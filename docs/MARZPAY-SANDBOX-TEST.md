# MarzPay: setup and sandbox test (2026-11-02)

MarzPay sits next to Flutterwave, behind one setting:

    PAYMENT_GATEWAY=flutterwave   (default, nothing changes)
    PAYMENT_GATEWAY=marzpay

Each payment remembers which service started it, so switching back and forth never strands a payment that is already waiting.

## 1. Put the files in (on your PC)

1. Copy the files from the zip into `mindwell-app`. The folder layout matches the repo.
2. Run the migration. It adds two columns to `payment_transactions`. **This is needed even if you stay on Flutterwave**, because checkout now writes these columns.

       php artisan migrate

3. Optional: run the tests. They need MySQL or MariaDB, because some older migrations only work on MySQL.

       php artisan test --filter=MarzPay

   All 12 should pass.
4. Commit and push as usual. Do a `git status` first: `.env` must not be in it.

## 2. On the test site (cPanel)

Upload this together with everything else that is still local only (the 2026-11-01 work). Then run these in cPanel → Terminal:

    cd ~/laravel8
    /opt/alt/php83/usr/bin/php artisan migrate --force
    /opt/alt/php83/usr/bin/php artisan optimize

Add these lines to the **server** `.env`, using the keys from MarzPay Dashboard → API Keys. Leave `PAYMENT_GATEWAY` alone for now:

    MARZPAY_API_KEY=...
    MARZPAY_API_SECRET=...
    MARZPAY_SANDBOX=true
    MARZPAY_LOG_PAYLOADS=true
    # only if webhook signing is switched on in the dashboard:
    MARZPAY_WEBHOOK_SECRET=whsec_...

Then run `/opt/alt/php83/usr/bin/php artisan optimize` again.

The callback address is filled in automatically: `https://mindwell.insight360apps.net/webhooks/marzpay`.

## 3. Sandbox checks

Use the same php path for each command.

| Command | What it tells us |
|---|---|
| `php artisan marzpay:check` | Whether the keys work, and which services the account has |
| `php artisan marzpay:check --collect=07XXXXXXXX --wait=120` | Starts a UGX 500 test payment and shows each status change |
| `php artisan marzpay:check --status=<uuid>` | Checks one test payment again later |
| `php artisan marzpay:check --log` | Shows MarzPay's callback, if one arrived, and whether it was signed |

The tool never prints the keys, and it masks phone numbers. Its output can be pasted to Claude as is.

A `--collect` test is **not** a Mindwell payment, so nothing in the database changes. What we want to learn from it:

- Do sandbox payments ever reach `completed` or `failed`, or do they stay `sandbox` or `processing`?
- What does the status answer look like?
- Does a callback arrive, and with which headers?

## 4. Real flow on the test site

1. In the server `.env`, set `PAYMENT_GATEWAY=marzpay`, then run `php artisan optimize`.
2. On the website and in the app, book a session or a plan and pay with Mobile Money.
3. The waiting page and the app keep checking the status.
4. If the sandbox never completes payments, the **Test mode** buttons (staging only, while `MARZPAY_SANDBOX=true`) approve or fail the payment, so the rest of the flow can still be tested.
5. Receipts for sandbox payments are marked as tests.

To go back to Flutterwave: set `PAYMENT_GATEWAY=flutterwave`, then run `php artisan optimize`.

## 5. Going live (after Mindwell's onboarding is approved)

1. Generate **live** API keys and replace the sandbox keys in the server `.env`.
2. Set `MARZPAY_SANDBOX=false` and `MARZPAY_LOG_PAYLOADS=false`. The log holds phone numbers.
3. Switch on webhook signing and set `MARZPAY_WEBHOOK_SECRET`.
4. Run `php artisan optimize`. Then make one small real payment and refund it from the MarzPay dashboard.

Refunds: MarzPay has no refund API. Send the money back from the MarzPay dashboard, then record the refund in Admin → Payments, as before.
