<?php

namespace Tests\Feature;

use App\Models\AvailabilitySlot;
use App\Models\ClientProfile;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 2026-11-07: (1) the first assessment is required before any booking, and the client is
 * brought back to the time they picked; (2) the booking page is a calendar that looks
 * booking_days_ahead days ahead.
 */
class BookingCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:00:00')); // a Monday morning
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function client(bool $assessed = false): array
    {
        $user = User::factory()->create(array_merge(['role' => 'client', 'name' => 'Mugerwa Faizal'], User::consentAttributes()));
        $profile = ClientProfile::create(['user_id' => $user->id]);
        if ($assessed) {
            $profile->intakeAssessments()->create(['responses' => [], 'phq9_score' => 3, 'gad7_score' => 2, 'risk_level' => 'low']);
        }

        return [$user, $profile];
    }

    private function therapist(bool $inPerson = true): TherapistProfile
    {
        $user = User::factory()->create(array_merge(['role' => 'therapist', 'name' => 'Alexandre Ndawula'], User::consentAttributes()));
        $t = TherapistProfile::create(['user_id' => $user->id, 'license_number' => 'UPC-1', 'license_state' => 'UG', 'credentials_verified' => true]);
        $t->forceFill(['offers_in_person' => $inPerson])->save();
        foreach (['monday', 'wednesday', 'saturday'] as $day) {
            AvailabilitySlot::create(['therapist_id' => $t->id, 'day_of_week' => $day, 'start_time' => '10:00', 'end_time' => '15:00', 'is_recurring' => true]);
        }

        return $t;
    }

    private function answers(): array
    {
        $a = [];
        foreach (range(1, 9) as $i) { $a["phq_{$i}"] = 0; }
        foreach (range(1, 7) as $i) { $a["gad_{$i}"] = 1; }

        return $a;
    }

    /** Saves the page for screenshots when MW_SAVE_HTML is set (a folder). */
    private function keep(string $name, string $html): void
    {
        if ($dir = getenv('MW_SAVE_HTML')) {
            file_put_contents(rtrim($dir, '/').'/'.$name.'.html', $html);
        }
    }

    public function test_booking_page_is_a_calendar_looking_four_weeks_ahead(): void
    {
        [$user] = $this->client(assessed: true);
        $t = $this->therapist();

        $res = $this->actingAs($user)->get(route('therapists.book', $t));

        $res->assertOk()
            ->assertSee('Pick a day')
            ->assertSee('November 2026')
            ->assertSee('December 2026')
            ->assertSee('data-day="2026-12-05"', false)   // a Saturday 26 days ahead
            ->assertSee('Morning')
            ->assertSee('How would you like to meet?')
            ->assertDontSee('One step before you book');
        $this->keep('book-calendar', $res->getContent());
    }

    public function test_no_assessment_means_assessment_first_then_back_to_the_same_time(): void
    {
        [$user, $profile] = $this->client();
        $t = $this->therapist();
        $at = '2026-11-14 11:30:00'; // Saturday

        $page = $this->actingAs($user)->get(route('therapists.book', $t));
        $page->assertOk()->assertSee('One step before you book')->assertSee('Continue to the assessment');
        $this->keep('book-needs-assessment', $page->getContent());

        $this->actingAs($user)->post(route('therapists.book.store', $t), ['scheduled_at' => $at, 'session_mode' => 'in_person'])
            ->assertRedirect(route('intake.show'))
            ->assertSessionHas('mw_book_after_intake', ['therapist' => $t->id, 'at' => $at, 'mode' => 'in_person']);
        $this->assertSame(0, TherapySession::count());

        $this->actingAs($user)->get(route('intake.show'))->assertOk()->assertSee('A few questions before your first session');

        $back = route('therapists.book', $t).'?'.http_build_query(['at' => $at, 'mode' => 'in_person']);
        $this->actingAs($user)->post(route('intake.store'), $this->answers())->assertRedirect($back);

        // Assessment saved, but no "match me now" request was started.
        $this->assertTrue($profile->fresh()->hasAssessment());
        $this->assertSame(0, TherapySession::count());

        // Back on the page, the time and the kind of session are still chosen.
        $this->actingAs($user)->get($back)->assertOk()
            ->assertSee('value="'.$at.'" required', false)
            ->assertSeeInOrder(['value="in_person"', 'checked'], false)
            ->assertDontSee('One step before you book');

        // Now the booking goes through.
        $this->actingAs($user)->post(route('therapists.book.store', $t), ['scheduled_at' => $at, 'session_mode' => 'in_person'])
            ->assertRedirect();
        $this->assertSame(1, TherapySession::where('therapist_profile_id', $t->id)->count());
    }

    public function test_the_usual_assessment_still_starts_a_match_me_now_request(): void
    {
        [$user] = $this->client();

        $this->actingAs($user)->post(route('intake.store'), $this->answers())->assertRedirect();

        $this->assertSame(1, TherapySession::whereNull('therapist_profile_id')->count());
    }

    public function test_app_is_told_to_do_the_assessment_first(): void
    {
        [$user] = $this->client();
        $t = $this->therapist(inPerson: false);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/therapists/'.$t->id)
            ->assertOk()->assertJson(['assessment_required' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/therapists/'.$t->id.'/book', ['scheduled_at' => '2026-11-14T11:30:00+03:00'])
            ->assertStatus(409)->assertJson(['code' => 'assessment_required']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/intake', $this->answers() + ['for_booking' => true])
            ->assertCreated()->assertJson(['outcome' => 'assessed', 'session' => null]);
        $this->assertSame(0, TherapySession::count());

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/therapists/'.$t->id)
            ->assertOk()->assertJson(['assessment_required' => false]);
    }
}
