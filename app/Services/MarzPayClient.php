<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * MarzPay merchant API (2026-11-02): Mobile Money collections in Uganda (MTN + Airtel,
 * the network is worked out from the number). Plain Laravel Http calls, no SDK, same
 * as FlutterwaveClient and PandoraSms.
 *
 *   POST /collect-money          start: the client gets a PIN prompt on their phone
 *   GET  /collect-money/{uuid}   where it stands now
 *   callback_url                 MarzPay posts the final status (see webhook())
 *
 * Built from MarzPay's own guide and OpenAPI file, before we had sandbox keys. The
 * parts the docs leave open (the exact shape of the status response, whether sandbox
 * payments ever complete) are read defensively here, and `php artisan marzpay:check`
 * shows the real answers. MARZPAY_LOG_PAYLOADS=true keeps a copy of every body in
 * storage/logs/marzpay.log while we test.
 *
 * Status words: pending, processing, completed, failed, cancelled, and "sandbox" on
 * a sandbox create. Never mark anything paid from the create response.
 */
class MarzPayClient
{
    public function isConfigured(): bool
    {
        return filled(config('marzpay.api_key')) && filled(config('marzpay.api_secret'));
    }

    /**
     * Starts a Mobile Money collection.
     *
     * @param  string  $reference  OUR UUID v4, new for every attempt
     * @param  string  $phone  E.164, e.g. +256772123456 (see e164())
     * @param  array<int, array<string, mixed>>  $metadata  up to 10 one-key items
     * @return array{uuid: string, status: string, sandbox: bool, message: ?string}
     *
     * @throws MarzPayException when MarzPay refuses it or can't be reached
     */
    public function collect(string $reference, int $amount, string $phone, string $description, array $metadata = [], ?string $callbackUrl = null): array
    {
        $payload = array_filter([
            'amount' => $amount,
            'phone_number' => $phone,
            'reference' => $reference,
            'country' => config('marzpay.country', 'UG'),
            'description' => mb_substr($description, 0, 255),
            'callback_url' => $callbackUrl,
            'metadata' => $metadata ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->send('post', '/collect-money', $payload);
        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['status'] ?? null) === 'error') {
            throw MarzPayException::fromResponse($response->status(), $body);
        }

        $tx = self::findTransaction($body);

        if (empty($tx['uuid'])) {
            Log::warning('MarzPay collect: no transaction uuid in the answer', ['status' => $response->status(), 'body' => $body]);

            throw new MarzPayException('MarzPay accepted the request but did not return a transaction id.', $response->status(), null);
        }

        return [
            'uuid' => (string) $tx['uuid'],
            'status' => strtolower((string) ($tx['status'] ?? 'processing')),
            'sandbox' => self::looksSandbox($body),
            'message' => $body['message'] ?? null,
        ];
    }

    /**
     * Where a collection stands now, in the shape FlutterwavePaymentController checks
     * (status / amount / currency / reference), plus MarzPay's own details.
     *
     * Tries GET /collect-money/{uuid} first and falls back to GET /transactions/{uuid}
     * (callback-shaped) if that answer can't be read.
     *
     * @throws MarzPayException if MarzPay can't be reached or neither answer is usable
     */
    public function collection(string $uuid): array
    {
        $response = $this->send('get', '/collect-money/'.rawurlencode($uuid));
        $body = $response->json() ?? [];

        // Usable = has a status AND an amount (the amount is checked before marking paid).
        $usable = fn (?array $n) => $n !== null && $n['amount'] !== null;

        if ($response->successful() && $usable($normalized = self::normalize($body))) {
            return $normalized;
        }

        $fallback = $this->send('get', '/transactions/'.rawurlencode($uuid));
        $fallbackBody = $fallback->json() ?? [];

        if ($fallback->successful() && $usable($normalized = self::normalize($fallbackBody))) {
            return $normalized;
        }

        // Both say "Transaction not found." Seen 2026-11-03 for EVERY sandbox payment:
        // MarzPay's sandbox answers the create (status "sandbox") but keeps nothing to
        // look up afterwards. The caller decides what that means (see verify()).
        if ($response->status() === 404 && $fallback->status() === 404) {
            throw new MarzPayException('Transaction not found.', 404, 'NOT_FOUND');
        }

        Log::warning('MarzPay status check could not be read', [
            'uuid' => $uuid,
            'collect_money' => ['status' => $response->status(), 'body' => $body],
            'transactions' => ['status' => $fallback->status(), 'body' => $fallbackBody],
        ]);

        throw MarzPayException::fromResponse($response->successful() ? $fallback->status() : $response->status(), $response->successful() ? $fallbackBody : $body);
    }

    /** GET /services: what this business is subscribed to. Raw body, for marzpay:check. */
    public function services(): array
    {
        return $this->raw('get', '/services');
    }

    /** GET /collect-money/services: countries and providers open for collections. */
    public function collectServices(): array
    {
        return $this->raw('get', '/collect-money/services');
    }

    /** @return array{http: int, body: array} */
    public function raw(string $method, string $path, array $payload = []): array
    {
        $response = $this->send($method, $path, $payload);

        return ['http' => $response->status(), 'body' => $response->json() ?? ['_raw' => mb_substr($response->body(), 0, 2000)]];
    }

    private function send(string $method, string $path, array $payload = []): Response
    {
        if (! $this->isConfigured()) {
            throw new MarzPayException('MARZPAY_API_KEY / MARZPAY_API_SECRET are not set in .env.', 0, 'NOT_CONFIGURED');
        }

        try {
            $response = $method === 'get'
                ? $this->http()->get($path)
                : $this->http()->post($path, $payload);
        } catch (\Throwable $e) {
            self::log('error', $method, $path, $payload, null, $e->getMessage());

            throw new MarzPayException('Could not reach MarzPay: '.$e->getMessage(), 0, 'UNREACHABLE', $e);
        }

        self::log('call', $method, $path, $payload, $response);

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth((string) config('marzpay.api_key'), (string) config('marzpay.api_secret'))
            ->baseUrl(rtrim((string) config('marzpay.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('marzpay.timeout', 30));
    }

    // ------------------------------------------------------------------ pure helpers
    // Static and free of Laravel so they can be checked on their own (see the tests).

    /** +256772123456 from 0772123456 / 772123456 / 256772123456; null if not a UG mobile. */
    public static function e164(string $input): ?string
    {
        $digits = preg_replace('/\D/', '', $input);

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = substr($digits, 1);
        }

        return (strlen($digits) === 9 && $digits[0] === '7') ? '+256'.$digits : null;
    }

    /** RFC 4122 version 4 (random). MarzPay rejects other versions. */
    public static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80);
        $h = bin2hex($b);

        return substr($h, 0, 8).'-'.substr($h, 8, 4).'-'.substr($h, 12, 4).'-'.substr($h, 16, 4).'-'.substr($h, 20);
    }

    /**
     * The transaction object, wherever this particular answer keeps it:
     * create/status envelope (data.transaction), direct callback (transaction),
     * dashboard webhook ({data: {transaction}}), or a flat data object.
     */
    public static function findTransaction(array $body): array
    {
        foreach ([
            $body['data']['transaction'] ?? null,
            $body['transaction'] ?? null,
            $body['data']['data']['transaction'] ?? null,
        ] as $candidate) {
            if (is_array($candidate) && (isset($candidate['uuid']) || isset($candidate['reference']))) {
                return $candidate;
            }
        }

        $data = $body['data'] ?? null;
        if (is_array($data) && isset($data['status']) && (isset($data['uuid']) || isset($data['reference']))) {
            return $data;
        }

        return [];
    }

    /** The part of the body that sits next to `transaction` (collection, metadata …). */
    public static function payloadRoot(array $body): array
    {
        if (isset($body['transaction'])) {
            return $body;
        }
        if (isset($body['data']['transaction'])) {
            return $body['data'];
        }
        if (isset($body['data']['data']['transaction'])) {
            return $body['data']['data'];
        }

        return $body['data'] ?? $body;
    }

    /**
     * MarzPay's status words → the ones FlutterwavePaymentController::classifyStatus()
     * already understands: 'succeeded', 'failed', 'cancelled', anything else = waiting.
     */
    public static function mapStatus(?string $status): string
    {
        $s = strtolower(trim((string) $status));

        return match (true) {
            in_array($s, ['completed', 'successful', 'succeeded'], true) => 'succeeded',
            in_array($s, ['failed', 'declined', 'rejected', 'expired'], true) => 'failed',
            in_array($s, ['cancelled', 'canceled'], true) => 'cancelled',
            default => $s === '' ? 'pending' : $s, // pending, processing, sandbox
        };
    }

    public static function looksSandbox(array $body): bool
    {
        $root = self::payloadRoot($body);
        $tx = self::findTransaction($body);

        return strtolower((string) ($tx['status'] ?? '')) === 'sandbox'
            || ($body['data']['metadata']['sandbox_mode'] ?? false) === true
            || ($root['metadata']['sandbox_mode'] ?? false) === true
            || strtolower((string) ($root['collection']['mode'] ?? '')) === 'sandbox'
            || stripos((string) ($body['message'] ?? ''), 'sandbox') !== false;
    }

    /**
     * Any MarzPay body that describes one collection (status response, callback,
     * dashboard webhook) → the checked shape. Null if there is no transaction in it.
     * No phone number is kept: this ends up in payment_transactions.meta.
     */
    public static function normalize(array $body): ?array
    {
        $tx = self::findTransaction($body);
        if (! $tx || ! isset($tx['status'])) {
            return null;
        }

        $root = self::payloadRoot($body);
        $collection = is_array($root['collection'] ?? null) ? $root['collection'] : [];

        $money = fn ($m) => is_array($m) ? ($m['raw'] ?? null) : (is_numeric($m) ? $m : null);
        $amount = $money($tx['amount'] ?? null) ?? $money($collection['amount'] ?? null);
        $currency = (is_array($tx['amount'] ?? null) ? ($tx['amount']['currency'] ?? null) : null)
            ?? (is_array($collection['amount'] ?? null) ? ($collection['amount']['currency'] ?? null) : null)
            ?? ($tx['currency'] ?? null)
            ?? 'UGX'; // Uganda-only collections; the docs always show UGX here

        return [
            'gateway' => 'marzpay',
            'status' => self::mapStatus($tx['status']),
            'marzpay_status' => strtolower((string) $tx['status']),
            'uuid' => $tx['uuid'] ?? null,
            'reference' => $tx['reference'] ?? null,
            'amount' => $amount !== null ? (float) $amount : null,
            'currency' => $currency,
            'charge' => $money($tx['charge'] ?? null) ?? $money($collection['charge'] ?? null),
            'net_amount' => $money($tx['net_amount'] ?? null) ?? $money($collection['net_amount'] ?? null),
            'provider' => $tx['provider'] ?? ($collection['provider'] ?? null),
            'provider_transaction_id' => $collection['provider_transaction_id'] ?? null,
            'event_type' => $body['event_type'] ?? ($root['event_type'] ?? null),
            // Any reason the service gave for a failure, kept short (2026-11-03).
            'reason' => self::reasonText($tx, $collection),
            'sandbox' => self::looksSandbox($body),
            'checked_at' => gmdate('c'),
        ];
    }

    /** The first reason-like text in a transaction, if any. */
    public static function reasonText(array $tx, array $collection = []): ?string
    {
        foreach (['failure_reason', 'reason', 'status_description', 'status_message', 'error_message', 'provider_message', 'message'] as $k) {
            foreach ([$tx, $collection] as $src) {
                if (isset($src[$k]) && is_string($src[$k]) && trim($src[$k]) !== '') {
                    return mb_substr(trim($src[$k]), 0, 200);
                }
            }
        }

        return null;
    }

    /**
     * Why a payment failed, roughly, from whatever the service said:
     * insufficient | declined | timeout | number | mismatch | unknown.
     * Works on MarzPay's normalized answer and on Flutterwave's raw one.
     */
    public static function failureKind(array $verified): string
    {
        $status = strtolower((string) ($verified['marzpay_status'] ?? $verified['status'] ?? ''));
        $text = strtolower(json_encode(array_intersect_key($verified, array_flip(['reason', 'processor_response', 'message', 'status_description', 'error', 'narration']))) ?: '');

        return match (true) {
            str_contains($text, 'insufficient') || str_contains($text, 'not enough') || str_contains($text, 'low balance') => 'insufficient',
            str_contains($text, 'timeout') || str_contains($text, 'timed out') || str_contains($text, 'expired') => 'timeout',
            str_contains($text, 'invalid') && (str_contains($text, 'number') || str_contains($text, 'msisdn') || str_contains($text, 'phone')) => 'number',
            str_contains($text, 'pin') || str_contains($text, 'declin') || str_contains($text, 'reject') || str_contains($text, 'cancel') || $status === 'cancelled' => 'declined',
            in_array($status, ['succeeded', 'completed', 'successful'], true) => 'mismatch',
            default => 'unknown',
        };
    }

    /**
     * X-MarzPay-Signature: t={unix},v1={hex hmac_sha256("{t}.{raw body}", secret)}.
     * Allows 5 minutes of clock drift. MarzPay's docs don't say whether the "whsec_"
     * prefix is part of the key, so both are accepted.
     */
    public static function signatureValid(string $rawBody, ?string $signatureHeader, ?string $timestampHeader, string $secret, ?int $now = null, int $tolerance = 300): bool
    {
        if ($secret === '' || ! $signatureHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k][] = $v;
        }

        $t = $parts['t'][0] ?? $timestampHeader;
        $candidates = $parts['v1'] ?? [];

        if (! $t || ! ctype_digit((string) $t) || ! $candidates) {
            return false;
        }
        if (abs(($now ?? time()) - (int) $t) > $tolerance) {
            return false;
        }

        $keys = [$secret];
        if (str_starts_with($secret, 'whsec_')) {
            $keys[] = substr($secret, 6);
        }

        foreach ($keys as $key) {
            $expected = hash_hmac('sha256', $t.'.'.$rawBody, $key);
            foreach ($candidates as $given) {
                if (hash_equals($expected, strtolower($given))) {
                    return true;
                }
            }
        }

        return false;
    }

    /** +256772123456 → +256772***456, for anything printed or logged. */
    public static function maskPhones(string $text): string
    {
        return preg_replace('/(\+?256|0)(7\d{2})\d{3}(\d{3})/', '$1$2***$3', $text);
    }

    private static function log(string $kind, string $method, string $path, array $payload, ?Response $response, ?string $error = null): void
    {
        if (! config('marzpay.log_payloads')) {
            return;
        }

        self::channel()->info('MarzPay '.$kind.' '.strtoupper($method).' '.$path, [
            'request' => $payload ?: null,
            'http' => $response?->status(),
            'response' => $response ? ($response->json() ?? mb_substr($response->body(), 0, 2000)) : null,
            'error' => $error,
        ]);
    }

    /** storage/logs/marzpay.log — only written while MARZPAY_LOG_PAYLOADS=true. */
    public static function channel(): LoggerInterface
    {
        return Log::build(['driver' => 'single', 'path' => storage_path('logs/marzpay.log')]);
    }
}
