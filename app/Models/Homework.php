<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Homework / a worksheet (2026-11-07, roadmap 36). The therapist gives it (optionally
 * with a library resource such as a PDF worksheet, and a due date); the client answers
 * with words and/or a photo or PDF, which marks it done; the therapist marks it reviewed.
 */
class Homework extends Model
{
    use HasUuids;

    protected $table = 'homework';

    /** Reply file: photos and PDFs, up to 10 MB (same as messages). */
    public const FILE_RULE = ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf'];

    protected $fillable = [
        'client_profile_id', 'therapist_profile_id', 'resource_id', 'title', 'instructions', 'due_on', 'status',
        'reply', 'reply_file_path', 'reply_file_name', 'reply_file_mime', 'reply_file_size', 'done_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime', 'reviewed_at' => 'datetime', 'reply_file_size' => 'integer'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function therapistProfile(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_on && $this->due_on->lt(now()->startOfDay());
    }

    public function replyFileUrl(bool $signed = false): ?string
    {
        if (! $this->reply_file_path) {
            return null;
        }

        return $signed
            ? \Illuminate\Support\Facades\URL::temporarySignedRoute('files.homework', now()->addMinutes(30), ['homework' => $this->id])
            : route('homework.file', $this);
    }
}
