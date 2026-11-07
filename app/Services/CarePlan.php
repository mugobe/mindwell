<?php

namespace App\Services;

use App\Models\ClientProfile;
use App\Models\Homework;
use App\Models\ResourceShare;
use App\Models\TherapistProfile;
use App\Models\TherapyGoal;
use App\Models\TherapySession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Care features in one place (2026-11-07): who may see/act, the "My therapy" summary,
 * and saving uploaded files privately. Used by the website and the mobile API.
 */
class CarePlan
{
    /** A therapist may act for a client they have (had) a session with, like "My clients". */
    public static function therapistMayAct(?TherapistProfile $therapist, ClientProfile $client): bool
    {
        return $therapist && $therapist->credentials_verified
            && TherapySession::where('client_profile_id', $client->id)->where('therapist_profile_id', $therapist->id)->exists();
    }

    /** Everything on the client's "My therapy" page. */
    public function forClient(ClientProfile $client): array
    {
        $next = TherapySession::with('therapistProfile.user', 'venue')
            ->where('client_profile_id', $client->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now()->subHours(2))
            ->orderBy('scheduled_at')
            ->first();

        $last = TherapySession::with('therapistProfile.user')
            ->where('client_profile_id', $client->id)
            ->whereNotNull('therapist_profile_id')
            ->whereIn('status', ['scheduled', 'completed'])
            ->orderByDesc('scheduled_at')
            ->first();

        $therapist = $next?->therapistProfile ?? $last?->therapistProfile;

        return [
            'therapist' => $therapist,
            'nextSession' => $next,
            'completedCount' => TherapySession::where('client_profile_id', $client->id)->where('status', 'completed')->count(),
            'goals' => TherapyGoal::with('updates')->where('client_profile_id', $client->id)
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'achieved' THEN 1 ELSE 2 END")
                ->latest()->get(),
            'homework' => Homework::with('resource', 'therapistProfile.user')->where('client_profile_id', $client->id)
                ->orderByRaw("CASE status WHEN 'assigned' THEN 0 ELSE 1 END")
                ->orderByRaw('due_on IS NULL')->orderBy('due_on')->latest()->get(),
            'shared' => ResourceShare::with('resource', 'therapistProfile.user')
                ->where('client_profile_id', $client->id)
                ->whereHas('resource', fn ($q) => $q->where('is_published', true))
                ->latest()->limit(30)->get(),
        ];
    }

    /** Saves an uploaded file on the private disk. @return array{path:string,name:string,mime:?string,size:int} */
    public static function store(UploadedFile $file, string $folder): array
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $name = mb_substr(preg_replace('/[^\pL\pN ._()-]+/u', '_', basename($file->getClientOriginalName())) ?: 'file.'.$ext, 0, 150);

        return [
            'path' => $file->storeAs($folder, (string) Str::uuid().'.'.$ext, 'local'),
            'name' => $name,
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    /** Streams a private file inline (images, PDFs, audio), never as something to run. */
    public static function stream(?string $path, ?string $name, ?string $mime)
    {
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path, $name, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ], 'inline');
    }
}
