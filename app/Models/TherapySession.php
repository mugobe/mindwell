<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapySession extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_profile_id',
        'therapist_profile_id',
        'intake_assessment_id',
        'previous_session_id',
        'status',
        'payment_method',
        'amount_charged',
        'routing_method',
        'scheduled_at',
        'duration_minutes',
        'entered_lobby_at',
        'routed_at',
        'approved_at',
        'closure_outcome',
        'closure_report',
        'presenting_concerns',
        'session_summary',
        'progress_notes',
        'risk_assessment',
        'plan_notes',
        'follow_up_notes',
        'therapist_live_notes',
        'reminder_24h_sent_at',
        'reminder_1h_sent_at',
        'room_alert_sent_at',
        // Session feedback & ratings (2026-10-04).
        'client_joined_at',
        'rating_reminder_sent_at',
        // Richer session notes (2026-10-06).
        'session_number',
        'interventions_used',
        'homework',
        'goals_progress',
        'closed_at',
        'referral_facility_id',
        'referred_at',
        'referral_assigned_at',
        // In-person sessions (2026-10-07).
        'session_mode',
        'venue_id',
        'location_details',
    ];

    public const MODE_VIDEO = 'video';
    public const MODE_IN_PERSON = 'in_person';

    public const MODES = [
        self::MODE_VIDEO => 'Online (video)',
        self::MODE_IN_PERSON => 'In person',
    ];

    public const CLOSURE_OUTCOMES = [
        'schedule_next_session' => 'Schedule another session',
        'refer_to_specialist' => 'Refer out (e.g. rehab, psychiatry)',
        'case_closed' => 'Case closed',
        'continue_monitoring' => 'Continue monitoring, no action yet',
        'other' => 'Other',
    ];

    /**
     * Notifications (added 2026-09-30): one central hook instead of edits in every
     * controller that changes a session's status (admin approve/assign, the lobby
     * router, …). Notifier never throws, so this can't break any of those actions.
     */
    protected static function booted(): void
    {
        static::created(function (TherapySession $session) {
            if ($session->status === 'scheduled') {
                app(\App\Services\Notifier::class)->sessionConfirmed($session);
            }
        });

        static::updated(function (TherapySession $session) {
            if (! $session->wasChanged('status')) {
                return;
            }

            $from = $session->getRawOriginal('status');

            if ($session->status === 'scheduled' && $from !== 'scheduled') {
                app(\App\Services\Notifier::class)->sessionConfirmed($session);
            } elseif ($session->status === 'cancelled' && $from === 'pending_approval') {
                app(\App\Services\Notifier::class)->bookingDeclined($session);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'reminder_24h_sent_at' => 'datetime',
            'reminder_1h_sent_at' => 'datetime',
            'room_alert_sent_at' => 'datetime',
            'client_joined_at' => 'datetime',
            'rating_reminder_sent_at' => 'datetime',
            'session_number' => 'integer',
            'amount_charged' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'entered_lobby_at' => 'datetime',
            'routed_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'referred_at' => 'datetime',
            'referral_assigned_at' => 'datetime',
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

    public function intakeAssessment(): BelongsTo
    {
        return $this->belongsTo(IntakeAssessment::class);
    }

    /**
     * The session this one was recommended as a follow-up from, when a therapist
     * chose "Schedule another session" while closing it out (2026-09-26).
     */
    public function previousSession(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'previous_session_id');
    }

    /**
     * The other direction: the follow-up session created from this one, if any.
     */
    public function followUpSession(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TherapySession::class, 'previous_session_id');
    }

    /**
     * The facility this case was routed to, once the admin referral queue assigns one.
     * Only meaningful when closure_outcome === 'refer_to_specialist'.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'referral_facility_id');
    }

    /** The client's feedback on this session (added 2026-10-04). */
    public function rating(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SessionRating::class);
    }

    /** The place an in-person session happens (2026-10-07). */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function isInPerson(): bool
    {
        return $this->session_mode === self::MODE_IN_PERSON;
    }

    /** Has admin said where an in-person session happens yet? */
    public function hasLocation(): bool
    {
        return $this->venue_id !== null || filled($this->location_details);
    }

    /**
     * Where it happens, as text: "Venue, address — extra details". Null for video
     * sessions or when admin hasn't set it yet.
     */
    public function locationText(): ?string
    {
        if (! $this->isInPerson() || ! $this->hasLocation()) {
            return null;
        }

        return collect([$this->venue?->label(), $this->location_details])->filter()->join(' — ');
    }

    /** Price of one session of this kind, before vouchers (both are config values). */
    public function listPrice(): float
    {
        return (float) ($this->isInPerson()
            ? config('mindwell.in_person_session_price')
            : config('mindwell.one_off_session_price'));
    }

    /** When the session was due to finish (scheduled start + duration). */
    public function plannedEndAt(): ?\Illuminate\Support\Carbon
    {
        return $this->scheduled_at?->copy()->addMinutes($this->duration_minutes ?: (int) config('mindwell.session_duration_minutes', 70));
    }

    /**
     * Can the client be asked "how was it?" — only if they actually took part: they
     * joined the video room, or the therapist closed the session as completed.
     */
    public function isRateable(): bool
    {
        if ($this->status === 'cancelled' || ! $this->scheduled_at) {
            return false;
        }

        // Joined the room (they may leave early, so no "has it ended?" check here) or
        // the therapist has closed it as completed.
        return $this->client_joined_at !== null || $this->status === 'completed';
    }
}
