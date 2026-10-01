<?php

namespace App\Http\Controllers;

use App\Services\Notifier;
use App\Services\PandoraSms;
use App\Support\UgandaPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → notifications (added 2026-09-30): the person's mobile number for SMS
 * reminders, and on/off switches for email and SMS. Reached by clicking your own name
 * in the top bar. Works for clients and therapists alike.
 */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.notifications', [
            'user' => $request->user(),
            'phoneDisplay' => UgandaPhone::display($request->user()->phone),
            'smsLive' => app(PandoraSms::class)->isConfigured(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:20', UgandaPhone::rule()],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
        ]);

        $request->user()->update([
            'phone' => filled($validated['phone'] ?? null) ? UgandaPhone::normalize($validated['phone']) : null,
            'notify_email' => (bool) ($validated['notify_email'] ?? false),
            'notify_sms' => (bool) ($validated['notify_sms'] ?? false),
        ]);

        return redirect()->route('settings.notifications')->with('status', 'Your notification settings are saved.');
    }

    public function testSms(Request $request, Notifier $notifier): RedirectResponse
    {
        $phone = $request->user()->phone;
        if (! $phone) {
            return back()->with('error', 'Add your mobile number and save first.');
        }

        $result = $notifier->testSms($phone);

        return back()->with($result['ok'] ? 'status' : 'error', $result['ok']
            ? 'Test SMS sent to '.UgandaPhone::display($phone).'. It should arrive within a minute.'
            : "The test SMS wasn't sent: ".$result['detail']);
    }
}
