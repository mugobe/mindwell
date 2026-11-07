<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\JournalEntry;
use App\Models\User;
use App\Notifications\MindwellNotice;
use App\Services\JournalReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 2026-11-07: one mood per day, writing unlimited, the journal grouped by day, and
 * optional morning/evening reminders.
 */
class JournalDaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-11-07 08:46:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function client(array $attrs = []): array
    {
        $user = User::factory()->create(array_merge(['role' => 'client', 'name' => 'Mugerwa Faizal'], User::consentAttributes(), $attrs));

        return [$user, ClientProfile::create(['user_id' => $user->id])];
    }

    private function keep(string $name, string $html): void
    {
        if ($dir = getenv('MW_SAVE_HTML')) {
            file_put_contents(rtrim($dir, '/').'/'.$name.'.html', $html);
        }
    }

    public function test_a_quick_second_face_corrects_today_instead_of_adding_one(): void
    {
        [$user, $profile] = $this->client();

        $this->actingAs($user)->post(route('journal.store'), ['mood' => 2])->assertSessionHas('status', 'Thank you for checking in today.');
        Carbon::setTestNow(now()->addMinutes(1));
        $this->actingAs($user)->post(route('journal.store'), ['mood' => 5])->assertSessionHas('status', "Today's mood is updated.");
        Carbon::setTestNow(now()->addMinutes(7));
        $this->actingAs($user)->post(route('journal.store'), ['mood' => 5])->assertSessionHas('status', "That's already today's mood.");

        $this->assertSame(1, JournalEntry::where('client_profile_id', $profile->id)->count());
        $this->assertSame(5, JournalEntry::first()->mood);
    }

    public function test_a_later_change_is_kept_but_the_day_counts_once(): void
    {
        [$user, $profile] = $this->client();

        $this->actingAs($user)->post(route('journal.store'), ['mood' => 2]);
        Carbon::setTestNow(Carbon::parse('2026-11-07 13:10:00'));
        $this->actingAs($user)->post(route('journal.store'), ['mood' => 5]);

        $this->assertSame(2, JournalEntry::count());
        $this->assertCount(1, JournalEntry::dailyMoodsFor($profile->id, 14));
        $this->assertSame(5, JournalEntry::dailyMoodsFor($profile->id, 14)->first()['mood']);

        $this->actingAs($user)->get(route('journal.index'))->assertOk()
            ->assertSee('Today')
            ->assertSee('Feeling great')
            ->assertSeeInOrder(['Low 8:46 AM', 'Great 1:10 PM'])
            ->assertSee('No words, just the mood.')
            ->assertSee('1</div><div class="l">day checked in', false);
    }

    public function test_writing_has_no_limit_and_uses_todays_mood(): void
    {
        [$user, $profile] = $this->client();

        $this->actingAs($user)->post(route('journal.store'), ['mood' => 4]);
        $this->actingAs($user)->post(route('journal.store'), ['body' => 'Slept better.']);
        $this->actingAs($user)->post(route('journal.store'), ['body' => 'Talked to my sister.', 'shared_with_therapist' => 1])
            ->assertSessionHas('status', 'Saved to your journal.');

        $written = JournalEntry::whereNotNull('body')->orderBy('created_at')->get();
        $this->assertCount(2, $written);
        $this->assertSame([4, 4], $written->pluck('mood')->all());
        $this->assertTrue($written->last()->shared_with_therapist);

        $res = $this->actingAs($user)->get(route('journal.index'))->assertOk()
            ->assertSee('Slept better.')->assertSee('Talked to my sister.')
            ->assertSee("Today you're feeling good", false)
            ->assertDontSee('No words, just the mood.');
        $this->keep('journal', $res->getContent());

        $this->actingAs($user)->post(route('journal.store'), [])->assertSessionHasErrors('mood');
    }

    public function test_the_list_pages_by_day(): void
    {
        [$user, $profile] = $this->client();
        foreach (range(0, 11) as $i) {
            $at = Carbon::parse('2026-11-07 09:00')->subDays($i);
            $e = JournalEntry::create(['client_profile_id' => $profile->id, 'kind' => 'daily', 'mood' => 3]);
            $e->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }

        $this->actingAs($user)->get(route('journal.index'))->assertOk()->assertSee('Page 1 of 2');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/journal?grouped=1')->assertOk()
            ->assertJsonCount(10, 'days')->assertJson(['has_more' => true, 'today' => ['mood' => 3]]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/journal?grouped=1&page=2')->assertOk()
            ->assertJsonCount(2, 'days')->assertJson(['has_more' => false]);
    }

    public function test_app_store_says_what_happened_and_old_list_still_works(): void
    {
        [$user] = $this->client();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/journal', ['mood' => 3])->assertCreated()->assertJson(['action' => 'mood_saved']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/journal', ['mood' => 4])->assertCreated()->assertJson(['action' => 'mood_updated']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/journal', ['body' => 'Hello'])->assertCreated()->assertJson(['action' => 'written', 'entry' => ['mood' => 4]]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/journal')->assertOk()->assertJsonCount(2, 'entries');
    }

    public function test_reminders_are_optional_once_a_day_and_skipped_when_done(): void
    {
        Notification::fake();
        Cache::flush();
        [$asked] = $this->client(['journal_reminders' => 'morning']);
        [$done, $doneProfile] = $this->client(['journal_reminders' => 'both']);
        [$off] = $this->client();
        JournalEntry::create(['client_profile_id' => $doneProfile->id, 'kind' => 'daily', 'mood' => 4]);

        $reminders = app(JournalReminders::class);
        $this->assertSame(1, $reminders->run(Carbon::parse('2026-11-07 08:05')));
        $this->assertSame(0, $reminders->run(Carbon::parse('2026-11-07 08:10')));
        $this->assertSame(0, $reminders->run(Carbon::parse('2026-11-07 12:00')));
        Notification::assertSentTo($asked, MindwellNotice::class);
        Notification::assertNotSentTo($done, MindwellNotice::class);
        Notification::assertNotSentTo($off, MindwellNotice::class);

        // Evening: only "both" asked, and they haven't written since 3 PM.
        $this->assertSame(1, $reminders->run(Carbon::parse('2026-11-07 19:35')));
        Notification::assertSentTo($done, MindwellNotice::class);
    }

    public function test_reminder_setting_on_web_and_app(): void
    {
        [$user] = $this->client();

        $this->actingAs($user)->put(route('settings.notifications.update'), ['notify_email' => 1, 'journal_reminders' => 'evening'])->assertRedirect();
        $this->assertSame('evening', $user->fresh()->journal_reminders);

        // An older app version doesn't send the field: the choice stays.
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/notifications', ['notify_email' => true])->assertOk()
            ->assertJson(['user' => ['journal_reminders' => 'evening']]);

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/notifications', ['notify_email' => true, 'journal_reminders' => 'off'])->assertOk()
            ->assertJson(['user' => ['journal_reminders' => 'off']]);
        $this->assertNull($user->fresh()->journal_reminders);
    }
}
