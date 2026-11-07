# Staff refresh + therapist onboarding (2026-11-03)

Built on Collins's `mindwell-app-current.tar.gz` of 3 Nov, which matches the code from every earlier package. Copy the zip's files over `mindwell-app`; the folders are the same as the repo.

## What's in it

### Staff look (admins and therapists)
- **Brand colours:** navy, orange and green from the logo, the Plus Jakarta Sans font, and status chips in the same colours as the client side (`layouts/_staff-style`).
- **Sidebar colour:** navy (default) or the logo's deep green, set once for the whole clinic in **Admin → Setup → Appearance**.
- **Test-site ribbon:** a strip at the top on staging and local only, never live. It shows which payment service is active and whether it's in sandbox.
- **Admin overview:**
  - a greeting that ranks the day;
  - number tiles with six-week trend lines;
  - "Needs your attention" sorted most urgent first, now including therapist applications;
  - a payment service card showing MarzPay or Flutterwave, sandbox or live, and when the last callback arrived.
- **Therapist dashboard:**
  - a navy "next session" card;
  - countdown chips, with an orange Join button only when the session is close;
  - the client's last mood face, from journal moods they chose to share;
  - a "mood dropped" alert after two low shared check-ins in a row;
  - on phones: the next session first, today's list, then the tiles, plus bottom tabs.
- **Therapist sessions page:** no more "Join video" days ahead. Other days show a date chip; earlier the same day shows "Open room".
- **Payments:**
  - the clipped filter text is fixed;
  - a new **All services** filter: MarzPay, Flutterwave, Voucher, Cash;
  - service and Test chips;
  - plain statuses: "Waiting on phone", "Not completed";
  - a copy button for the reference;
  - MarzPay fees shown on the first tile, and a "Not completed" tile;
  - chart colours in navy and orange.
- **Calendar:**
  - blocks show the time, the client's short name, and video or in person;
  - in the admin "All therapists" view, each therapist has their own colour and appears in the legend;
  - "Awaiting approval" is amber with a dashed border; past sessions fade;
  - an orange "now" line.

### Therapist onboarding
- **`/register/therapist`** now creates only the account (name, email, mobile, password, terms). It then opens the application.
- **Six steps at `/apply/...`:**
  1. **Licence:** profession, who registers you, number, issue and expiry dates; upload the licence (required) and ID (optional).
  2. **Training:** qualification, institution, year, other training, years in practice, supervision, specialties, approaches, languages, age groups, video or in person; upload the CV (required).
  3. **Fitness to practise:** six yes/no declarations, with details required for every "yes"; indemnity insurance, with an optional certificate; two professional references.
  4. **Profile:** photo, professional title, bio (at least 80 characters), usual fee.
  5. **Consents:** code of conduct, confidentiality and data protection. Each agreement is saved with the user, time, IP address and version.
  6. **Review and send:** lists anything missing, then asks the applicant to confirm the information is true.
- **Saving:** every step saves as you go. "Save and finish later" works at any point.
- **Status:** after sending, the applicant sees a tracker (Account → Sent → Under review → Approved) on their dashboard and at `/apply/status`.
- **Documents:** stored in `storage/app/private/therapist-applications/…`, never in `public/`. Only admins can open them, and each opening is written to the audit log.
- **Admin → People → Applications:**
  - tabs: To review, Changes requested, Not submitted yet, Approved, Not approved;
  - each application shows all answers and documents, with "yes" answers highlighted at the top;
  - a verification checklist; the four *required* items must be ticked before approving;
  - three decisions:
    - **Approve:** copies everything into the therapist profile and opens their portal;
    - **Ask for changes:** a note is required; the applicant edits and sends it again;
    - **Decline:** a note is required.
  - The applicant is emailed after each decision, and admins are emailed when an application is sent.
- **Therapists page:** now only shows sign-ups from before this change, with a link to Applications.
- **Questions and wording:** all lists and texts are in `config/onboarding.php`: professions, regulators, specialties, approaches, languages, declarations, consent wording, checklist and documents.

## Upload steps
1. Copy the files into `mindwell-app` (local), run `php artisan migrate`, and test locally. The migration adds 3 tables: `therapist_applications`, `therapist_application_documents` and `consent_records`.
2. Run the tests with MySQL/MariaDB: `php artisan test --filter="TherapistOnboarding|MarzPay"`. 22 tests should pass. The old `ExampleTest` fails as before, because `/` redirects.
3. Upload to the server. Then run:
   ```
   /opt/alt/php83/usr/bin/php artisan migrate --force
   /opt/alt/php83/usr/bin/php artisan optimize
   ```
4. Make sure `storage/app/private` exists and the web server can write to it. Laravel creates it on the first upload if `storage/app` is writable.
5. Optional: in Admin → Setup → Appearance, choose navy or green.

## For Dr. Ntabade to confirm
- **Regulators:** the list in `config/onboarding.php` → `regulators` is a draft.
- **Consents:** the wording of the three consents is a draft. If the text changes, raise the `version` so new agreements are recorded against the new text.
- **Declarations and checklist:** the declaration questions, and which checklist items are required.
- **Mood faces:** admins don't see them yet. Therapists only see the moods their clients shared.
