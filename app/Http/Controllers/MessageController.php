<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ClientProfile;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Client ↔ therapist messaging, added 2026-09-29. Decisions (AskUserQuestion):
 *  - Auto-refresh, not Laravel Reverb: an open chat polls poll() every 4s and the nav
 *    badge polls unread() every 30s. No new package or background process. If Reverb
 *    is added later, only the two polling loops in the views need replacing — this
 *    controller's send()/poll() stay as they are.
 *  - Only between a client and a therapist who have had, or have booked, a session
 *    together (ChatConversation::canTalk()).
 *
 * One controller, both roles (like VideoRoomController): routes live in the plain
 * `auth` group and every action checks the user is one of the two people in the
 * conversation. Admins can't read messages. Message contents are never audit-logged.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        [$role, $profileId] = $this->me($request->user());

        $conversations = ChatConversation::query()
            ->where($role === 'client' ? 'client_profile_id' : 'therapist_profile_id', $profileId)
            ->with(['clientProfile.user', 'therapistProfile.user', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->get()
            ->keyBy($role === 'client' ? 'therapist_profile_id' : 'client_profile_id');

        // Everyone this person may message: their conversations, plus anyone they've
        // had or booked a session with but haven't written to yet.
        $counterpartIds = TherapySession::query()
            ->where($role === 'client' ? 'client_profile_id' : 'therapist_profile_id', $profileId)
            ->whereIn('status', ChatConversation::RELATIONSHIP_STATUSES)
            ->whereNotNull($role === 'client' ? 'therapist_profile_id' : 'client_profile_id')
            ->distinct()
            ->pluck($role === 'client' ? 'therapist_profile_id' : 'client_profile_id');

        $profiles = ($role === 'client' ? TherapistProfile::class : ClientProfile::class)::with('user')
            ->whereIn('id', $counterpartIds->merge($conversations->keys())->unique())
            ->get();

        $rows = $profiles->map(function ($profile) use ($conversations, $role) {
            $conversation = $conversations->get($profile->id);
            $last = $conversation?->messages->first();

            return [
                'profile_id' => $profile->id,
                'name' => $profile->user?->name ?? ($role === 'client' ? 'Therapist' : 'Client'),
                'conversation' => $conversation,
                'preview' => $last ? (($last->sender_role === $role ? 'You: ' : '').$last->body) : null,
                'last_at' => $conversation?->last_message_at,
                'unread' => $conversation ? $conversation->unreadCountFor($role) : 0,
            ];
        })->sortByDesc(fn ($r) => $r['last_at']?->timestamp ?? 0)->values();

        return view('messages.index', ['rows' => $rows, 'role' => $role]);
    }

    /** Opens (creating on first use) the conversation with one counterpart. */
    public function start(Request $request, string $profileId): RedirectResponse
    {
        [$role, $myProfileId] = $this->me($request->user());

        [$clientProfileId, $therapistProfileId] = $role === 'client'
            ? [$myProfileId, $profileId]
            : [$profileId, $myProfileId];

        abort_unless(ChatConversation::canTalk($clientProfileId, $therapistProfileId), 403);

        $conversation = ChatConversation::firstOrCreate([
            'client_profile_id' => $clientProfileId,
            'therapist_profile_id' => $therapistProfileId,
        ]);

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, ChatConversation $conversation): View
    {
        $role = $this->roleIn($request->user(), $conversation);
        $conversation->load('clientProfile.user', 'therapistProfile.user');

        $messages = $conversation->messages()->latest()->limit(200)->get()->reverse()->values();
        $otherLastReadAt = $conversation->lastReadAtFor($role === 'client' ? 'therapist' : 'client');
        $conversation->markReadBy($role);

        $otherName = $role === 'client'
            ? ($conversation->therapistProfile?->user?->name ?? 'Your therapist')
            : ($conversation->clientProfile?->user?->name ?? 'Your client');

        return view('messages.show', [
            'conversation' => $conversation,
            'role' => $role,
            'otherName' => $otherName,
            'messages' => $messages->map(fn (ChatMessage $m) => $m->toChatArray($role)),
            'canSend' => $this->canStillTalk($conversation),
            // One variable only — Blade's @json splits its argument on commas.
            'jsConfig' => [
                'pollUrl' => route('messages.poll', $conversation),
                'sendUrl' => route('messages.send', $conversation),
                'csrf' => csrf_token(),
                'otherLastReadAt' => $otherLastReadAt?->toIso8601String(),
            ],
        ]);
    }

    public function send(Request $request, ChatConversation $conversation): JsonResponse|RedirectResponse
    {
        $role = $this->roleIn($request->user(), $conversation);
        abort_unless($this->canStillTalk($conversation), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $body = trim($validated['body']);
        if ($body === '') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Message is empty.'], 422)
                : back();
        }

        $message = $conversation->messages()->create([
            'sender_user_id' => $request->user()->id,
            'sender_role' => $role,
            'body' => $body,
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
            $role === 'client' ? 'client_last_read_at' : 'therapist_last_read_at' => now(),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message->toChatArray($role)], 201);
        }

        return redirect()->route('messages.show', $conversation);
    }

    /**
     * Returns messages created at or after `since` (the page dedupes by id — cheaper
     * and safer than an exact cursor, since two messages can share a timestamp), and
     * marks the conversation read for this person.
     */
    public function poll(Request $request, ChatConversation $conversation): JsonResponse
    {
        $role = $this->roleIn($request->user(), $conversation);

        $since = $request->date('since');

        $messages = $conversation->messages()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since->subSeconds(2)))
            ->oldest()
            ->limit(200)
            ->get();

        $conversation->markReadBy($role);
        $otherRead = $conversation->lastReadAtFor($role === 'client' ? 'therapist' : 'client');

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $m) => $m->toChatArray($role))->values(),
            'otherLastReadAt' => $otherRead?->toIso8601String(),
        ]);
    }

    /** For the nav badge (polled every 30s by the layout). */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(['count' => ChatConversation::unreadTotalFor($request->user())]);
    }

    /** @return array{0: string, 1: string} [role, profile id] */
    private function me(User $user): array
    {
        $role = $user->role;
        abort_unless(in_array($role, ['client', 'therapist'], true), 403);

        $profileId = $role === 'client' ? $user->clientProfile?->id : $user->therapistProfile?->id;
        abort_unless($profileId, 403);

        return [$role, $profileId];
    }

    private function roleIn(User $user, ChatConversation $conversation): string
    {
        if ($user->clientProfile?->id && $user->clientProfile->id === $conversation->client_profile_id) {
            return 'client';
        }
        if ($user->therapistProfile?->id && $user->therapistProfile->id === $conversation->therapist_profile_id) {
            return 'therapist';
        }

        abort(403);
    }

    private function canStillTalk(ChatConversation $conversation): bool
    {
        return ChatConversation::canTalk($conversation->client_profile_id, $conversation->therapist_profile_id);
    }
}
