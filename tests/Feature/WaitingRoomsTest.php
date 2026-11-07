<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The calm waiting screens (2026-11-06): the "finding a therapist" lobby and the video
 * waiting room. Checks the pages render with the new parts, and that the lobby's status
 * check moves the client on with the therapist's name.
 */
class WaitingRoomsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'livekit.url' => 'wss://example.livekit.cloud',
            'livekit.api_key' => 'APItest',
            'livekit.api_secret' => str_repeat('s', 40),
        ]);
    }

    private function client(): array
    {
        $user = User::factory()->create(array_merge(['role' => 'client', 'name' => 'Joseph Okello'], User::consentAttributes()));

        return [$user, ClientProfile::create(['user_id' => $user->id])];
    }

    private function therapist(): TherapistProfile
    {
        $user = User::factory()->create(array_merge(['role' => 'therapist', 'name' => 'Grace Namuli'], User::consentAttributes()));

        return TherapistProfile::create(['user_id' => $user->id, 'license_number' => 'UPC-TEST-1', 'license_state' => 'UG', 'credentials_verified' => true]);
    }

    /** Saves the page for screenshots when MW_SAVE_HTML is set (a folder). */
    private function keep(string $name, string $html): void
    {
        if ($dir = getenv('MW_SAVE_HTML')) {
            file_put_contents(rtrim($dir, '/').'/'.$name.'.html', $html);
        }
    }

    public function test_lobby_shows_the_calm_waiting_page(): void
    {
        [$user, $profile] = $this->client();
        $session = TherapySession::create([
            'client_profile_id' => $profile->id,
            'status' => 'routing',
            'routed_at' => now(),
            'entered_lobby_at' => now()->subSeconds(75),
        ]);

        $res = $this->actingAs($user)->get(route('sessions.lobby', $session));

        $res->assertOk()
            ->assertSee("Take a breath. You're in the right place.", false)
            ->assertSee('Checking your connection')
            ->assertSee('Get ready')
            ->assertSee('data-mwcalm-leaves', false)
            ->assertSee('Browse therapists instead');
        $this->assertStringContainsString('var waited = 75', $res->getContent());
        $this->keep('lobby', $res->getContent());
    }

    public function test_lobby_status_moves_on_with_the_therapists_name(): void
    {
        [$user, $profile] = $this->client();
        $session = TherapySession::create([
            'client_profile_id' => $profile->id,
            'status' => 'routing',
            'routed_at' => now(),
        ]);

        $this->actingAs($user)->getJson(route('sessions.status', $session))
            ->assertOk()->assertJson(['status' => 'routing', 'redirect' => null, 'therapist' => null]);

        $session->update(['status' => 'scheduled', 'therapist_profile_id' => $this->therapist()->id, 'scheduled_at' => now()]);
        $this->actingAs($user)->getJson(route('sessions.status', $session))
            ->assertOk()->assertJson([
                'status' => 'scheduled',
                'redirect' => route('sessions.connected', $session),
                'therapist' => 'Grace Namuli',
            ]);

        // Cancelled by staff while waiting: the lobby no longer waits forever.
        $session->update(['status' => 'cancelled']);
        $this->actingAs($user)->getJson(route('sessions.status', $session))
            ->assertOk()->assertJson(['status' => 'cancelled', 'redirect' => route('sessions.history', $session)]);
    }

    public function test_video_waiting_room_has_the_new_parts_and_keeps_the_camera_check(): void
    {
        [$user, $profile] = $this->client();
        $session = TherapySession::create([
            'client_profile_id' => $profile->id,
            'therapist_profile_id' => $this->therapist()->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addMinutes(12),
            'duration_minutes' => 50,
        ]);

        $res = $this->actingAs($user)->get(route('sessions.room', $session));

        $res->assertOk()
            ->assertSee('Video session, 50 minutes')
            ->assertSee('Check camera and mic')
            ->assertSee('mw-chk-net', false)
            ->assertSee('Join with audio only')
            ->assertSee('What would you like to talk about today?')
            ->assertSee('Your screen stays on while you wait')
            ->assertSee('Grace Namuli is ready for you', false);
        $this->assertMatchesRegularExpression('/data-starts-in="(7\d\d|6\d\d)"/', $res->getContent());
        $this->keep('room-client', $res->getContent());

        // The therapist gets the same layout, without the leaves and breathing cue.
        $therapistUser = $session->therapistProfile->user;
        $res = $this->actingAs($therapistUser)->get(route('sessions.room', $session));
        $res->assertOk()->assertSee('Ready when you are')->assertDontSee('class="mwcalm-leaves"', false);
        $this->keep('room-therapist', $res->getContent());
    }

    public function test_app_room_answer_has_the_start_time_and_length(): void
    {
        [$user, $profile] = $this->client();
        $session = TherapySession::create([
            'client_profile_id' => $profile->id,
            'therapist_profile_id' => $this->therapist()->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addMinutes(30),
            'duration_minutes' => 50,
        ]);

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/sessions/'.$session->id.'/room');

        $res->assertOk()->assertJson(['state' => 'too_early', 'duration_minutes' => 50, 'therapist_name' => 'Grace Namuli']);
        $this->assertEqualsWithDelta(1800, $res->json('starts_in_seconds'), 5);
        $this->assertNotNull($res->json('starts_at'));
    }
}
