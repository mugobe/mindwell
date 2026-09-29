<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Small convenience wrapper so every call site doesn't have to repeat
     * "who did this, what did they do, from where" boilerplate.
     */
    public static function record(?Request $request, string $action, ?string $description = null, array $meta = []): self
    {
        return static::create([
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}