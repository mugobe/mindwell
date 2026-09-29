<?php

namespace App\Services;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Mints LiveKit access tokens, added 2026-09-28 for real video sessions.
 *
 * A LiveKit access token is a plain HS256 JWT signed with the project's API secret —
 * so, like FlutterwaveClient, this is a few lines of PHP rather than a Composer SDK.
 * The claim layout matches LiveKit's own livekit-server-sdk (v2.19, AccessToken.toJwt):
 * `iss` = API key, `sub` = participant identity, `nbf`/`exp`, `name` = display name,
 * and a `video` grant naming the one room this participant may join. It was checked
 * against that SDK's own TokenVerifier before delivery.
 *
 * The browser only ever receives a token for ONE room (that session's), minted
 * server-side after VideoRoomController has checked the user is that session's
 * client or therapist — the API secret itself never leaves the server.
 */
class LiveKitToken
{
    public function isConfigured(): bool
    {
        return filled(config('livekit.url'))
            && filled(config('livekit.api_key'))
            && filled(config('livekit.api_secret'));
    }

    public function roomNameFor(string $sessionId): string
    {
        return config('livekit.room_prefix').$sessionId;
    }

    public function forRoom(string $room, string $identity, string $displayName, CarbonInterface $expiresAt): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('LIVEKIT_URL / LIVEKIT_API_KEY / LIVEKIT_API_SECRET are not set — add them to .env.');
        }

        $now = time();

        $payload = [
            'iss' => config('livekit.api_key'),
            'sub' => $identity,
            'name' => $displayName,
            'nbf' => $now - 10, // small allowance for clock drift between this server and LiveKit
            // Only has to be valid at the moment of joining — LiveKit refreshes a
            // connected participant's token itself. Never shorter than 5 minutes.
            'exp' => max($expiresAt->getTimestamp(), $now + 300),
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        return $this->encode($payload, (string) config('livekit.api_secret'));
    }

    private function encode(array $payload, string $secret): string
    {
        $segments = [
            $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];

        $segments[] = $this->base64Url(hash_hmac('sha256', implode('.', $segments), $secret, true));

        return implode('.', $segments);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
