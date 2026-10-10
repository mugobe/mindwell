<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\TherapistProfile;
use App\Models\TherapySession;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — messages with their therapist (2026-10-09). Same rules as the website
 * (only after a session together; admins can't read). Sending, polling for new messages
 * and the unread count use the website's MessageController directly — those actions
 * already return JSON.
 */
class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profileId = $request->user()->clientProfile?->id;
        abort_unless($profileId, 403);

        $conversations = ChatConversation::where('client_profile_id', $profileId)
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->get()
            ->keyBy('therapist_profile_id');

        $therapistIds = TherapySession::where('client_profile_id', $profileId)
            ->whereIn('status', ChatConversation::RELATIONSHIP_STATUSES)
            ->whereNotNull('therapist_profile_id')
            ->distinct()
            ->pluck('therapist_profile_id');

        $rows = TherapistProfile::with('user')
            ->whereIn('id', $therapistIds->merge($conversations->keys())->unique())
            ->get()
            ->map(function ($t) use ($conversations, $profileId) {
                $c = $conversations->get($t->id);
                $last = $c?->messages->first();

                return [
                    'therapist' => ApiPresenter::therapist($t),
                    'conversation_id' => $c?->id,
                    'preview' => $last ? (($last->sender_role === 'client' ? 'You: ' : '').$last->body) : null,
                    'last_at' => ApiPresenter::date($c?->last_message_at),
                    'unread' => $c ? $c->unreadCountFor('client') : 0,
                    'can_send' => ChatConversation::canTalk($profileId, $t->id),
                ];
            })
            ->sortByDesc(fn ($r) => $r['last_at'] ?? '')
            ->values();

        return response()->json(['conversations' => $rows]);
    }

    /**
     * Open (creating on first use) the conversation with one of their therapists and
     * return the latest 200 messages. Marks it read. `can_send` false = read-only.
     */
    public function open(Request $request, TherapistProfile $therapist): JsonResponse
    {
        $profileId = $request->user()->clientProfile?->id;
        abort_unless($profileId, 403);

        // Like the website's chat page: an existing conversation can always be READ;
        // starting one, or sending, needs a session together (canTalk). Fixed 2026-10-09
        // for the app (it used to 403 on old conversations, so they couldn't be opened).
        $canSend = ChatConversation::canTalk($profileId, $therapist->id);
        $conversation = ChatConversation::where('client_profile_id', $profileId)
            ->where('therapist_profile_id', $therapist->id)
            ->first();

        abort_unless($conversation || $canSend, 403);

        $conversation ??= ChatConversation::firstOrCreate([
            'client_profile_id' => $profileId,
            'therapist_profile_id' => $therapist->id,
        ]);

        $messages = $conversation->messages()->latest()->limit(200)->get()->reverse()->values();
        $otherRead = $conversation->lastReadAtFor('therapist');
        $conversation->markReadBy('client');
        $therapist->loadMissing('user');

        return response()->json([
            'conversation_id' => $conversation->id,
            'therapist' => ApiPresenter::therapist($therapist),
            'messages' => $messages->map(fn (ChatMessage $m) => $m->toChatArray('client'))->values(),
            'therapist_last_read_at' => $otherRead?->toIso8601String(),
            'can_send' => $canSend,
            'safety_note' => "Messages aren't watched around the clock. If you're in danger or thinking of harming yourself, use Get help now.",
        ]);
    }
}
