<?php

namespace Tests\Feature;

use App\Http\Controllers\FlutterwavePaymentController;
use App\Models\ClientProfile;
use App\Models\PaymentTransaction;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Checkout;
use App\Services\MarzPayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MarzPay (2026-11-02), tested against fake MarzPay answers shaped like their guide.
 * The real sandbox is checked separately with `php artisan marzpay:check`.
 *
 * Needs MySQL/MariaDB (some older migrations use MySQL-only ALTERs), e.g.
 *   DB_CONNECTION=mysql DB_DATABASE=mw_test php artisan test --filter=MarzPay
 */
class MarzPayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://wallet.wearemarz.com/api/v1';

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.gateway' => 'marzpay',
            'marzpay.api_key' => 'test_key',
            'marzpay.api_secret' => 'test_secret',
            'marzpay.base_url' => self::BASE,
            'marzpay.callback_url' => 'https://mindwell.example/webhooks/marzpay',
            'marzpay.webhook_secret' => self::SECRET,
            'marzpay.sandbox' => true,
            'marzpay.log_payloads' => false,
            'flutterwave.currency' => 'UGX',
        ]);
    }

    /** A fresh set of fake MarzPay answers (Http::fake() on its own keeps the old ones first). */
    private function fakeOnly(array $stubs): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs);
    }

    /** A client with a self-booked session waiting for payment, and its pending transaction. */
    private function pendingSession(float $amount = 15000): array
    {
        $user = User::factory()->create(array_merge(['role' => 'client'], User::consentAttributes()));
        $profile = ClientProfile::create(['user_id' => $user->id]);
        $session = TherapySession::create([
            'client_profile_id' => $profile->id,
            'status' => 'pending_payment',
            'routing_method' => 'self_booked',
        ]);

        $result = app(Checkout::class)->createTransaction(null, 'session', $amount, $profile, [], [
            'client_profile_id' => $profile->id,
            'purpose' => 'session',
            'therapy_session_id' => $session->id,
        ], 'MW-S-', ['network' => 'MTN', 'phone_number' => '0772123456']);

        return [$user, $session, $result['transaction']];
    }

    private function createdBody(PaymentTransaction $t, string $status = 'processing'): array
    {
        return [
            'status' => 'success',
            'message' => 'Collection initiated successfully (Sandbox Mode).',
            'data' => [
                'transaction' => ['uuid' => 'mz-uuid-1', 'reference' => $t->gateway_reference, 'status' => $status, 'provider_reference' => null],
                'collection' => ['amount' => ['formatted' => '15,000.00', 'raw' => (float) $t->amount, 'currency' => 'UGX'], 'provider' => 'mtn', 'phone_number' => '+256772123456', 'mode' => 'sandbox'],
                'metadata' => ['response_timestamp' => '2026-11-02 12:00:00', 'sandbox_mode' => true],
            ],
        ];
    }

    private function statusBody(PaymentTransaction $t, string $status, ?float $amount = null): array
    {
        return [
            'status' => 'success',
            'data' => [
                'transaction' => [
                    'uuid' => 'mz-uuid-1', 'reference' => $t->gateway_reference, 'status' => $status,
                    'amount' => ['formatted' => '15,000.00', 'raw' => $amount ?? (float) $t->amount, 'currency' => 'UGX'],
                    'provider' => 'mtn',
                ],
            ],
        ];
    }

    private function callbackBody(PaymentTransaction $t, string $event = 'collection.completed', string $status = 'completed'): array
    {
        return [
            'event_type' => $event,
            'transaction' => [
                'uuid' => 'mz-uuid-1', 'reference' => $t->gateway_reference, 'status' => $status,
                'amount' => ['formatted' => '15,000.00', 'raw' => (float) $t->amount, 'currency' => 'UGX'],
                'charge' => ['formatted' => '450.00', 'raw' => 450, 'currency' => 'UGX'],
                'net_amount' => ['formatted' => '14,550.00', 'raw' => (float) $t->amount - 450, 'currency' => 'UGX'],
                'provider' => 'mtn', 'phone_number' => '+256772123456',
            ],
            'collection' => ['provider' => 'mtn', 'mode' => 'mtnuganda', 'provider_transaction_id' => '148769164724'],
            'metadata' => [['mindwell_payment' => (string) $t->id], ['purpose' => 'session']],
        ];
    }

    private function postSigned(array $body, ?string $secret = self::SECRET, ?int $ts = null)
    {
        $raw = json_encode($body);
        $ts ??= time();
        $sig = 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$raw, (string) $secret);

        return $this->call('POST', '/webhooks/marzpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MARZPAY_TIMESTAMP' => (string) $ts,
            'HTTP_X_MARZPAY_SIGNATURE' => $sig,
        ], $raw);
    }

    public function test_start_sends_what_marzpay_asks_for(): void
    {
        [$user, , $t] = $this->pendingSession();

        $this->assertSame('marzpay', $t->gateway);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $t->gateway_reference);

        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t, 'sandbox'), 201)]);

        $r = app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);

        $this->assertTrue($r['ok']);
        Http::assertSent(function (HttpRequest $req) use ($t) {
            $d = $req->data();

            return $req->url() === self::BASE.'/collect-money'
                && $req->hasHeader('Authorization', 'Basic '.base64_encode('test_key:test_secret'))
                && $d['amount'] === 15000
                && $d['phone_number'] === '+256772123456'
                && $d['reference'] === $t->gateway_reference
                && $d['country'] === 'UG'
                && $d['callback_url'] === 'https://mindwell.example/webhooks/marzpay'
                && $d['description'] === 'Mindwell Consultancy payment'
                && $d['metadata'] === [['mindwell_payment' => (string) $t->id], ['purpose' => 'session']];
        });

        $t->refresh();
        $this->assertSame('mz-uuid-1', $t->flw_transaction_id);
        $this->assertSame('pending', $t->status);
        $this->assertTrue($t->meta['sandbox']);
        $this->assertTrue($t->isTest());
    }

    public function test_status_polling_waits_then_finalizes_once(): void
    {
        [$user, $session, $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);

        $this->fakeOnly([self::BASE.'/collect-money/mz-uuid-1' => Http::sequence()
            ->push($this->statusBody($t, 'processing'))
            ->push($this->statusBody($t, 'completed'))]);

        $c = app(FlutterwavePaymentController::class);
        $this->assertSame('pending', $c->checkForApi($t->fresh(), null)['state']);
        $this->assertSame('succeeded', $c->checkForApi($t->fresh(), null)['state']);

        $t->refresh();
        $this->assertSame('successful', $t->status);
        $this->assertNotNull($t->processed_at);
        $this->assertSame('pending_approval', $session->fresh()->status);
        $this->assertStringNotContainsString('772123456', json_encode($t->meta), 'no phone number in meta');
        $this->assertTrue($t->isTest(), 'still a test payment after finalize');

        // Already processed: no further call to MarzPay.
        $this->assertSame('succeeded', $c->checkForApi($t->fresh(), null)['state']);
    }

    public function test_signed_callback_finalizes_and_duplicate_is_ignored(): void
    {
        [$user, $session, $t] = $this->pendingSession();
        $this->fakeOnly([
            self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201),
            self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($t, 'completed')),
        ]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);
        $t->refresh();

        $this->postSigned($this->callbackBody($t))->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame('successful', $t->fresh()->status);
        $this->assertSame('pending_approval', $session->fresh()->status);

        $this->postSigned($this->callbackBody($t))->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_dashboard_wrapped_callback_works_too(): void
    {
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly([
            self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201),
            self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($t, 'completed')),
        ]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);
        $t->refresh();

        $wrapped = ['event_type' => 'collection.completed', 'webhook_id' => 1, 'business_id' => 2, 'timestamp' => now()->toIso8601String(), 'data' => $this->callbackBody($t)];
        $this->postSigned($wrapped)->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame('successful', $t->fresh()->status);
    }

    public function test_bad_or_old_signature_is_refused(): void
    {
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);
        $t->refresh();

        $this->postSigned($this->callbackBody($t), 'wrong_secret')->assertStatus(401);
        $this->postSigned($this->callbackBody($t), self::SECRET, time() - 3600)->assertStatus(401);
        $this->postJson('/webhooks/marzpay', $this->callbackBody($t))->assertStatus(401);
        $this->assertSame('pending', $t->fresh()->status);
    }

    public function test_callback_is_never_trusted_on_its_own(): void
    {
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly([
            self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201),
            // The callback says completed, but MarzPay itself still says processing.
            self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($t, 'processing')),
        ]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);
        $t->refresh();

        $this->postSigned($this->callbackBody($t))->assertOk();
        $this->assertSame('pending', $t->fresh()->status);
        $this->assertNull($t->fresh()->processed_at);
    }

    public function test_failed_and_wrong_amount_are_not_paid(): void
    {
        [$user, $session, $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);

        $this->fakeOnly([self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($t->fresh(), 'completed', 500))]);
        $this->assertSame('failed', app(FlutterwavePaymentController::class)->checkForApi($t->fresh(), null)['state']);
        $this->assertNull($t->fresh()->processed_at);
        $this->assertSame('pending_payment', $session->fresh()->status);

        [$user2, , $t2] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t2), 201)]);
        app(Checkout::class)->startMobileMoney($t2, ['network' => 'MTN', 'phone_number' => '0772123456'], $user2);
        $this->fakeOnly([self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($t2->fresh(), 'failed'))]);
        $this->assertSame('failed', app(FlutterwavePaymentController::class)->checkForApi($t2->fresh(), null)['state']);
    }

    public function test_falls_back_to_transactions_endpoint(): void
    {
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);
        $t->refresh();

        $this->fakeOnly([
            self::BASE.'/collect-money/mz-uuid-1' => Http::response(['status' => 'success', 'data' => ['something' => 'else']]),
            self::BASE.'/transactions/mz-uuid-1' => Http::response($this->callbackBody($t)),
        ]);

        $this->assertSame('succeeded', app(FlutterwavePaymentController::class)->checkForApi($t, null)['state']);
    }

    public function test_refusals_are_shown_kindly_and_nothing_is_charged(): void
    {
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response(['status' => 'error', 'message' => 'Bad phone', 'error_code' => 'INVALID_PHONE_NUMBER'], 422)]);

        $r = app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('number could not be used', $r['error']);
        $this->assertSame('failed', $t->fresh()->status);

        [$user2, , $small] = $this->pendingSession(300);
        $this->fakeOnly([]);
        $r = app(Checkout::class)->startMobileMoney($small, ['network' => 'MTN', 'phone_number' => '0772123456'], $user2);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('at least UGX 500', $r['error']);
        Http::assertNothingSent();
    }

    public function test_flutterwave_stays_the_default(): void
    {
        config(['payments.gateway' => 'flutterwave']);
        [, , $t] = $this->pendingSession();

        $this->assertSame('flutterwave', $t->gateway);
        $this->assertNull($t->gateway_reference);
    }

    public function test_pure_helpers(): void
    {
        $this->assertSame('+256772123456', MarzPayClient::e164('0772 123 456'));
        $this->assertSame('+256772123456', MarzPayClient::e164('256772123456'));
        $this->assertSame('+256772123456', MarzPayClient::e164('+256 772 123456'));
        $this->assertSame('+256772123456', MarzPayClient::e164('772123456'));
        $this->assertNull(MarzPayClient::e164('0412123456'));
        $this->assertNull(MarzPayClient::e164('07721234567'));

        $this->assertSame('succeeded', MarzPayClient::mapStatus('completed'));
        $this->assertSame('failed', MarzPayClient::mapStatus('failed'));
        $this->assertSame('cancelled', MarzPayClient::mapStatus('cancelled'));
        $this->assertSame('processing', MarzPayClient::mapStatus('processing'));
        $this->assertSame('sandbox', MarzPayClient::mapStatus('sandbox'));

        $this->assertSame('+256772***456', MarzPayClient::maskPhones('+256772123456'));
        $this->assertSame('0772***456', MarzPayClient::maskPhones('0772123456'));

        $raw = '{"a":1}';
        $ts = 1767000000;
        $hex = hash_hmac('sha256', $ts.'.'.$raw, 'test_secret');
        $this->assertTrue(MarzPayClient::signatureValid($raw, "t=$ts,v1=$hex", null, 'whsec_test_secret', $ts), 'whsec_ prefix stripped');
        $this->assertTrue(MarzPayClient::signatureValid($raw, "t=$ts,v1=$hex", null, 'test_secret', $ts + 100));
        $this->assertFalse(MarzPayClient::signatureValid($raw, "t=$ts,v1=$hex", null, 'test_secret', $ts + 1000));
        $this->assertFalse(MarzPayClient::signatureValid('{"a":2}', "t=$ts,v1=$hex", null, 'test_secret', $ts));
        $this->assertFalse(MarzPayClient::signatureValid($raw, null, null, 'test_secret', $ts));
    }

    public function test_waiting_page_and_unknown_callback(): void
    {
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t, 'sandbox'), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);

        $this->withoutVite();
        $this->actingAs($user)->get(route('payments.waiting', $t))
            ->assertOk()
            ->assertSee('This is a test payment (sandbox)', false);

        $stranger = $this->callbackBody($t->fresh());
        $stranger['transaction']['reference'] = MarzPayClient::uuid4();
        $stranger['transaction']['uuid'] = 'someone-else';
        $this->postSigned($stranger)->assertOk()->assertJson(['status' => 'ignored']);
    }

    /** Real sandbox, 2026-11-03: create works, every lookup answers 404 "Transaction not found." */
    private function sandboxNotFound(): array
    {
        return [
            self::BASE.'/collect-money/mz-uuid-1' => Http::response(['status' => 'error', 'message' => 'Transaction not found.'], 404),
            self::BASE.'/transactions/mz-uuid-1' => Http::response(['status' => 'error', 'message' => 'Transaction not found.'], 404),
        ];
    }

    public function test_sandbox_not_found_means_still_waiting_not_an_error(): void
    {
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t, 'sandbox'), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'AIRTEL', 'phone_number' => '0701123599'], $user);

        $this->fakeOnly($this->sandboxNotFound());
        $check = app(FlutterwavePaymentController::class)->checkForApi($t->fresh(), null);

        $this->assertSame('pending', $check['state']);
        $this->assertSame('pending', $t->fresh()->status);

        $this->withoutVite();
        $this->actingAs($user)->get(route('payments.callback', ['tx_ref' => $t->tx_ref]))
            ->assertRedirect(route('payments.waiting', $t))
            ->assertSessionMissing('error');
    }

    public function test_signed_sandbox_callback_completes_on_staging_only(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        [$user, $session, $t] = $this->pendingSession();
        $this->fakeOnly(array_merge([self::BASE.'/collect-money' => Http::response($this->createdBody($t, 'sandbox'), 201)], $this->sandboxNotFound()));
        app(Checkout::class)->startMobileMoney($t, ['network' => 'AIRTEL', 'phone_number' => '0701123599'], $user);
        $t->refresh();

        // Unsigned: not trusted, stays waiting.
        config(['marzpay.webhook_secret' => null]);
        $this->postJson('/webhooks/marzpay', $this->callbackBody($t))->assertOk();
        $this->assertSame('pending', $t->fresh()->status);

        // Signed: used as the answer, because the sandbox has nothing to look up.
        config(['marzpay.webhook_secret' => self::SECRET]);
        $this->postSigned($this->callbackBody($t))->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame('successful', $t->fresh()->status);
        $this->assertSame('pending_approval', $session->fresh()->status);
        $this->assertSame('signed_sandbox_callback', $t->fresh()->meta['verified_by']);
    }

    public function test_signed_callback_never_trusted_alone_in_production_or_live(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        [$user, , $t] = $this->pendingSession();
        $this->fakeOnly(array_merge([self::BASE.'/collect-money' => Http::response($this->createdBody($t, 'sandbox'), 201)], $this->sandboxNotFound()));
        app(Checkout::class)->startMobileMoney($t, ['network' => 'AIRTEL', 'phone_number' => '0701123599'], $user);
        $this->postSigned($this->callbackBody($t->fresh()))->assertOk();
        $this->assertSame('pending', $t->fresh()->status);

        // Staging, but a LIVE payment (account out of sandbox): still not trusted alone.
        $this->app->detectEnvironment(fn () => 'staging');
        config(['marzpay.sandbox' => false]);
        [$user2, , $live] = $this->pendingSession();
        $live->update(['meta' => null]);
        $liveCreated = $this->createdBody($live, 'processing');
        $liveCreated['message'] = 'Collection initiated successfully.';
        $liveCreated['data']['collection']['mode'] = 'live';
        $liveCreated['data']['metadata']['sandbox_mode'] = false;
        $this->fakeOnly(array_merge([self::BASE.'/collect-money' => Http::response($liveCreated, 201)], $this->sandboxNotFound()));
        app(Checkout::class)->startMobileMoney($live, ['network' => 'MTN', 'phone_number' => '0772123456'], $user2);
        $this->assertFalse($live->fresh()->meta['sandbox']);
        $this->postSigned($this->callbackBody($live->fresh()))->assertOk();
        $this->assertSame('pending', $live->fresh()->status);
    }

    public function test_a_failed_payment_says_why_and_that_nothing_was_charged(): void
    {
        [$user, $session, $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'AIRTEL', 'phone_number' => '0705274980'], $user);

        $failed = $this->statusBody($t->fresh(), 'failed');
        $failed['data']['transaction']['failure_reason'] = 'Insufficient balance';
        $this->fakeOnly([self::BASE.'/collect-money/mz-uuid-1' => Http::response($failed)]);

        // App: the message names the reason and says no money was taken.
        $api = app(FlutterwavePaymentController::class)->checkForApi($t->fresh(), null);
        $this->assertSame('failed', $api['state']);
        $this->assertStringContainsString("wasn't enough money", $api['message']);
        $this->assertStringContainsString('not been charged', $api['message']);

        // Website: back to checkout with the notice, the number filled in again.
        $this->withoutVite();
        $this->actingAs($user)->withSession(['mw_last_momo' => ['network' => 'AIRTEL', 'phone_number' => '0705274980']])
            ->get(route('payments.waiting', $t))
            ->assertRedirect(route('sessions.checkout', $session))
            ->assertSessionHas('payment_failed', fn ($n) => $n['kind'] === 'insufficient');
        $this->get(route('sessions.checkout', $session))->assertOk()
            ->assertSee('Payment not completed')->assertSee('You have not been charged.')->assertSee('0705274980');

        // A cancelled prompt.
        $this->assertSame('declined', MarzPayClient::failureKind(['status' => 'cancelled', 'marzpay_status' => 'cancelled']));
        $this->assertSame('declined', MarzPayClient::failureKind(['status' => 'failed', 'reason' => 'User entered wrong PIN']));
        $this->assertSame('unknown', MarzPayClient::failureKind(['status' => 'failed']));
    }

    public function test_no_way_around_paying_unless_test_mode_is_switched_on(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        [$user, $session, $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t, 'sandbox'), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'MTN', 'phone_number' => '0772123456'], $user);
        $this->withoutVite();

        // Default: off, even on staging with a sandbox account.
        $this->assertFalse(app(FlutterwavePaymentController::class)->simulationAllowed());
        $this->actingAs($user)->get(route('payments.waiting', $t))->assertOk()->assertDontSee('Simulate approval');
        $this->withSession(['_token' => 'test-token'])
            ->post(route('payments.simulate', $t), ['_token' => 'test-token', 'outcome' => 'approve'])->assertNotFound();
        $this->assertSame('pending_payment', $session->fresh()->status);

        // Only an explicit PAYMENT_TEST_MODE=true brings the buttons back.
        config(['payments.test_mode' => true]);
        $this->assertTrue(app(FlutterwavePaymentController::class)->simulationAllowed());
        $this->get(route('payments.waiting', $t))->assertSee('Simulate approval');
    }

    /** Real live behaviour, 2026-11-04: a cancelled Airtel prompt stayed "pending" for 10+ minutes. */
    private function startedPending(): array
    {
        [$user, $session, $t] = $this->pendingSession();
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($this->createdBody($t), 201)]);
        app(Checkout::class)->startMobileMoney($t, ['network' => 'AIRTEL', 'phone_number' => '0701123599'], $user);
        $this->fakeOnly([self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($t->fresh(), 'pending'))]);

        return [$user, $session, $t->fresh()];
    }

    public function test_waiting_stops_at_the_deadline_with_a_clear_message(): void
    {
        [$user, $session, $t] = $this->startedPending();
        $this->withoutVite();

        $this->actingAs($user)->get(route('payments.waiting', $t))->assertOk()
            ->assertSee('Cancel payment')->assertSee('Waiting for your approval');
        $this->getJson(route('payments.status', $t))->assertOk()->assertJson(['state' => 'pending', 'next' => null]);

        $this->travel(4)->minutes();
        $this->getJson(route('payments.status', $t))->assertOk()
            ->assertJson(['state' => 'failed', 'next' => route('payments.waiting', $t)]);
        $this->assertSame('cancelled', $t->fresh()->status);
        $this->assertSame('timed_out', $t->fresh()->closedReason());

        $this->get(route('payments.waiting', $t))->assertRedirect(route('sessions.checkout', $session))
            ->assertSessionHas('payment_failed', fn ($n) => $n['kind'] === 'timed_out');
        $this->get(route('sessions.checkout', $session))->assertSee("We didn't get a confirmation from your phone in time.")->assertSee('decline it');
    }

    public function test_client_can_cancel_and_a_late_approval_is_still_recorded(): void
    {
        [$user, $session, $t] = $this->startedPending();
        $this->withoutVite();

        $this->actingAs($user)->withSession(['_token' => 'x'])->post(route('payments.cancel', $t), ['_token' => 'x'])
            ->assertRedirect(route('sessions.checkout', $session))
            ->assertSessionHas('payment_failed', fn ($n) => $n['kind'] === 'client_cancelled');
        $this->assertSame('client_cancelled', $t->fresh()->closedReason());
        $this->assertSame('pending_payment', $session->fresh()->status);

        // They approve the re-sent prompt a few minutes later: the background check records it.
        $this->fakeOnly([self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($t->fresh(), 'completed'))]);
        $this->artisan('mindwell:payments-reconcile')->assertSuccessful();

        $this->assertSame('successful', $t->fresh()->status);
        $this->assertSame('pending_approval', $session->fresh()->status);
        $this->assertSame('client_cancelled', $t->fresh()->meta['closed_reason']);
    }

    public function test_late_approval_after_paying_again_is_flagged_not_applied_twice(): void
    {
        config(['marzpay.webhook_secret' => null]);
        $admin = User::factory()->create(array_merge(['role' => 'admin'], User::consentAttributes(false)));
        \Illuminate\Support\Facades\Notification::fake();

        [$user, $session, $first] = $this->startedPending();
        $first->closeUnpaid('timed_out');

        // Second attempt succeeds and books the session.
        $second = app(Checkout::class)->createTransaction(null, 'session', 15000, $user->clientProfile, [], [
            'client_profile_id' => $user->clientProfile->id, 'purpose' => 'session', 'therapy_session_id' => $session->id,
        ], 'MW-S-', ['network' => 'AIRTEL', 'phone_number' => '0701123599'])['transaction'];
        $created2 = $this->createdBody($second);
        $created2['data']['transaction']['uuid'] = 'mz-uuid-2';
        $this->fakeOnly([self::BASE.'/collect-money' => Http::response($created2, 201)]);
        app(Checkout::class)->startMobileMoney($second, ['network' => 'AIRTEL', 'phone_number' => '0701123599'], $user);
        $done2 = $this->statusBody($second->fresh(), 'completed');
        $done2['data']['transaction']['uuid'] = 'mz-uuid-2';
        $this->fakeOnly([
            self::BASE.'/collect-money/mz-uuid-2' => Http::response($done2),
            self::BASE.'/collect-money/mz-uuid-1' => Http::response($this->statusBody($first->fresh(), 'completed')),
        ]);
        $this->assertSame('succeeded', app(FlutterwavePaymentController::class)->checkForApi($second->fresh(), null)['state']);
        $this->assertSame('pending_approval', $session->fresh()->status);

        // Then the first, re-sent prompt is approved too.
        $this->artisan('mindwell:payments-reconcile')->assertSuccessful();

        $first->refresh();
        $this->assertSame('successful', $first->status);
        $this->assertSame('already_paid', $first->meta['unapplied']);
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\MindwellNotice::class, fn ($n) => $n->subject === 'A payment needs a refund or credit');
    }
}
