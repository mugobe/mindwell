<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * A recorded consent (2026-11-03): who agreed to which document, which version, when, and
 * from where. Never updated or deleted by the app; a new version means a new row.
 * Documents and versions are listed in config/onboarding.php → consents.
 */
class ConsentRecord extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'document', 'version', 'accepted_at', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Records the consent once per user + document + version. */
    public static function record(User $user, string $document, string $version, ?Request $request = null): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id, 'document' => $document, 'version' => $version],
            [
                'accepted_at' => now(),
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ],
        );
    }

    /** Has this user agreed to the CURRENT version of every onboarding consent? */
    public static function currentFor(User $user): array
    {
        $out = [];
        foreach (config('onboarding.consents') as $key => $doc) {
            $out[$key] = static::where('user_id', $user->id)->where('document', $key)->where('version', $doc['version'])->first();
        }

        return $out;
    }
}
