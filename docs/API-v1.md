# Mindwell API v1 (for the Android client app)

Base URL: `http://127.0.0.1:8000/api/v1` locally, `https://<domain>/api/v1` live.
Clients only — therapist/admin logins get `403 {code: "not_client"}`.

## Auth
- `POST auth/register` · `POST auth/login` → `{ token, user }`. Send `Authorization: Bearer <token>` on every other call.
- Tokens last 90 days; one per device (`device_name`). `POST auth/logout` ends this device.
- `POST auth/forgot-password {email}` emails the website reset link.

## Errors
| HTTP | Body | Meaning |
|---|---|---|
| 401 | `{message}` | Token missing/expired → go to login |
| 403 | `{code: "password_change_required"}` | Show "choose a new password" (`PUT me/password`) |
| 403 | `{code: "consent_required"}` | Show updated Terms (`POST consent`) |
| 403 | `{code: "not_client" \| "suspended"}` | Log out with the message |
| 409 | `{message, code?}` | Not allowed right now (e.g. `intake_done`, `slot_taken`, `not_yet`) |
| 422 | `{message, errors: {field: [..]}}` | Validation — show next to the field |
| 429 | | Too many tries; wait a minute |

## Endpoints
- **Public:** `GET config` (prices, crisis contacts, Terms/Privacy links, moods, genders, languages, networks).
- **Me:** `GET me` · `PUT me` (My details) · `PUT me/notifications` · `PUT me/password` · `POST consent`.
- **Home:** `GET dashboard` — next session (+ `join.state`), in-progress session, mood today, check-in due, rate prompt, About-you prompt, unread messages, coverage.
- **Intake:** `GET intake` (About-you sections + 16 questions) · `POST intake` (`phq_1..9`, `gad_1..7` 0–3, optional `about_*`, `skipped[]`) → `outcome: crisis | lobby | checkout` + `session`.
  - `crisis` → show the crisis screen with `config.crisis.contacts`.
- **About you:** `GET about-you` · `PUT about-you` · `POST about-you/not-now`.
- **Therapists:** `GET therapists` · `GET therapists/{id}` (profile + `days[].times[]`) · `POST therapists/{id}/book {scheduled_at, session_mode}` → session (`next_step` checkout or awaiting_approval).
- **Sessions:** `GET sessions` (`upcoming`, `past`) · `GET sessions/{id}` · `GET sessions/{id}/lobby` (poll ~5s while `next_step = lobby`) · `POST sessions/{id}/stop-waiting` · `GET|POST sessions/{id}/change` · `DELETE change-requests/{id}`.
- **Video:** `GET sessions/{id}/room` (`state: too_early|open|ended|closed`, `therapist_in_room`, `notes: {body, shared}` = the client's own notepad) · `POST sessions/{id}/room/token` → `{url, token}` for LiveKit · `POST …/room/presence` every 15s (`leaving=1` on exit) · `POST …/room/notes {body, shared}`.
- **Rating:** `POST sessions/{id}/rating {rating 1–5, comment}` or `{skip: true}`.
- **Paying:** `GET sessions/{id}/checkout?voucher=` · `POST sessions/{id}/pay {network, phone_number, voucher?}` → `{state, message, payment}`.
  - `state = pending` → show the message ("approve on your phone") and poll `GET payments/{id}` every ~4s until `succeeded` / `failed` (stop after ~10 min).
  - `succeeded` → follow `session.next_step`.
  - Local sandbox only: `POST payments/{id}/simulate {outcome: approve|fail}` (`can_simulate` tells you).
- **Plans:** `GET plans` · `GET plans/{id}/checkout?voucher=` · `POST plans/{id}/subscribe` · `GET|POST subscription/renew` (same payment states as above).
- **Employer:** `GET employer-benefit` · `POST employer-benefit {seat_code}`.
- **Payments:** `GET payments?page=` · `GET payments/{id}/receipt` (PDF, Bearer) · `GET payments/{id}/receipt-link` → `{url, expires_at}` — a 10-minute signed link (web route `receipts.signed`, `/r/{id}`) the app opens in the phone's browser.
- **Messages:** `GET messages` · `POST messages/with/{therapistId}` (opens chat, returns last 200; an existing conversation opens read-only with `can_send: false` when there's no session together any more) · `POST conversations/{id}/messages {body}` · `GET conversations/{id}/poll?since=ISO` (poll ~4s while open) · `GET messages/unread`.
- **Journal:** `GET journal?page=` · `POST journal {mood, body?, shared_with_therapist?}` · `PATCH journal/{id}` · `DELETE journal/{id}`.
- **Progress:** `GET progress` (words + 0–1 levels, never raw scores) · `GET check-in` · `POST check-in` → `outcome: crisis | saved` · `PUT progress/share-mood {share}`.

## Shared rules
The API calls the website's own code for intake, booking (row lock + open-slot re-check), payments (`App\Services\Checkout` + `FlutterwavePaymentController::finalize`), check-ins and employer codes, so the app and the website behave the same.
