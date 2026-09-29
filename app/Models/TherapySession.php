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
        'closed_at',
        'referral_facility_id',
        'referred_at',
        'referral_assigned_at',
    ];

    public const CLOSURE_OUTCOMES = [
        'schedule_next_session' => 'Schedule another session',
        'refer_to_specialist' => 'Refer out (e.g. rehab, psychiatry)',
        'case_closed' => 'Case closed',
        'continue_monitoring' => 'Continue monitoring, no action yet',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
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
}
