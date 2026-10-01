<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\LiveKitToken;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Real video sessions (LiveKit), added 2026-09-28.
 *
 * ONE room page, used by both sides of a session: the session's own client and its
 * assigned therapist. Nobody else (admins included) ever gets a token.
 *
 * WHEN each side may enter — revised 2026-09-28 at the user's request ("shouldn't the
 * therapist be able to start the session before the time, and the patient get let in?"),
 * decided via AskUserQuestion: "Therapist opens early + client alerted".
 *  - THERAPIST: any time on the session's day, until the window closes.
 *  - CLIENT: from video_join_early_minutes before the start time — OR earlier, the
 *    moment the therapist is actually in the room. The client's page polls status()
 *    every few seconds and flips to "Alexandre is ready — Join now" (with a chime and a
 *    flashing tab title) as soon as that happens.
 *  - BOTH: until video_join_late_minutes after the planned end.
 * The client still clicks Join themselves: no website can switch someone's camera on
 * without a click, and a therapy client shouldn't be put on camera unannounced anyway.
 *
 * "In the room" = presence(), a heartbeat each connected browser sends every 15s,
 * kept in the cache for 45s. No migration needed, and it self-clears if a browser
 * crashes or loses its connection.
 *
 * Tokens are only minted by token() — at the moment someone clicks Join — never baked
 * into the page, so a page left open for an hour can't be holding a stale or
 * too-early token. Nothing is recorded: LiveKit only relays the live call.
 */
class VideoRoomController extends Controller
{
    private const PRESENCE_TTL_SECONDS = 45;

    public function show(Request $request, TherapySession $therapySession): View|RedirectResponse
    {
        $user = $request->user();
        $role = $this->participantRole($user, $therapySession);
        abort_unless($role !== null, 403);

        $backUrl = $role === 'therapist' ? route('therapist.sessions.index') : route('sessions.history');

        if ($therapySession->status !== 'scheduled') {
            return redirect($backUrl)->with('error', $therapySession->status === 'completed'
                ? 'That session has already been closed, so its video room is no longer open.'
                : "That session isn't confirmed yet, so its video room isn't open.");
        }

        $therapySession->load('clientProfile.user', 'therapistProfile.user');
        $access = $this->access($therapySession, $role);

        $otherName = $role === 'therapist'
            ? ($therapySession->clientProfile?->user?->name ?? 'your client')
            : ($therapySession->therapistProfile?->user?->name ?? 'your therapist');

        $leaveUrl = $role === 'therapist'
            ? route('therapist.sessions.close', $therapySession)
            : route('sessions.history');

        // The side-panel notepad (added 2026-09-29): the client's goes to their private
        // journal, the therapist's to therapy_sessions.therapist_live_notes.
        $clientNote = $role === 'client'
            ? JournalEntry::where('client_profile_id', $therapySession->client_profile_id)
                ->where('therapy_session_id', $therapySession->id)
                ->first()
            : null;

        return view('sessions.room', [
            'session' => $therapySession,
            'role' => $role,
            'access' => $access,
            'configured' => app(LiveKitToken::class)->isConfigured(),
            'otherName' => $otherName,
            'backUrl' => $backUrl,
            'notesBody' => $role === 'client' ? ($clientNote?->body ?? '') : ($therapySession->therapist_live_notes ?? ''),
            'notesShared' => (bool) $clientNote?->shared_with_therapist,
            // Handed to the page's JavaScript as ONE variable on purpose: Blade's
            // @json() splits its argument on commas, so an inline array literal inside
            // @json(...) breaks the compiled view (bug found 2026-09-28).
            'jsConfig' => [
                'role' => $role,
                'otherName' => $otherName,
                'state' => $access['state'],
                'otherLive' => $access['other_live'],
                'leaveUrl' => $leaveUrl,
                'statusUrl' => route('sessions.room.status', $therapySession),
                'tokenUrl' => route('sessions.room.token', $therapySession),
                'presenceUrl' => route('sessions.room.presence', $therapySession),
                'notesUrl' => route('sessions.room.notes', $therapySession),
                'journalUrl' => route('journal.index'),
                // Planned end of the session, for the gentle "40 min left" timer.
                'endsAtMs' => $access['starts_at']->copy()->addMinutes($therapySession->duration_minutes ?: 70)->getTimestamp() * 1000,
                'csrf' => csrf_token(),
            ],
        ]);
    }

    /** Polled by the room page (every 5s) until the person joins. */
    public function status(Request $request, TherapySession $therapySession): JsonResponse
    {
        $role = $this->participantRole($request->user(), $therapySession);
        abort_unless($role !== null, 403);

        $access = $this->access($therapySession, $role);

        return response()->json([
            'state' => $access['state'],
            'otherLive' => $access['other_live'],
        ]);
    }

    /** Called when someone clicks Join. The only place a LiveKit token is minted. */
    public function token(Request $request, TherapySession $therapySession): JsonResponse
    {
        $user = $request->user();
        $role = $this->participantRole($user, $therapySession);
        abort_unless($role !== null, 403);

        $livekit = app(LiveKitToken::class);
        if (! $livekit->isConfigured()) {
            return response()->json(['message' => "Video isn't set up on this server yet."], 503);
        }

        $access = $this->access($therapySession, $role);
        if ($access['state'] !== 'open') {
            return response()->json(['message' => match ($access['state']) {
                'too_early' => "The room isn't open for you yet.",
                'ended' => "This session's video window has ended.",
                default => 'This session is no longer open for video.',
            }, 'state' => $access['state']], 409);
        }

        $token = $livekit->forRoom(
            room: $livekit->roomNameFor($therapySession->id),
            identity: "{$role}-{$user->id}",
            displayName: $user->name,
            expiresAt: $access['closes_at'],
        );

        AuditLog::record($request, 'session.video_joined', "{$user->name} joined the video room ({$role}).", [
            'therapy_session_id' => $therapySession->id,
        ]);

        // Added 2026-09-30: when the therapist opens the room and the client isn't in it,
        // tell the client by email + SMS (once per session) — the in-page chime only
        // reaches them if they already have the room page open.
        if ($role === 'therapist' && ! $access['other_live'] && ! $therapySession->room_alert_sent_at) {
            $therapySession->forceFill(['room_alert_sent_at' => now()])->saveQuietly();
            app(Notifier::class)->roomReady($therapySession);
        }

        return response()->json(['url' => config('livekit.url'), 'token' => $token]);
    }

    /** Heartbeat from a connected browser; `leaving=1` clears it immediately. */
    public function presence(Request $request, TherapySession $therapySession): Response
    {
        $role = $this->participantRole($request->user(), $therapySession);
        abort_unless($role !== null, 403);

        $key = $this->presenceKey($therapySession, $role);

        if ($request->boolean('leaving')) {
            Cache::forget($key);
        } elseif ($therapySession->status === 'scheduled') {
            Cache::put($key, now()->timestamp, self::PRESENCE_TTL_SECONDS);
        }

        return response()->noContent();
    }

    /**
     * Autosave for the room's side-panel notepad (added 2026-09-29), called a moment
     * after the person stops typing. Works whether or not they've joined the call yet.
     *  - Client: upserts their one journal entry for this session (kind 'session'),
     *    including the "Share with my therapist" switch. Private unless shared.
     *  - Therapist: saves therapy_sessions.therapist_live_notes, which pre-fills the
     *    report's "Session summary" when they close the session. Never shown to the client.
     */
    public function notes(Request $request, TherapySession $therapySession): JsonResponse
    {
        $role = $this->participantRole($request->user(), $therapySession);
        abort_unless($role !== null, 403);

        if ($therapySession->status !== 'scheduled') {
            return response()->json(['message' => $role === 'client'
                ? 'This session has ended — you can keep editing these notes in your journal.'
                : 'This session has been closed, so its live notes are locked.'], 409);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'shared' => ['nullable', 'boolean'],
        ]);

        if ($role === 'client') {
            JournalEntry::updateOrCreate(
                ['client_profile_id' => $therapySession->client_profile_id, 'therapy_session_id' => $therapySession->id],
                [
                    'kind' => 'session',
                    'body' => $validated['body'] ?? null,
                    'shared_with_therapist' => (bool) ($validated['shared'] ?? false),
                ],
            );
        } else {
            $therapySession->update(['therapist_live_notes' => $validated['body'] ?? null]);
        }

        return response()->json(['saved' => true]);
    }

    /**
     * The single rule every entry point shares.
     *
     * @return array{state: string, other_live: bool, opens_at: Carbon, closes_at: Carbon, starts_at: Carbon}
     */
    private function access(TherapySession $session, string $role): array
    {
        $startsAt = $session->scheduled_at ?? now();
        $duration = $session->duration_minutes ?: (int) config('mindwell.session_duration_minutes', 70);
        $closesAt = $startsAt->copy()->addMinutes($duration + (int) config('mindwell.video_join_late_minutes', 30));

        $opensAt = $role === 'therapist'
            ? $startsAt->copy()->startOfDay()
            : $startsAt->copy()->subMinutes((int) config('mindwell.video_join_early_minutes', 10));

        $otherRole = $role === 'therapist' ? 'client' : 'therapist';
        $otherLive = Cache::has($this->presenceKey($session, $otherRole));

        $now = now();

        $state = match (true) {
            $session->status !== 'scheduled' => 'closed',
            $now->gt($closesAt) => 'ended',
            $now->gte($opensAt) => 'open',
            // The early-start rule: a client may come in early once the therapist is there.
            $role === 'client' && $otherLive => 'open',
            default => 'too_early',
        };

        return [
            'state' => $state,
            'other_live' => $otherLive,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'starts_at' => $startsAt,
        ];
    }

    private function presenceKey(TherapySession $session, string $role): string
    {
        return "mindwell.room.{$session->id}.{$role}_live";
    }

    /**
     * 'client' or 'therapist' if this user is one of the two people in this session,
     * otherwise null. Checked against the profile ids actually on the session row.
     */
    private function participantRole(User $user, TherapySession $session): ?string
    {
        $clientProfileId = $user->clientProfile?->id;
        if ($clientProfileId && $clientProfileId === $session->client_profile_id) {
            return 'client';
        }

        $therapistProfileId = $user->therapistProfile?->id;
        if ($therapistProfileId && $therapistProfileId === $session->therapist_profile_id) {
            return 'therapist';
        }

        return null;
    }
}
