# Mindwell — Go-live checklist (Sunday)

Prepared in Phase 0 (2026-10-05). Work through it top to bottom on the server (Coolify).
Never commit `.env` — every secret below lives only in Coolify's environment settings.

## 1. Server & app
- [ ] VPS created, Coolify installed, domain pointed at it (A record), HTTPS certificate issued by Coolify.
- [ ] App deployed from the **private** GitHub repo (PHP 8.3), MySQL database created.
- [ ] `php artisan key:generate` (once — keep the key; changing it logs everyone out and breaks encrypted data).
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache` (re-run after every deploy)
- [ ] Create the first admin account; log in and check the admin portal.

## 2. Production `.env` (in Coolify, not in git)
```
APP_NAME=Mindwell
APP_ENV=production
APP_DEBUG=false                 # NEVER true in production — it shows secrets on error pages
APP_URL=https://<your-domain>
APP_TIMEZONE=Africa/Kampala     # (or wherever it's set in config/app.php)

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=...  DB_PORT=3306  DB_DATABASE=mindwell  DB_USERNAME=...  DB_PASSWORD=...

SESSION_DRIVER=database          # or file — check config/session.php (table is auth_sessions)
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true

MAIL_MAILER=smtp                 # a real provider — NOT "log"
MAIL_HOST=...  MAIL_PORT=587  MAIL_USERNAME=...  MAIL_PASSWORD=...  MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@<your-domain>
MAIL_FROM_NAME=Mindwell

PANDORA_SMS_USERNAME=...  PANDORA_SMS_PASSWORD=...  PANDORA_SMS_SENDER=Mindwell

FLUTTERWAVE_CLIENT_ID=...  FLUTTERWAVE_CLIENT_SECRET=...  FLUTTERWAVE_CURRENCY=UGX
FLUTTERWAVE_TOKEN_URL=...  FLUTTERWAVE_BASE_URL=...   # sandbox until Flutterwave confirms the LIVE url + live keys
FLUTTERWAVE_WEBHOOK_SECRET_HASH=...   # same value as in the Flutterwave dashboard

LIVEKIT_URL=...  LIVEKIT_API_KEY=...  LIVEKIT_API_SECRET=...

MINDWELL_BUSINESS_NAME=...  MINDWELL_BUSINESS_ADDRESS=...  MINDWELL_BUSINESS_PHONE=...
MINDWELL_BUSINESS_EMAIL=...  MINDWELL_BUSINESS_TIN=...

MINDWELL_IN_PERSON_PRICE=20000   # placeholder in-person session price (UGX) — Dr. Ntabade sets the real one
MINDWELL_CRISIS_LINE_NAME=...    # Dr. Ntabade's 24/7 choice — blank hides it
MINDWELL_CRISIS_LINE_NUMBER=...
MINDWELL_LEGAL_IS_DRAFT=true     # set false + bump MINDWELL_LEGAL_VERSION when final text is in
```

## 3. Scheduler (reminders, renewals, check-ins, rating emails)
- [ ] Coolify → Scheduled task, every minute: `php artisan schedule:run`
- [ ] Next day: confirm `mindwell:notify` ran (laravel log / a reminder arrived).

## 4. Payments
- [ ] Flutterwave dashboard → webhook URL `https://<your-domain>/webhooks/flutterwave` + secret hash.
      (The CSRF exception for this URL is already in bootstrap/app.php.)
- [ ] Confirm with Flutterwave support how v4 signs webhooks (roadmap #53).
- [ ] The "simulate payment" button only exists when APP_ENV=local + sandbox — check it's gone.

## 5. Safety & privacy
- [ ] Open `/urgent-help` logged out — numbers show, no login needed.
- [ ] Privacy Policy and Terms pages show the DRAFT banner until the final text arrives.
- [ ] Database backups: Coolify → daily backup of the MySQL database, kept off the server (e.g. S3). Test one restore.
- [ ] Only Collins + Dr. Ntabade have admin accounts; strong passwords.

## 6. Smoke test (soft launch)
- [ ] Sign up as a test client (first/last name, date of birth, tick boxes) → intake → payment (small real amount once live keys exist) → lobby/booking.
- [ ] Admin approves → therapist + client get email/SMS.
- [ ] Video session joins from two devices; leave → rating box.
- [ ] Forgot password email arrives.

## Mobile API v1 (added 2026-10-09)
- Run `php artisan migrate` — `2026_10_09_100000_make_api_tokens_work_with_uuid_users` makes Sanctum's `personal_access_tokens.tokenable_id` hold UUIDs (Mindwell user ids are UUIDs).
- **Do not** run `php artisan install:api` or `php artisan vendor:publish --tag=sanctum-migrations` later: they add a second "create personal_access_tokens" migration that fails with "table already exists". The API is already installed (`routes/api.php`, Sanctum).
- The app talks to `https://<your-domain>/api/v1`. Production must be HTTPS.
- App sign-ins expire after 90 days and are revoked when the password is reset or changed on the website, or an admin sets a temporary password.
- Postman collection for testing: `docs/Mindwell-API-v1.postman_collection.json`.
