<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::orderBy('price')->get();

        // 2026-10-08 redesign: how many clients are on each plan right now.
        $activeCounts = \App\Models\ClientSubscription::where('status', 'active')
            ->selectRaw('subscription_plan_id, count(*) as n')
            ->groupBy('subscription_plan_id')
            ->pluck('n', 'subscription_plan_id');

        return view('admin.plans.index', compact('plans', 'activeCounts'));
    }

    public function create(): View
    {
        return view('admin.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $plan = SubscriptionPlan::create($validated);

        AuditLog::record($request, 'subscription_plan.created', "Created plan \"{$plan->name}\"", ['plan_id' => $plan->id]);

        return redirect()->route('admin.plans.index')->with('status', $plan->name.' was added.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validated($request);

        $plan->update($validated);

        AuditLog::record($request, 'subscription_plan.updated', "Updated plan \"{$plan->name}\"", ['plan_id' => $plan->id]);

        return redirect()->route('admin.plans.index')->with('status', $plan->name.' was updated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'string', 'in:weekly,monthly'],
            'sessions_included' => ['required', 'integer', 'min:1', 'max:100'],
            'features' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['features'] = collect(explode("\n", $validated['features'] ?? ''))
            ->map(fn ($f) => trim($f))
            ->filter()
            ->values()
            ->all() ?: null;

        return $validated;
    }
}
