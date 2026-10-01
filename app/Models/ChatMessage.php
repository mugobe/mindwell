<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a client ↔ therapist conversation. See ChatConversation. */
class ChatMessage extends Model
{
    use HasUuids;

    protected $fillable = ['chat_conversation_id', 'sender_user_id', 'sender_role', 'body'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** Shape sent to the chat page's JavaScript (and used for the first render). */
    public function toChatArray(string $viewerRole): array
    {
        return [
            'id' => $this->id,
            'mine' => $this->sender_role === $viewerRole,
            'body' => $this->body,
            'time' => $this->created_at->format('g:i A'),
            'day' => $this->created_at->isToday() ? 'Today'
                : ($this->created_at->isYesterday() ? 'Yesterday' : $this->created_at->format('l, M j')),
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
