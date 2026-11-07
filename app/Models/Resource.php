<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A resource in the library (2026-11-07, roadmap 37). Only admins add them (Collins).
 * Clients see published ones; therapists can share any published one with a client.
 * A file (PDF or audio) lives on the private "local" disk under resources/{id}/.
 */
class Resource extends Model
{
    use HasUuids;

    public const KINDS = [
        'article' => 'Article',
        'pdf' => 'PDF / worksheet',
        'audio' => 'Audio',
        'video' => 'Video',
        'link' => 'Web link',
    ];

    /** Suggested topics (admins can type their own). */
    public const TOPICS = ['Anxiety', 'Low mood', 'Stress', 'Sleep', 'Relationships', 'Grief and loss', 'Self-care', 'Breathing and relaxation', 'Worksheets'];

    /** Upload rule: PDFs and audio, up to 20 MB. */
    public const FILE_RULE = ['file', 'max:20480', 'mimes:pdf,mp3,m4a,aac,wav,ogg'];

    protected $fillable = [
        'title', 'summary', 'kind', 'topic', 'body', 'file_path', 'file_name', 'file_mime', 'file_size',
        'url', 'minutes', 'is_published', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'minutes' => 'integer', 'file_size' => 'integer'];
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ResourceShare::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    /** "5 min read", "12 min listen", "PDF", ... */
    public function lengthLabel(): ?string
    {
        if ($this->minutes) {
            return $this->minutes.' min '.match ($this->kind) {
                'audio' => 'listen',
                'video' => 'watch',
                default => 'read',
            };
        }

        return $this->kind === 'pdf' ? 'PDF' : null;
    }

    /** A YouTube link as an embeddable address (privacy-enhanced), or null. */
    public function youtubeEmbed(): ?string
    {
        if (! $this->url || ! preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $this->url, $m)) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$m[1];
    }

    /** Where to open the file: the website's own route, or a 30-minute signed link for the app. */
    public function fileUrl(bool $signed = false): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return $signed
            ? \Illuminate\Support\Facades\URL::temporarySignedRoute('files.resource', now()->addMinutes(30), ['resource' => $this->id])
            : route('resources.file', $this);
    }
}
