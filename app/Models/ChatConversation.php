<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

/**
 * A private conversation between one client and one therapist (added 2026-09-29).
 * One row per pair. Only allowed once they have had, or have booked, a session
 * together — see canTalk(). Admins have no access to message contents.
 */
class ChatConversation extends Model
{
    use HasUuids;

    /** Session statuses that count as "we have a relationship". */
    public const RELATIONSHIP_STATUSES = ['pending_approval', 'scheduled', 'completed'];

    protected $fillable = [
        'client_profile_id',
        'therapist_profile_id',
        'last_message_at',
        'client_last_read_at',
        'therapist_last_read_at',
        'client_notified_at',
        'therapist_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'client_notified_at' => 'datetime',
            'therapist_notified_at' => 'datetime',
            'last_message_at' => 'datetime',
            'client_last_read_at' => 'datetime',
            'therapist_last_read_at' => 'datetime',
        ];
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function therapistProfile(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public static function canTalk(string $clientProfileId, string $therapistProfileId): bool
    {
        return TherapySession::where('client_profile_id', $clientProfileId)
            ->where('therapist_profile_id', $therapistProfileId)
            ->whereIn('status', self::RELATIONSHIP_STATUSES)
            ->exists();
    }

    public function lastReadAtFor(string $role)
    {
        return $role === 'client' ? $this->client_last_read_at : $this->therapist_last_read_at;
    }

    public function markReadBy(string $role): void
    {
        $this->forceFill([$role === 'client' ? 'client_last_read_at' : 'therapist_last_read_at' => now()])->save();
    }

    public function unreadCountFor(string $role): int
    {
        $lastRead = $this->lastReadAtFor($role);

        return $this->messages()
            ->where('sender_role', '!=', $role)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }

    /**
     * Total unread messages for the nav badge. Wrapped so a missing table (migration
     * not run yet) can never take down every page that uses the main layout.
     */
    public static function unreadTotalFor(?User $user): int
    {
        if (! $user || ! in_array($user->role, ['client', 'therapist'], true)) {
            return 0;
        }

        try {
            $role = $user->role;
            $profileId = $role === 'client' ? $user->clientProfile?->id : $user->therapistProfile?->id;
            if (! $profileId) {
                return 0;
            }

            $readColumn = $role === 'client' ? 'client_last_read_at' : 'therapist_last_read_at';

            return ChatMessage::query()
                ->join('chat_conversations as c', 'c.id', '=', 'chat_messages.chat_conversation_id')
                ->where($role === 'client' ? 'c.client_profile_id' : 'c.therapist_profile_id', $profileId)
                ->where('chat_messages.sender_role', '!=', $role)
                ->where(function ($q) use ($readColumn) {
                    $q->whereNull("c.{$readColumn}")
                        ->orWhereColumn('chat_messages.created_at', '>', "c.{$readColumn}");
                })
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
