<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin → Vouchers (2026-10-07, roadmap 25). Every rule is a field on the voucher, so
 * Dr. Ntabade's team can run any promotion without code changes. Vouchers are never
 * deleted once used (the receipts point at them) — switch them off instead.
 */
class VoucherController extends Controller
{
    public function index(): View
    {
        $vouchers = Voucher::withCount('redemptions')
            ->withSum('redemptions', 'discount_amount')
            ->with('client')
            ->latest()
            ->get();

        return view('admin.vouchers.index', compact('vouchers'));
    }

    public function create(): View
    {
        return view('admin.vouchers.form', [
            'voucher' => new Voucher([
                'discount_type' => Voucher::TYPE_PERCENT,
                'applies_to_sessions' => true,
                'max_uses_per_client' => 1,
                'is_active' => true,
            ]),
            'redemptions' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $voucher = Voucher::create($data);

        AuditLog::record($request, 'voucher.created', "Created voucher {$voucher->code} ({$voucher->discountLabel()}).", ['voucher_id' => $voucher->id]);

        return redirect()->route('admin.vouchers.index')->with('status', "Voucher {$voucher->code} created.");
    }

    public function edit(Voucher $voucher): View
    {
        return view('admin.vouchers.form', [
            'voucher' => $voucher->load('client'),
            'redemptions' => $voucher->redemptions()->with('clientProfile.user', 'paymentTransaction')->latest()->limit(100)->get(),
        ]);
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $voucher->update($this->validated($request, $voucher));

        AuditLog::record($request, 'voucher.updated', "Updated voucher {$voucher->code} ({$voucher->discountLabel()}).", ['voucher_id' => $voucher->id]);

        return redirect()->route('admin.vouchers.index')->with('status', "Voucher {$voucher->code} saved.");
    }

    public function toggle(Request $request, Voucher $voucher): RedirectResponse
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        AuditLog::record($request, 'voucher.toggled', 'Switched voucher '.$voucher->code.($voucher->is_active ? ' on.' : ' off.'), ['voucher_id' => $voucher->id]);

        return back()->with('status', "Voucher {$voucher->code} is now ".($voucher->is_active ? 'on' : 'off').'.');
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        $request->merge(['code' => Voucher::normalizeCode($request->input('code')) ?: null]);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vouchers', 'code')->ignore($voucher?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in([Voucher::TYPE_PERCENT, Voucher::TYPE_FIXED])],
            'discount_value' => ['required', 'numeric', 'min:1', $request->input('discount_type') === Voucher::TYPE_PERCENT ? 'max:100' : 'max:100000000'],
            'max_discount' => ['nullable', 'numeric', 'min:1'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'client_email' => ['nullable', 'email'],
            'max_uses_total' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_client' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => array_merge(['nullable', 'date'], $request->filled('starts_at') ? ['after_or_equal:starts_at'] : []),
        ], [
            'code.regex' => 'Use letters, numbers, - or _ only (no spaces).',
            'discount_value.max' => 'A percentage can be at most 100.',
        ]);

        $applies = [
            'applies_to_sessions' => $request->boolean('applies_to_sessions'),
            'applies_to_plans' => $request->boolean('applies_to_plans'),
            'applies_to_renewals' => $request->boolean('applies_to_renewals'),
        ];
        if (! in_array(true, $applies, true)) {
            throw ValidationException::withMessages(['applies_to_sessions' => 'Tick at least one: sessions, new plans or renewals.']);
        }

        $clientId = null;
        if (filled($data['client_email'] ?? null)) {
            $clientId = User::where('email', $data['client_email'])->where('role', 'client')->value('id');
            if (! $clientId) {
                throw ValidationException::withMessages(['client_email' => 'No client account uses that email.']);
            }
        }

        unset($data['client_email']);

        return array_merge($data, $applies, [
            // Blank on edit keeps the current code; blank on create generates one.
            'code' => $data['code'] ?? $voucher?->code ?? $this->generateCode(),
            'max_discount' => $data['discount_type'] === Voucher::TYPE_PERCENT ? ($data['max_discount'] ?? null) : null,
            'client_user_id' => $clientId,
            'new_clients_only' => $request->boolean('new_clients_only'),
            // "Unlimited" per client is an empty box.
            'max_uses_per_client' => $data['max_uses_per_client'] ?? null,
            // An end date means "through the end of that day".
            'ends_at' => filled($data['ends_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['ends_at'])->endOfDay() : null,
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'MW'.strtoupper(Str::random(6));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
