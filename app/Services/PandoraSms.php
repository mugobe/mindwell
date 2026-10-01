<?php

namespace App\Services;

use App\Support\UgandaPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one SMS through Pandora (sms.thepandoranetworks.com). Added 2026-09-30.
 * Plain Http client, no Composer package — same approach as FlutterwaveClient and
 * LiveKitToken. See config/pandora.php for settings.
 *
 * Never throws: every outcome comes back as ['ok' => bool, 'detail' => string] so an
 * SMS problem can never break the action that triggered it (approving a booking etc).
 */
class PandoraSms
{
    public function isConfigured(): bool
    {
        return filled(config('pandora.username')) && filled(config('pandora.password'));
    }

    /**
     * @param  string  $normalizedNumber  2567XXXXXXXX (see UgandaPhone::normalize)
     * @return array{ok: bool, detail: string}
     */
    public function send(string $normalizedNumber, string $message): array
    {
        // Pandora caps a message at 160 characters.
        $message = mb_strlen($message) > 160 ? mb_substr($message, 0, 157).'...' : $message;
        $number = UgandaPhone::format($normalizedNumber, (string) config('pandora.number_format', 'local'));

        if (! $this->isConfigured()) {
            Log::info('SMS NOT SENT (Pandora not configured — set PANDORA_SMS_USERNAME/PASSWORD)', [
                'to' => $number,
                'message' => $message,
            ]);

            return ['ok' => false, 'detail' => 'Pandora SMS is not configured; the message was written to the log instead.'];
        }

        $sender = (string) config('pandora.sender');
        $params = [
            'username' => config('pandora.username'),
            'password' => config('pandora.password'),
            'number' => $number,
            'message' => $message,
            // Pandora's own docs name this 'sender' in their GET example and
            // 'sender_id' in their POST example — both are sent so either works.
            'sender' => $sender,
            'sender_id' => $sender,
            'message_type' => config('pandora.message_type'),
            'message_category' => config('pandora.message_category'),
        ];

        try {
            $request = Http::timeout(15)->acceptJson();
            $response = strtolower((string) config('pandora.method')) === 'get'
                ? $request->get(config('pandora.endpoint'), $params)
                : $request->asForm()->post(config('pandora.endpoint'), $params);

            $body = $response->json();
            $ok = $response->successful() && is_array($body) && ($body['success'] ?? false) === true;
            $detail = is_array($body)
                ? (string) ($body['message'] ?? $body['error_message'] ?? $body['error'] ?? json_encode($body))
                : mb_substr(trim(strip_tags($response->body())), 0, 300);

            // The password is never logged.
            Log::log($ok ? 'info' : 'warning', $ok ? 'SMS sent via Pandora' : 'Pandora SMS rejected', [
                'to' => $number,
                'http_status' => $response->status(),
                'detail' => $detail,
            ]);

            return ['ok' => $ok, 'detail' => $detail ?: ('HTTP '.$response->status())];
        } catch (Throwable $e) {
            Log::warning('Pandora SMS request failed', ['to' => $number, 'error' => $e->getMessage()]);

            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
