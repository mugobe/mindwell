<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Wrapper around Flutterwave's v4 REST API — REBUILT 2026-09-27, same day as the first
 * version, after discovering (by actually testing against the real sandbox with this
 * account's real credentials) that this account only has v4-style OAuth2 credentials
 * — no classic v3 secret/public key pair exists on it at all. v4 has no equivalent of
 * v3's fully-hosted "Standard" checkout page (redirect to Flutterwave, they collect
 * card AND Mobile Money details, we never see either), so this currently covers
 * MOBILE MONEY ONLY — the payment method that actually matters for this market. Card
 * support needs its own follow-up round: v4 requires card number/expiry/CVV encrypted
 * client-side before they ever reach our server, which means building an actual
 * card-entry form + JS encryption, not just adding a method here.
 *
 * Still deliberately just Laravel's own Http client, not a Composer SDK — Flutterwave
 * doesn't maintain an official first-party PHP package for v4, and the handful of
 * community ones aren't a clear enough win over a few plain Http:: calls to take on as
 * a dependency (same reasoning as the "No boilerplate/scaffolding tools" project note).
 */
class FlutterwaveClient
{
    /**
     * OAuth2 client-credentials token, cached for slightly less than its real 600-
     * second lifetime so a burst of checkouts doesn't hit the token endpoint on every
     * single one. Confirmed working 2026-09-27 against the real sandbox.
     */
    private function accessToken(): string
    {
        return Cache::remember('flutterwave.access_token', 540, function () {
            $clientId = config('flutterwave.client_id');
            $clientSecret = config('flutterwave.client_secret');

            if (empty($clientId) || empty($clientSecret)) {
                throw new RuntimeException('FLUTTERWAVE_CLIENT_ID / FLUTTERWAVE_CLIENT_SECRET are not set — add them to .env.');
            }

            $response = Http::asForm()->post(config('flutterwave.token_url'), [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            $body = $response->json();

            if (! $response->successful() || empty($body['access_token'])) {
                throw new RuntimeException($body['error_description'] ?? $body['error'] ?? 'Flutterwave did not return an access token.');
            }

            return $body['access_token'];
        });
    }

    /**
     * Every authenticated v4 call needs these three — X-Trace-Id and X-Idempotency-Key
     * are both REQUIRED (confirmed in Flutterwave's own v4 integration guides), not
     * optional extras the way they might be on some other APIs. $idempotencyKey should
     * be something stable per logical attempt (our own tx_ref works well) so a network-
     * level retry of the exact same request can't double-charge.
     */
    private function headers(string $idempotencyKey): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->accessToken(),
            'Content-Type' => 'application/json',
            'X-Trace-Id' => (string) Str::uuid(),
            'X-Idempotency-Key' => $idempotencyKey,
        ];

        // Sandbox-only testing hook: Flutterwave's v4 API reference lists an optional
        // X-Scenario-Key header for simulating outcomes in sandbox. The exact values
        // aren't published anywhere we could find, so this stays unset unless someone
        // deliberately puts one in .env while testing. Never set this against live.
        if ($scenario = config('flutterwave.scenario_key')) {
            $headers['X-Scenario-Key'] = $scenario;
        }

        return $headers;
    }

    /**
     * Initiates a Mobile Money Uganda charge via v4's "orchestrator" endpoint (one
     * call, rather than the separate customer/payment-method/charge steps v4 also
     * offers for saved/recurring payment methods — this app doesn't need those).
     *
     * CONFIRMED 2026-09-28 against the real sandbox: a Mobile Money charge comes back
     * HTTP 201 with `status: "pending"` and `next_action.type: "payment_instruction"`
     * (plus a human-readable `note` — "Please authorise this payment on your mobile
     * number..."). There is NO Flutterwave page to send the client to: they approve
     * on their phone, and we find out by polling verifyCharge() (or via webhook).
     * `redirect_url` is only returned for next_action types that actually need one —
     * note the response ALSO echoes back the redirect_url WE sent in the request at
     * the top level, which must NOT be mistaken for an approval page.
     *
     * @return array{id: string, redirect_url: ?string, instruction: ?string}
     * @throws RuntimeException on anything Flutterwave rejects SYNCHRONOUSLY (e.g. a
     *                           malformed phone number). A real charge failure that
     *                           only becomes known asynchronously (the client declines
     *                           the PIN prompt) is NOT an exception here — that's
     *                           discovered later via verifyCharge().
     */
    public function initiateMobileMoneyCharge(
        string $reference,
        float $amount,
        string $currency,
        string $network,
        string $phoneNumber,
        array $customer
    ): array {
        // No redirect_url is sent for Mobile Money. Flutterwave rejects a local one
        // ("Redirect url is invalid" for http://127.0.0.1:8000/..., confirmed
        // 2026-09-28), and Mobile Money never redirects anyway — the client approves on
        // their phone (next_action = payment_instruction). The diagnostic run confirmed
        // a charge without redirect_url is accepted (test C, HTTP 201). Card payments
        // WILL need one, and a public https URL, when that round is built.
        // $phoneNumber is expected LOCAL-only (no leading 0, no country code) — see
        // SessionRequestController::normalizePhoneNumber()/SubscriptionController's
        // twin. '256' is Uganda's numeric calling code, confirmed against a real
        // worked v4 example from Flutterwave's own engineering blog (which used '233'
        // for a Ghana example) — the first version of this method wrongly used the
        // ISO alpha-2 code 'UG' here, and passed the full 256-prefixed number as
        // phone_number too. Both are corrected 2026-09-28 after a real "Malformed
        // request" response confirmed the old shape didn't validate.
        [$firstName, $lastName] = $this->splitName($customer['name'] ?? '');

        $response = Http::withHeaders($this->headers($reference))
            ->baseUrl(config('flutterwave.base_url'))
            ->post('/orchestration/direct-charges', [
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                // MUST be (object) [] — a plain PHP [] json_encodes as `[]` (an array),
                // and Flutterwave rejects that with a bare "Malformed request". This was
                // THE bug, confirmed 2026-09-28 by sending the identical request with
                // `"meta":[]` (rejected) and `"meta":{}` (accepted) side by side.
                'meta' => (object) [],
                'customer' => [
                    'email' => $customer['email'] ?? null,
                    // MUST be an object ({first, last}), not a flat string — sending a
                    // string here is the likely cause of the very first "Malformed
                    // request" response, since it's a type mismatch strict enough to
                    // fail before any single field gets its own validation error.
                    'name' => ['first' => $firstName, 'last' => $lastName],
                    'phone' => ['country_code' => '256', 'number' => $phoneNumber],
                ],
                'payment_method' => [
                    'type' => 'mobile_money',
                    'mobile_money' => [
                        'country_code' => '256',
                        'network' => $network,
                        'phone_number' => $phoneNumber,
                    ],
                ],
            ]);

        $body = $response->json();
        // v4's own docs/examples are inconsistent about whether responses are wrapped
        // in a top-level `data` key (like v3 always was) or returned flat — this
        // accepts either shape rather than guessing wrong and silently misreading it.
        $data = $body['data'] ?? $body;

        if (! $response->successful() || empty($data['id'])) {
            // Logged in full (status + raw body) since v4's own docs don't publish a
            // worked validation-error example — this is the fastest way to see exactly
            // which field Flutterwave objected to, without needing to reproduce it via
            // a separate curl call. Check storage/logs/laravel.log after a failed
            // attempt if the surfaced message alone isn't specific enough.
            Log::warning('Flutterwave direct-charge rejected', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new RuntimeException(
                $body['message']
                ?? $body['error']['message']
                ?? (is_array($body['errors'] ?? null) ? json_encode($body['errors']) : null)
                ?? 'Flutterwave rejected this Mobile Money charge.'
            );
        }

        $nextAction = $data['next_action'] ?? [];
        $redirect = $nextAction['redirect_url'] ?? null;

        return [
            'id' => (string) $data['id'],
            // Only a redirect Flutterwave asks for via next_action — never the top-level
            // `redirect_url`, which is just our own callback URL echoed back.
            'redirect_url' => is_array($redirect) ? ($redirect['url'] ?? null) : $redirect,
            'instruction' => $nextAction['payment_instruction']['note'] ?? null,
        ];
    }

    /**
     * Flutterwave's v4 customer object wants a first/last name pair, not a single
     * string — this app only ever collects one `name` field on `User`, so this just
     * splits it on the first space. Good enough for a sandbox test account; may need
     * a real first/last split on the User model if Flutterwave ever rejects a
     * single-word name.
     *
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [$parts[0] ?: 'Client', $parts[1] ?? ($parts[0] ?: 'Client')];
    }

    /**
     * Confirms what actually happened to a charge directly with Flutterwave's own
     * records — never trust the redirect callback or webhook body alone. Returns the
     * charge object, expected to carry (at minimum) `status`, `amount`, `currency`,
     * and `reference`.
     *
     * GET /charges/{id} is the retrieve-a-charge endpoint per Flutterwave's own v4
     * guide. The charge-creation response confirmed 2026-09-28 already carries the
     * fields relied on here (`status` — "pending" there, "succeeded" on success per
     * the docs' example — plus `amount`, `currency`, `reference`, all inside `data`);
     * this call is assumed to return the same object shape. Still logged raw on first
     * use by FlutterwavePaymentController in case that assumption is off.
     *
     * Uses a FRESH idempotency key every call, unlike initiateMobileMoneyCharge():
     * Flutterwave caches responses per idempotency key (the sandbox sends an
     * `x-idempotency-cache-hit` header), so reusing a fixed key here could keep
     * replaying the first "pending" answer forever while the client waits.
     *
     * @throws RuntimeException if the verify call itself fails (network/auth error —
     *                           distinct from a successfully-verified FAILED charge).
     */
    public function verifyCharge(string $chargeId): array
    {
        $response = Http::withHeaders($this->headers('verify-'.Str::uuid()))
            ->baseUrl(config('flutterwave.base_url'))
            ->get("/charges/{$chargeId}");

        $body = $response->json();
        $data = $body['data'] ?? $body;

        if (! $response->successful() || empty($data)) {
            Log::warning('Flutterwave verify-charge call itself failed', [
                'charge_id' => $chargeId,
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new RuntimeException($body['message'] ?? 'Flutterwave could not verify this charge.');
        }

        return $data;
    }
}