<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        // Added 2026-09-30 for reminders & notifications. phone is stored normalized
        // as 2567XXXXXXXX — see App\Support\UgandaPhone.
        'phone',
        'notify_email',
        'notify_sms',
        // Added 2026-10-03 for progress & mood insights — see App\Models\CheckIn.
        'check_in_reminded_at',
        'share_mood_with_therapist',
        // Added 2026-10-04: set when an admin gives a temporary password.
        'must_change_password',
        // Phase 0 (2026-10-05): sign-up details + consent records.
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'terms_accepted_at',
        'privacy_accepted_at',
        'emergency_disclaimer_accepted_at',
        'legal_version',
        // Therapist workspace (2026-10-06): client file details.
        'preferred_language',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'check_in_reminded_at' => 'datetime',
            'share_mood_with_therapist' => 'boolean',
            'must_change_password' => 'boolean',
            'date_of_birth' => 'date',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'emergency_disclaimer_accepted_at' => 'datetime',
        ];
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function therapistProfile(): HasOne
    {
        return $this->hasOne(TherapistProfile::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * The "reset your password" email (added 2026-10-04), in Mindwell's own wording
     * instead of Laravel's default. Always sent — the email/SMS switches in Settings are
     * for reminders, not account security.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $first = trim(explode(' ', trim((string) $this->name))[0] ?? '');

        $this->notify(new \App\Notifications\MindwellNotice(
            'Reset your Mindwell password',
            'Hi '.($first !== '' ? $first : 'there').',',
            [
                'We got a request to reset the password for your Mindwell account.',
                'This link works for '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60).' minutes.',
            ],
            'Choose a new password',
            route('password.reset', ['token' => $token, 'email' => $this->email]),
            "If you didn't ask for this, you can ignore this email — your password won't change.",
        ));
    }

    /** Gender options at sign-up (optional field). */
    public const GENDERS = [
        'female' => 'Female',
        'male' => 'Male',
        'other' => 'Other',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    /**
     * Has this person accepted the CURRENT Terms of Use and Privacy Policy (and, for
     * clients, the "not a replacement for emergency care" statement)? Added in Phase 0.
     */
    public function hasAcceptedCurrentLegal(): bool
    {
        return $this->terms_accepted_at !== null
            && $this->privacy_accepted_at !== null
            && ($this->role !== 'client' || $this->emergency_disclaimer_accepted_at !== null)
            && $this->legal_version === config('mindwell.legal.version');
    }

    /** The consent fields to save when someone ticks the boxes. */
    public static function consentAttributes(bool $includeEmergency = true): array
    {
        $now = now();

        return array_filter([
            'terms_accepted_at' => $now,
            'privacy_accepted_at' => $now,
            'emergency_disclaimer_accepted_at' => $includeEmergency ? $now : null,
            'legal_version' => config('mindwell.legal.version'),
        ]);
    }

    /**
     * 2026-10-07: client_profiles has an older `dob` column. users.date_of_birth is the
     * one Mindwell reads; this keeps the old column matching so nothing disagrees.
     */
    protected static function booted(): void
    {
        // New client profiles start with the user's date of birth (they're created
        // after the user — at sign-up and lazily on first booking/intake).
        if (class_exists(ClientProfile::class)) {
            ClientProfile::creating(function ($profile) {
                if (empty($profile->dob) && $profile->user_id) {
                    $profile->dob = static::whereKey($profile->user_id)->value('date_of_birth');
                }
            });
        }

        static::saved(function (User $user) {
            if ($user->wasChanged('date_of_birth') || $user->wasRecentlyCreated) {
                rescue(fn () => \Illuminate\Support\Facades\DB::table('client_profiles')
                    ->where('user_id', $user->getKey())
                    ->update(['dob' => $user->date_of_birth?->toDateString()]), report: false);
            }
        });
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * The client's "Client 0001" number (2026-10-06, Dr. Ntabade's THERAPIST DRAFT).
     * Given out in order the first time it's needed; safe if two requests race (the
     * column is unique, so the loser just takes the next number).
     */
    public function ensureClientNumber(): ?int
    {
        if ($this->role !== 'client') {
            return null;
        }
        if ($this->client_number) {
            return (int) $this->client_number;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $next = ((int) static::max('client_number')) + 1;

            try {
                static::whereKey($this->getKey())->whereNull('client_number')->update(['client_number' => $next]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                continue;
            }

            $this->refresh();
            if ($this->client_number) {
                return (int) $this->client_number;
            }
        }

        return null;
    }

    /** "Client 0007" — or null for non-clients. */
    public function clientLabel(): ?string
    {
        $number = $this->ensureClientNumber();

        return $number ? 'Client '.str_pad((string) $number, 4, '0', STR_PAD_LEFT) : null;
    }
}
