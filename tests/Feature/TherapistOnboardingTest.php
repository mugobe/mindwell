<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\TherapistApplication;
use App\Models\User;
use App\Notifications\MindwellNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Therapist onboarding (2026-11-03): sign-up → six steps → submit → admin review.
 * Needs MySQL/MariaDB like the other feature tests.
 */
class TherapistOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(array_merge(['role' => 'admin', 'name' => 'Joan Admin'], User::consentAttributes(includeEmergency: false)));
    }

    private function signUp(): User
    {
        $this->post('/register/therapist', [
            'name' => 'Grace Namutebi',
            'email' => 'grace@example.com',
            'phone' => '0772123456',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'accept_terms' => '1',
            'accept_privacy' => '1',
        ])->assertRedirect(route('apply.step', 'licence'));

        return User::where('email', 'grace@example.com')->firstOrFail();
    }

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    private function fillEverything(): void
    {
        $this->post(route('apply.save', 'licence'), [
            'profession' => 'counselling_psychologist',
            'regulator' => 'ahpc',
            'licence_number' => 'AHPC-1234',
            'licence_issued' => now()->subYears(2)->toDateString(),
            'licence_expiry' => now()->addYear()->toDateString(),
            'doc_licence' => $this->pdf('licence.pdf'),
            'go' => 'next',
        ])->assertRedirect(route('apply.step', 'training'));

        $this->post(route('apply.save', 'training'), [
            'qualification_level' => 'masters',
            'qualification_title' => 'MSc Counselling Psychology',
            'institution' => 'Makerere University',
            'qualification_year' => 2018,
            'years_practice' => 6,
            'supervision' => 'yes',
            'supervisor_name' => 'Dr. A',
            'specialties' => ['Anxiety', 'Depression'],
            'approaches' => ['Cognitive behavioural therapy (CBT)'],
            'languages' => ['English', 'Luganda'],
            'age_groups' => ['adults'],
            'session_types' => ['video', 'in_person'],
            'doc_cv' => $this->pdf('cv.pdf'),
            'go' => 'next',
        ])->assertRedirect(route('apply.step', 'fitness'));

        $decl = [];
        foreach (array_keys(config('onboarding.declarations')) as $k) {
            $decl[$k] = ['answer' => 'no'];
        }
        $decl['disciplinary'] = ['answer' => 'yes', 'details' => 'Written warning in 2019, resolved.'];

        $this->post(route('apply.save', 'fitness'), [
            'declarations' => $decl,
            'insurance' => 'no',
            'references' => [
                ['name' => 'Ref One', 'relationship' => 'Supervisor', 'email' => 'one@example.com'],
                ['name' => 'Ref Two', 'relationship' => 'Manager', 'phone' => '0700000000'],
            ],
            'go' => 'next',
        ])->assertRedirect(route('apply.step', 'profile'));

        $this->post(route('apply.save', 'profile'), [
            'professional_title' => 'Counselling Psychologist',
            'bio' => str_repeat('I help adults with anxiety and low mood using CBT. ', 3),
            'fee' => 15000,
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
            'go' => 'next',
        ])->assertRedirect(route('apply.step', 'consents'));

        $this->post(route('apply.save', 'consents'), [
            'agree' => ['code_of_conduct' => '1', 'confidentiality' => '1', 'data_protection' => '1'],
            'go' => 'next',
        ])->assertRedirect(route('apply.step', 'review'));
    }

    public function test_sign_up_creates_a_draft_application(): void
    {
        $user = $this->signUp();

        $this->assertSame('therapist', $user->role);
        $this->assertFalse($user->therapistProfile->credentials_verified);
        $this->assertSame(TherapistApplication::DRAFT, $user->therapistApplication->status);

        $this->get(route('dashboard'))->assertOk()->assertSee('Finish your application');
        $this->get(route('apply.step', 'licence'))->assertOk()->assertSee('Your licence and registration');
        // Not approved yet: the therapist portal stays shut.
        $this->get(route('therapist.calendar'))->assertForbidden();
    }

    public function test_full_application_can_be_submitted_and_admins_are_told(): void
    {
        $admin = $this->admin();
        $user = $this->signUp();
        $this->fillEverything();

        $app = $user->fresh()->therapistApplication->load('documents');
        $this->assertSame([], $app->missing());
        // Documents went to the PRIVATE disk; the photo to the public one.
        Storage::disk('local')->assertExists($app->document('licence')->path);
        Storage::disk('local')->assertExists($app->document('cv')->path);
        Storage::disk('public')->assertExists($app->answer('profile.photo_path'));
        $this->assertSame(3, ConsentRecord::where('user_id', $user->id)->count());
        $this->assertSame('2026-11', ConsentRecord::where('user_id', $user->id)->first()->version);

        $this->get(route('apply.step', 'review'))->assertOk()->assertSee('Everything is filled in')->assertSee('Written warning in 2019');

        $this->post(route('apply.submit'), ['confirm' => '1'])->assertRedirect(route('apply.status'));

        $app->refresh();
        $this->assertSame(TherapistApplication::SUBMITTED, $app->status);
        $this->assertSame(1, $app->submissions);
        Notification::assertSentTo($admin, MindwellNotice::class, fn ($n) => $n->subject === 'New therapist application');
        Notification::assertSentTo($user, MindwellNotice::class, fn ($n) => $n->subject === 'We have your application');

        // Locked once sent.
        $this->get(route('apply.step', 'licence'))->assertRedirect(route('apply.status'));
        $this->get(route('apply.status'))->assertOk()->assertSee('Your application is with our team');
    }

    public function test_incomplete_or_expired_applications_cannot_be_sent(): void
    {
        $this->signUp();

        $this->post(route('apply.save', 'licence'), ['licence_expiry' => now()->subDay()->toDateString(), 'go' => 'next'])
            ->assertSessionHasErrors('licence_expiry');

        $this->post(route('apply.submit'), ['confirm' => '1'])->assertRedirect(route('apply.step', 'review'));
        $this->assertSame(TherapistApplication::DRAFT, TherapistApplication::first()->status);
        $this->get(route('apply.step', 'review'))->assertSee('Before you can send your application');
    }

    public function test_admin_review_checklist_then_approve_fills_the_profile(): void
    {
        $admin = $this->admin();
        $user = $this->signUp();
        $this->fillEverything();
        $this->post(route('apply.submit'), ['confirm' => '1']);
        $app = $user->fresh()->therapistApplication;

        $this->actingAs($admin);
        $this->get(route('admin.applications.index'))->assertOk()->assertSee('Grace Namutebi');
        $this->get(route('admin.applications.show', $app))->assertOk()->assertSee('AHPC-1234')->assertSee('needs a look');

        // Required checklist items first.
        $this->post(route('admin.applications.decide', $app), ['decision' => 'approve'])->assertSessionHasErrors('decision');
        $this->assertFalse($user->therapistProfile->fresh()->credentials_verified);

        $this->post(route('admin.applications.checklist', $app), ['items' => ['licence_verified', 'licence_in_date', 'cv_reviewed', 'declarations_reviewed']])
            ->assertRedirect(route('admin.applications.show', $app));
        $this->assertTrue($app->fresh()->checklistDone('licence_verified'));
        $this->assertSame('Joan Admin', $app->fresh()->checklist['licence_verified']['by']);

        $this->post(route('admin.applications.decide', $app), ['decision' => 'approve'])->assertRedirect(route('admin.applications.show', $app));

        $profile = $user->therapistProfile->fresh();
        $this->assertTrue($profile->credentials_verified);
        $this->assertSame('AHPC-1234', $profile->license_number);
        $this->assertSame('Allied Health Professionals Council (AHPC)', $profile->license_state);
        $this->assertSame('Counselling Psychologist', $profile->professional_title);
        $this->assertSame(['Anxiety', 'Depression'], $profile->specialties);
        $this->assertSame('English, Luganda', $profile->languages);
        $this->assertTrue((bool) $profile->offers_in_person);
        $this->assertStringContainsString('MSc Counselling Psychology', $profile->qualifications);
        $this->assertSame(TherapistApplication::APPROVED, $app->fresh()->status);
        Notification::assertSentTo($user, MindwellNotice::class, fn ($n) => $n->subject === 'Welcome to Mindwell');
    }

    public function test_changes_requested_then_resubmitted_and_decline_needs_a_note(): void
    {
        $admin = $this->admin();
        $user = $this->signUp();
        $this->fillEverything();
        $this->post(route('apply.submit'), ['confirm' => '1']);
        $app = $user->fresh()->therapistApplication;

        $this->actingAs($admin)->post(route('admin.applications.decide', $app), ['decision' => 'changes'])->assertSessionHasErrors('note');
        $this->post(route('admin.applications.decide', $app), ['decision' => 'changes', 'note' => 'Please upload a clearer licence.'])->assertRedirect();
        $this->assertSame(TherapistApplication::CHANGES, $app->fresh()->status);
        Notification::assertSentTo($user, MindwellNotice::class, fn ($n) => $n->subject === 'About your Mindwell application');

        $this->actingAs($user);
        $this->get(route('apply.step', 'licence'))->assertOk()->assertSee('Please upload a clearer licence.');
        $this->post(route('apply.save', 'licence'), ['doc_licence' => $this->pdf('better.pdf'), 'go' => 'next']);
        $this->post(route('apply.submit'), ['confirm' => '1'])->assertRedirect(route('apply.status'));
        $this->assertSame(2, $app->fresh()->submissions);
        Notification::assertSentTo($admin, MindwellNotice::class, fn ($n) => $n->subject === 'A therapist application was updated');

        $this->actingAs($admin)->post(route('admin.applications.decide', $app), ['decision' => 'decline', 'note' => 'Licence could not be confirmed.']);
        $this->assertSame(TherapistApplication::DECLINED, $app->fresh()->status);
        $this->actingAs($user)->get(route('apply.status'))->assertSee('Licence could not be confirmed.');
    }

    public function test_documents_are_admin_only(): void
    {
        $admin = $this->admin();
        $user = $this->signUp();
        $this->fillEverything();
        $app = $user->fresh()->therapistApplication->load('documents');
        $doc = $app->document('licence');

        $this->get(route('admin.applications.document', [$app, $doc]))->assertForbidden();

        $client = User::factory()->create(array_merge(['role' => 'client'], User::consentAttributes()));
        $this->actingAs($client)->get(route('admin.applications.document', [$app, $doc]))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.applications.document', [$app, $doc]))->assertOk()->assertHeader('Cache-Control');
        $this->assertSame([], Storage::disk('public')->allFiles('therapist-applications'));
    }

    public function test_removing_and_replacing_a_document(): void
    {
        $user = $this->signUp();
        $this->post(route('apply.save', 'licence'), ['doc_licence' => $this->pdf('a.pdf'), 'go' => 'later']);
        $first = TherapistApplication::first()->documents()->first();
        $this->post(route('apply.save', 'licence'), ['doc_licence' => $this->pdf('b.pdf'), 'go' => 'later']);
        $app = TherapistApplication::first()->load('documents');

        $this->assertCount(1, $app->documents);
        $this->assertSame('b.pdf', $app->document('licence')->original_name);
        Storage::disk('local')->assertMissing($first->path);

        $this->delete(route('apply.document.remove', 'licence'))->assertRedirect();
        $this->assertCount(0, $app->fresh()->documents);
    }
}
