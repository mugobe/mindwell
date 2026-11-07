<?php

namespace Tests\Feature;

use App\Models\AvailabilitySlot;
use App\Models\ChatConversation;
use App\Models\ClientProfile;
use App\Models\Homework;
use App\Models\Resource;
use App\Models\ResourceShare;
use App\Models\TherapistProfile;
use App\Models\TherapyGoal;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\MindwellNotice;
use App\Services\OpenSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 2026-11-07 round: payment addresses, one-off availability, message attachments, and the
 * care features (resource library, goals, homework, My therapy).
 */
class CareFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:00:00')); // Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function people(): array
    {
        $cu = User::factory()->create(array_merge(['role' => 'client', 'name' => 'Mugerwa Faizal'], User::consentAttributes()));
        $client = ClientProfile::create(['user_id' => $cu->id]);
        $tu = User::factory()->create(array_merge(['role' => 'therapist', 'name' => 'Grace Namuli'], User::consentAttributes()));
        $therapist = TherapistProfile::create(['user_id' => $tu->id, 'license_number' => 'UPC-1', 'license_state' => 'UG', 'credentials_verified' => true]);
        TherapySession::create(['client_profile_id' => $client->id, 'therapist_profile_id' => $therapist->id, 'status' => 'completed', 'scheduled_at' => now()->subDays(3), 'duration_minutes' => 70]);

        return [$cu, $client, $tu, $therapist];
    }

    private function admin(): User
    {
        return User::factory()->create(array_merge(['role' => 'admin'], User::consentAttributes(false)));
    }

    private function keep(string $name, string $html): void
    {
        if ($dir = getenv('MW_SAVE_HTML')) {
            file_put_contents(rtrim($dir, '/').'/'.$name.'.html', $html);
        }
    }

    public function test_old_payment_addresses_redirect_to_the_new_ones(): void
    {
        [$cu] = $this->people();

        $loc = $this->actingAs($cu)->get('/payments/flutterwave/callback?tx_ref=MW-1&status=successful')->assertStatus(301)->headers->get('Location');
        $this->assertStringContainsString('/payments/callback?', $loc);
        $this->assertStringContainsString('tx_ref=MW-1', $loc);
        $this->actingAs($cu)->get('/payments/flutterwave/abc-123/waiting')->assertRedirect('/payments/abc-123/waiting');
        $this->assertStringEndsWith('/payments/x/status', route('payments.status', 'x'));
    }

    public function test_repeats_weekly_can_be_unticked_and_one_off_times_are_bookable(): void
    {
        [, , $tu, $therapist] = $this->people();

        $this->actingAs($tu)->post(route('therapist.availability.store'), ['is_recurring' => '0', 'on_date' => '2026-11-12', 'start_time' => '14:00', 'end_time' => '16:00'])
            ->assertSessionHas('status', 'Hours added for Thursday 12 November only.');
        $slot = AvailabilitySlot::first();
        $this->assertFalse($slot->is_recurring);
        $this->assertSame('thursday', $slot->day_of_week);

        $times = app(OpenSlots::class)->for($therapist, 14)->map->toDateTimeString();
        $this->assertContains('2026-11-12 14:00:00', $times);
        $this->assertNotContains('2026-11-19 14:00:00', $times); // only that date

        $this->actingAs($tu)->post(route('therapist.availability.store'), ['is_recurring' => '0', 'start_time' => '14:00', 'end_time' => '16:00'])
            ->assertSessionHasErrors('on_date');
        $this->actingAs($tu)->post(route('therapist.availability.store'), ['is_recurring' => '1', 'day_of_week' => 'friday', 'start_time' => '09:00', 'end_time' => '12:00'])
            ->assertSessionHas('status', 'Weekly hours added.');
        $this->actingAs($tu)->get(route('therapist.availability.index'))->assertOk()->assertSee('12 Nov only');
    }

    public function test_messages_can_carry_a_photo_or_pdf_only_the_two_people_can_open(): void
    {
        [$cu, $client, $tu, $therapist] = $this->people();
        $conv = ChatConversation::create(['client_profile_id' => $client->id, 'therapist_profile_id' => $therapist->id]);

        $res = $this->actingAs($cu)->post(route('messages.send', $conv), ['file' => UploadedFile::fake()->create('worksheet.pdf', 300, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('message.attachment.kind', 'pdf')->assertJsonPath('message.attachment.name', 'worksheet.pdf');
        $msg = $conv->messages()->first();
        $this->assertSame('', $msg->body);
        $this->assertSame('Sent a PDF', $msg->previewText());

        $this->actingAs($tu)->get(route('messages.attachment', [$conv, $msg]))->assertOk();
        $stranger = User::factory()->create(array_merge(['role' => 'client'], User::consentAttributes()));
        ClientProfile::create(['user_id' => $stranger->id]);
        $this->actingAs($stranger)->get(route('messages.attachment', [$conv, $msg]))->assertForbidden();

        $this->actingAs($cu)->post(route('messages.send', $conv), ['file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->actingAs($cu)->post(route('messages.send', $conv), ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])
            ->assertStatus(422);

        // The app gets a signed link that works without logging in, and only that file.
        $url = $this->actingAs($cu, 'sanctum')->getJson('/api/v1/conversations/'.$conv->id.'/poll')->json('messages.0.attachment.url');
        $this->assertStringContainsString('signature=', $url);
        auth()->forgetGuards();
        $this->get($url)->assertOk();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=nope', $url))->assertForbidden();
    }

    public function test_admin_builds_the_library_and_clients_read_it(): void
    {
        [$cu, $client] = $this->people();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.resources.store'), ['title' => 'Sleep better tonight', 'kind' => 'article', 'topic' => 'Sleep', 'body' => "Keep a routine.\n\nNo screens late.", 'minutes' => 4, 'is_published' => 1])
            ->assertRedirect(route('admin.resources.index'));
        $this->actingAs($admin)->post(route('admin.resources.store'), ['title' => 'Thought record', 'kind' => 'pdf', 'topic' => 'Worksheets', 'file' => UploadedFile::fake()->create('thought-record.pdf', 120, 'application/pdf'), 'is_published' => 1]);
        $this->actingAs($admin)->post(route('admin.resources.store'), ['title' => 'Draft', 'kind' => 'link', 'url' => 'https://example.org', 'is_published' => 0]);
        $this->actingAs($admin)->post(route('admin.resources.store'), ['title' => 'Video', 'kind' => 'video'])->assertSessionHasErrors('url');
        $this->assertSame(3, Resource::count());

        $page = $this->actingAs($cu)->get(route('resources.index'))->assertOk()->assertSee('Sleep better tonight')->assertSee('Thought record')->assertDontSee('Draft');
        $this->keep('resources', $page->getContent());
        $article = Resource::where('kind', 'article')->first();
        $this->actingAs($cu)->get(route('resources.show', $article))->assertOk()->assertSee('No screens late.');
        $this->actingAs($cu)->get(route('resources.show', Resource::where('title', 'Draft')->first()))->assertNotFound();
        $this->actingAs($cu)->get(route('resources.file', Resource::where('kind', 'pdf')->first()))->assertOk();

        $this->actingAs($cu)->get(route('admin.resources.index'))->assertForbidden();
    }

    public function test_therapist_sets_goals_homework_and_shares_and_the_client_tracks_them(): void
    {
        [$cu, $client, $tu, $therapist] = $this->people();
        $sheet = Resource::create(['title' => 'Thought record', 'kind' => 'pdf', 'file_path' => 'resources/x.pdf', 'file_name' => 'x.pdf', 'file_mime' => 'application/pdf', 'is_published' => true]);
        $read = Resource::create(['title' => 'Breathing for calm', 'kind' => 'article', 'body' => 'Breathe out slowly.', 'is_published' => true]);

        // Therapist, from the client's file.
        $this->actingAs($tu)->post(route('therapist.care.goals.store', $client), ['title' => 'Sleep 7 hours most nights'])->assertRedirect();
        $this->actingAs($tu)->post(route('therapist.care.homework.store', $client), ['title' => 'Thought record, 3 times', 'instructions' => 'Write down one worry each evening.', 'resource_id' => $sheet->id, 'due_on' => '2026-11-14'])->assertRedirect();
        $this->actingAs($tu)->post(route('therapist.care.resources.share', $client), ['resource_id' => $read->id, 'note' => 'Try this before bed.'])->assertRedirect();
        Notification::assertSentToTimes($cu, MindwellNotice::class, 3);

        $file = $this->actingAs($tu)->get(route('therapist.clients.show', $client))->assertOk()->assertSee('Care plan')->assertSee('Sleep 7 hours most nights');
        $this->keep('client-file-care', $file->getContent());

        // A therapist with no sessions with this client can't.
        $otherU = User::factory()->create(array_merge(['role' => 'therapist'], User::consentAttributes()));
        TherapistProfile::create(['user_id' => $otherU->id, 'license_number' => 'UPC-2', 'license_state' => 'UG', 'credentials_verified' => true]);
        $this->actingAs($otherU)->post(route('therapist.care.goals.store', $client), ['title' => 'Nope'])->assertForbidden();

        // Client: My therapy, progress, homework answer.
        $page = $this->actingAs($cu)->get(route('care.index'))->assertOk()
            ->assertSee('Grace Namuli')->assertSee('Sleep 7 hours most nights')->assertSee('Thought record, 3 times')->assertSee('Breathing for calm');
        $this->keep('my-therapy', $page->getContent());

        $goal = TherapyGoal::first();
        $this->actingAs($cu)->post(route('care.goals.progress', $goal), ['progress' => 40, 'note' => 'Slept well 3 nights.'])->assertRedirect(route('care.index'));
        $this->assertSame(40, $goal->fresh()->progress);
        $this->assertSame('client', $goal->updates()->first()->by_role);

        $hw = Homework::first();
        $this->actingAs($cu)->get(route('care.homework', $hw))->assertOk()->assertSee('Write down one worry each evening.');
        $this->actingAs($cu)->post(route('care.homework.reply', $hw), [])->assertSessionHasErrors('reply');
        $this->actingAs($cu)->post(route('care.homework.reply', $hw), ['reply' => 'Did it twice.', 'file' => UploadedFile::fake()->image('sheet.jpg')])->assertRedirect(route('care.index'));
        $hw->refresh();
        $this->assertSame('done', $hw->status);
        $this->actingAs($tu)->get(route('homework.file', $hw))->assertOk();

        $this->actingAs($tu)->post(route('therapist.care.homework.review', $hw))->assertRedirect();
        $this->assertNotNull($hw->fresh()->reviewed_at);
        $this->actingAs($tu)->patch(route('therapist.care.goals.update', $goal), ['status' => 'achieved'])->assertRedirect();
        $this->assertSame(100, $goal->fresh()->progress);

        // Another client can't see any of it.
        $other = User::factory()->create(array_merge(['role' => 'client'], User::consentAttributes()));
        ClientProfile::create(['user_id' => $other->id]);
        $this->actingAs($other)->get(route('care.homework', $hw))->assertForbidden();
        $this->actingAs($other)->post(route('care.goals.progress', $goal), ['progress' => 10])->assertForbidden();

        // Opening the shared resource marks it opened.
        $this->actingAs($cu)->get(route('resources.show', $read))->assertOk()->assertSee('Try this before bed.');
        $this->assertNotNull(ResourceShare::first()->opened_at);
    }

    public function test_app_gets_my_therapy_resources_and_can_answer_homework(): void
    {
        [$cu, $client, $tu, $therapist] = $this->people();
        $read = Resource::create(['title' => 'Breathing for calm', 'kind' => 'audio', 'file_path' => 'resources/a.mp3', 'file_name' => 'a.mp3', 'file_mime' => 'audio/mpeg', 'is_published' => true, 'topic' => 'Breathing and relaxation']);
        Storage::disk('local')->put('resources/a.mp3', 'audio');
        $goal = TherapyGoal::create(['client_profile_id' => $client->id, 'therapist_profile_id' => $therapist->id, 'title' => 'Walk daily', 'status' => 'active', 'progress' => 0]);
        $hw = Homework::create(['client_profile_id' => $client->id, 'therapist_profile_id' => $therapist->id, 'title' => 'Gratitude list', 'status' => 'assigned']);
        ResourceShare::create(['resource_id' => $read->id, 'client_profile_id' => $client->id, 'therapist_profile_id' => $therapist->id]);

        $this->actingAs($cu, 'sanctum')->getJson('/api/v1/my-therapy')->assertOk()
            ->assertJsonPath('therapist.name', 'Grace Namuli')->assertJsonPath('goals.0.title', 'Walk daily')
            ->assertJsonPath('homework.0.title', 'Gratitude list')->assertJsonPath('shared_resources.0.resource.title', 'Breathing for calm');
        $this->actingAs($cu, 'sanctum')->getJson('/api/v1/resources?topic=Breathing and relaxation')->assertOk()->assertJsonCount(1, 'resources');
        $fileUrl = $this->actingAs($cu, 'sanctum')->getJson('/api/v1/resources/'.$read->id)->assertOk()->json('resource.file_url');
        auth()->forgetGuards();
        $this->get($fileUrl)->assertOk();

        $this->actingAs($cu, 'sanctum')->postJson('/api/v1/goals/'.$goal->id.'/progress', ['progress' => 30])->assertOk()->assertJsonPath('goal.progress', 30);
        $this->actingAs($cu, 'sanctum')->post('/api/v1/homework/'.$hw->id.'/reply', ['reply' => 'Wrote 5 things.', 'file' => UploadedFile::fake()->create('list.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('homework.status', 'done')->assertJsonPath('homework.reply_file.name', 'list.pdf');
    }
}
