<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a client ↔ therapist conversation. See ChatConversation. */
class ChatMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'chat_conversation_id', 'sender_user_id', 'sender_role', 'body',
        // 2026-11-07: one photo or PDF per message (private disk).
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    /** Allowed attachment types and size (2026-11-07, Collins: photos + PDFs, 10 MB, both ways). */
    public const ATTACHMENT_RULE = ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf'];

    public function attachmentKind(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return str_starts_with((string) $this->attachment_mime, 'image/') ? 'image' : 'pdf';
    }

    /** One line for inbox lists. */
    public function previewText(): string
    {
        if (filled($this->body)) {
            return $this->body;
        }

        return match ($this->attachmentKind()) {
            'image' => 'Sent a photo',
            'pdf' => 'Sent a PDF',
            default => '',
        };
    }

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
            'body' => (string) $this->body,
            'attachment' => $this->attachment_path ? [
                'name' => $this->attachment_name,
                'kind' => $this->attachmentKind(),
                'mime' => $this->attachment_mime,
                'size' => (int) $this->attachment_size,
                'size_label' => $this->attachment_size >= 1048576
                    ? number_format($this->attachment_size / 1048576, 1).' MB'
                    : max(1, (int) round($this->attachment_size / 1024)).' KB',
                // The app reads files through the API (token); the website through its own route.
                'url' => request()->is('api/*')
                    ? \Illuminate\Support\Facades\URL::temporarySignedRoute('files.chat', now()->addMinutes(30), ['message' => $this->id])
                    : route('messages.attachment', [$this->chat_conversation_id, $this->id]),
                // HEIC photos (iPhone) can't be shown in most browsers, so they are a download.
                'previewable' => in_array($this->attachment_mime, ['image/jpeg', 'image/png', 'image/webp'], true),
            ] : null,
            'time' => $this->created_at->format('g:i A'),
            'day' => $this->created_at->isToday() ? 'Today'
                : ($this->created_at->isYesterday() ? 'Yesterday' : $this->created_at->format('l, M j')),
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
