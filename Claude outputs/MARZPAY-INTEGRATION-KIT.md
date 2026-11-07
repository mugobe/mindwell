# MarzPay integration kit: customer deposits for Insight360

Written 2026-11-04 from the Mindwell project. There, MarzPay Mobile Money collections were built, tested in sandbox, and confirmed with **real money** (UGX 500 via Airtel). Hand this to the Claude working on Insight360. The code is Laravel, but the rules apply to any stack.

Insight360 use case: a **customer deposits money** into their Insight360 account from MTN or Airtel Mobile Money. Insight360 credits the account **only after MarzPay confirms the money arrived**.

---

## 1. Account setup (MarzPay dashboard)

1. Create a business account at `https://wallet.wearemarz.com` and verify the business documents. New accounts start in **sandbox**; MarzPay switches the account to **LIVE** after verification.
2. **Subscribe to each service in the dashboard Services / Marketplace.** This is the one that bit us.
   - Going live did NOT subscribe anything: `GET /services` showed `subscribed_services: 0`, and every collection was refused.
   - For deposits you need **MTN Collection** and **Airtel Collection**.
   - For withdrawals later you'll also need **MTN Disbursement** and **Airtel Disbursement**.
3. **API Keys → Generate.** The secret is shown once. Store it only in `.env`.
4. **Webhook signing:** turn it on and copy the `whsec_…` secret into `.env`.
5. **IP whitelist:** only needed for `/balance`, `/send-money`, bank transfers, bills and airtime. Collections don't need it.
6. Make sure the live account belongs to the **business**, not a person's personal wallet. Deposits are customer money.

## 2. Environment

```
MARZPAY_API_BASE=https://wallet.wearemarz.com/api/v1
MARZPAY_API_KEY=
MARZPAY_API_SECRET=
MARZPAY_WEBHOOK_SECRET=        # whsec_..., from the dashboard
MARZPAY_CALLBACK_URL=          # https://your-domain/webhooks/marzpay (must be public HTTPS)
MARZPAY_SANDBOX=true           # false once the account is live
```

Auth on every request: `Authorization: Basic base64(api_key:api_secret)`, `Accept: application/json`.

## 3. The API you actually need

**Start a deposit:** `POST /collect-money`

```json
{
  "amount": 50000,
  "phone_number": "+256772123456",
  "reference": "a-new-uuid-v4-every-attempt",
  "country": "UG",
  "description": "Insight360 deposit",
  "callback_url": "https://your-domain/webhooks/marzpay",
  "metadata": [ { "deposit_id": "..." } ]
}
```

| Field | Rules |
|---|---|
| `amount` | Whole UGX, from 500 to 10,000,000 |
| `phone_number` | E.164 format (`+2567XXXXXXXX`). The network (MTN or Airtel) is detected from the number |
| `reference` | A **new UUID v4 for every attempt**; never reuse one |
| `metadata` | At most 10 items, each with one key. Use your own ids only, no personal data |

It returns HTTP 201 with `data.transaction.uuid` (MarzPay's id, so store it) and `status: "processing"`. This only means the PIN prompt was sent. **Never credit anything on this response.**

**Check status:** `GET /collect-money/{uuid}`
- Falls back to `GET /transactions/{uuid}`, which returns a callback-shaped body.
- Read `transaction.status`, `transaction.amount.raw` and `transaction.reference`.

**Status values:** `pending`, `processing`, `completed`, `failed`, `cancelled`. Only `completed` means the money arrived.

**Callback (MarzPay → you):** a POST to your `callback_url` when the status becomes final.
- Direct callbacks look like `{ event_type, transaction{uuid, reference, status, amount{raw}, charge{raw}, net_amount{raw}, provider}, collection{provider_transaction_id}, metadata }`.
- Dashboard-registered webhooks wrap the same body in `{ ..., data: {…} }`. Handle both shapes.
- `event_type` is `collection.completed`, `collection.failed` or `collection.cancelled`.
- The same callback can arrive more than once.

**Signature (when signing is on):** two headers.
- `X-MarzPay-Timestamp: <unix>`
- `X-MarzPay-Signature: t=<unix>,v1=<hex>`

`v1` is `hmac_sha256("{t}.{raw_body}", secret)`. Allow about 5 minutes of clock drift. The docs don't say whether the `whsec_` prefix is part of the key, so accept either.

**Money fields:** `amount` is what the customer paid, `charge` is MarzPay's fee (3% on collections in Uganda), and `net_amount` is what lands in your wallet.
- **Decide with the business:** credit the customer the full `amount` and absorb the fee, or pass the fee on. Make it visible, because for a deposit product this is a policy decision.

## 4. Rules for a financial app (most important)

1. **Credit only after a confirmed `completed`.** Get it from `GET /collect-money/{uuid}`, or from a callback that is signed **and** re-checked with a GET. A callback body alone only tells you which deposit to look at.
2. **Credit exactly once.** In one DB transaction:
   - lock the deposit row (`lockForUpdate`);
   - flip `processed_at` from NULL;
   - write the ledger entry, with a unique key on the deposit reference.

   A second callback, a status poll and a double-click must all be no-ops.
3. **Check what was confirmed against what you asked for:** the reference, the amount (`amount.raw`), and the currency (UGX). Credit nothing on a mismatch, flag it for staff, and log it.
4. **Pending is not failed.** If MarzPay can't be reached or still says `processing`, keep waiting and keep checking. Never tell the customer to pay again while one is open, or you'll get double deposits.
5. **Run a reconciliation job** every few minutes for deposits still pending after 2 minutes: GET the status, then finalise or fail. Callbacks can be late or never come. Leave anything older than about 24 hours for staff to review.
6. **Keep MarzPay's ids:** `transaction.uuid` and `collection.provider_transaction_id`, the MTN or Airtel id. Staff need them for disputes.
7. **Show which number is paying**, because a customer may deposit from someone else's phone. Record it masked (`+256772***456`) unless your KYC rules say otherwise.
8. **No refund API.** Reversals are a payout (`/send-money`) or done in the dashboard.
   - Payouts need the IP whitelist, disbursement permission, and a flat fee (UGX 1,000 or more).
   - **Only one payout can be in flight at a time**, otherwise you get HTTP 409 `PENDING_WITHDRAWAL_EXISTS`.

## 5. Minimal Laravel code

**Client:**

```php
// app/Services/MarzPayClient.php (trimmed from Mindwell's working version)
class MarzPayClient
{
    private function http() {
        return Http::withBasicAuth(config('marzpay.api_key'), config('marzpay.api_secret'))
            ->baseUrl(rtrim(config('marzpay.base_url'), '/'))->acceptJson()->asJson()->timeout(30);
    }

    public function collect(string $ref, int $amount, string $e164, string $desc, ?string $callback): array {
        $r = $this->http()->post('/collect-money', array_filter([
            'amount' => $amount, 'phone_number' => $e164, 'reference' => $ref, 'country' => 'UG',
            'description' => mb_substr($desc, 0, 255), 'callback_url' => $callback,
        ]));
        $b = $r->json() ?? [];
        if (! $r->successful() || ($b['status'] ?? null) === 'error' || empty($b['data']['transaction']['uuid'])) {
            throw new MarzPayException($b['message'] ?? 'Refused', $r->status(), $b['error_code'] ?? null);
        }
        return ['uuid' => $b['data']['transaction']['uuid'], 'status' => $b['data']['transaction']['status']];
    }

    public function status(string $uuid): ?array {           // null = MarzPay has no record (yet)
        foreach (["/collect-money/$uuid", "/transactions/$uuid"] as $path) {
            $r = $this->http()->get($path);
            if ($r->successful() && ($tx = self::tx($r->json() ?? [])) && isset($tx['status'])) {
                return [
                    'status' => strtolower($tx['status']),
                    'reference' => $tx['reference'] ?? null,
                    'amount' => (float) ($tx['amount']['raw'] ?? 0),
                    'currency' => $tx['amount']['currency'] ?? 'UGX',
                ];
            }
        }
        return null;
    }

    public static function tx(array $b): array {               // envelope, direct callback, dashboard wrapper
        return $b['data']['transaction'] ?? $b['transaction'] ?? $b['data']['data']['transaction'] ?? [];
    }

    public static function signatureValid(string $raw, ?string $sig, string $secret): bool {
        if (! $sig || $secret === '') return false;
        parse_str(str_replace(',', '&', $sig), $p);
        $t = $p['t'] ?? null; $v1 = $p['v1'] ?? null;
        if (! $t || ! $v1 || abs(time() - (int) $t) > 300) return false;
        foreach (array_unique([$secret, preg_replace('/^whsec_/', '', $secret)]) as $key) {
            if (hash_equals(hash_hmac('sha256', "$t.$raw", $key), strtolower($v1))) return true;
        }
        return false;
    }

    public static function e164(string $in): ?string {
        $d = preg_replace('/\D/', '', $in);
        if (str_starts_with($d, '256') && strlen($d) === 12) $d = substr($d, 3);
        elseif (str_starts_with($d, '0') && strlen($d) === 10) $d = substr($d, 1);
        return (strlen($d) === 9 && $d[0] === '7') ? '+256'.$d : null;
    }
}
```

**Deposit flow:**

```php
// Start: create the deposit row FIRST (status pending, reference = Str::uuid()), then:
$started = $marz->collect($deposit->reference, $deposit->amount, MarzPayClient::e164($phone), 'Insight360 deposit', config('marzpay.callback_url'));
$deposit->update(['marzpay_uuid' => $started['uuid']]);

// Finalise: the ONLY place money is credited. Called by status polling, the webhook and reconciliation.
public function finalise(Deposit $deposit): void
{
    $s = $this->marz->status($deposit->marzpay_uuid);
    if (! $s || in_array($s['status'], ['pending', 'processing'], true)) return;   // keep waiting

    DB::transaction(function () use ($deposit, $s) {
        $d = Deposit::whereKey($deposit->id)->lockForUpdate()->first();
        if ($d->processed_at) return;                                          // already done

        if ($s['status'] !== 'completed') {
            $d->update(['status' => 'failed', 'processed_at' => now()]);
            return;
        }
        if ($s['reference'] !== $d->reference || abs($s['amount'] - $d->amount) > 0.01 || $s['currency'] !== 'UGX') {
            $d->update(['status' => 'review']);                               // flag for staff, credit nothing
            return;
        }

        $d->update(['status' => 'completed', 'processed_at' => now()]);
        Ledger::credit($d->account_id, $d->amount, 'deposit', $d->reference);  // unique on reference
    });
}
```

**Webhook:**

```php
// POST /webhooks/marzpay: outside auth, CSRF-exempt. Always answer fast.
public function marzpay(Request $r)
{
    $raw = $r->getContent();
    if (config('marzpay.webhook_secret') && ! MarzPayClient::signatureValid($raw, $r->header('X-MarzPay-Signature'), config('marzpay.webhook_secret'))) {
        return response()->json(['status' => 'unauthorized'], 401);
    }
    $tx = MarzPayClient::tx(json_decode($raw, true) ?: []);
    $deposit = Deposit::where('reference', $tx['reference'] ?? '')->orWhere('marzpay_uuid', $tx['uuid'] ?? '')->first();
    if ($deposit && ! $deposit->processed_at) {
        $this->deposits->finalise($deposit);        // re-checks with GET; never trusts the body
    }
    return response()->json(['status' => 'ok']);
}
```

## 6. What MarzPay really does (tested, not from the docs)

- **Sandbox** returns `201` with `status: "sandbox"` and the message "Collection completed successfully (Sandbox Mode)".
  - No PIN prompt is ever sent.
  - **Every later lookup returns 404 "Transaction not found."**
  - So in sandbox, treat 404 as "still pending". Test the rest with a staging-only "simulate" switch that is **off by default**. Then test live with UGX 500 on your own phone.
- **Live** works as documented: the PIN prompt arrives, and the status becomes `completed`.
  - The dashboard shows two lines per payment: the collection (credit) and the fee (debit, e.g. UGX 15 on UGX 500). A failed attempt shows both lines as Failed, and no money moves.
- **Refusals** don't always carry an `error_code`. Read the `message`. "Not subscribed" means step 1.2 above.

## 7. What to tell the customer

- **Waiting:** "Check your phone and enter your Mobile Money PIN to approve the deposit." Poll status every 3 to 5 seconds for about 10 minutes, then fall back to "We'll update your balance as soon as it's confirmed."
- **Failed:** say "Deposit not completed", give the likely reason, and then **"You have not been charged."** Refill the number and network, and offer "Try again". Likely reasons:
  - cancelled or wrong PIN: "The payment was cancelled on the phone, or the PIN wasn't accepted";
  - "There wasn't enough money on that Mobile Money account";
  - "The request timed out";
  - otherwise: "The payment wasn't approved on your phone".
- **Completed:** show the new balance and a receipt with the MarzPay or provider reference.

## 8. Test checklist before customers use it

- [ ] `GET /services` shows MTN and Airtel Collection `is_subscribed: true`.
- [ ] Live UGX 500 deposit from your own MTN number, and from an Airtel number: the account is credited **once**, and the receipt is right.
- [ ] Cancel the prompt, and enter a wrong PIN: the deposit is marked failed, there is no credit, and the customer sees a clear message.
- [ ] The same callback sent twice credits once. The amount-mismatch case credits nothing.
- [ ] Close the app before approving: the callback or reconciliation still credits the deposit.
- [ ] Logs don't hold full phone numbers or secrets. Payload logging is off in production.
