<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /** 2026-10-08 redesign: area tabs → action prefixes. */
    public const AREAS = [
        'accounts' => ['label' => 'Accounts', 'prefixes' => ['user.', 'therapist.']],
        'sessions' => ['label' => 'Sessions', 'prefixes' => ['session.', 'time_off.']],
        'care' => ['label' => 'Care', 'prefixes' => ['crisis_flag.', 'referral.']],
        'money' => ['label' => 'Money', 'prefixes' => ['payment.', 'payments.', 'voucher.', 'subscription_plan.', 'client_subscription.', 'employer_account.', 'employer_seat.']],
        'setup' => ['label' => 'Setup', 'prefixes' => ['venue.', 'facility.', 'intake_settings.']],
    ];

    public function index(Request $request): View
    {
        $area = array_key_exists((string) $request->query('area'), self::AREAS) ? (string) $request->query('area') : null;
        $q = trim((string) $request->query('q', $request->query('action', '')));

        $logs = AuditLog::with('user')
            ->when($area, fn ($query) => $query->where(function ($w) use ($area) {
                foreach (self::AREAS[$area]['prefixes'] as $prefix) {
                    $w->orWhere('action', 'like', $prefix.'%');
                }
            }))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('action', 'like', '%'.$q.'%')
                ->orWhere('description', 'like', '%'.$q.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%'))))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log.index', ['logs' => $logs, 'areas' => self::AREAS, 'area' => $area, 'q' => $q]);
    }
}
