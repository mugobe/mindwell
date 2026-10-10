<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Change password while logged in (added 2026-10-04). Everyone can use it from
 * Settings; someone with a temporary password from an admin is sent here first by
 * App\Http\Middleware\EnsurePasswordChanged.
 */
class PasswordChangeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.password', ['forced' => (bool) $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => "That isn't your current password.",
            'password.different' => 'Choose a password that is different from the current one.',
        ]);

        $user = $request->user();
        $wasForced = (bool) $user->must_change_password;

        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        // Mobile app (2026-10-09): changing the password on the website signs out the app.
        rescue(fn () => $user->tokens()->delete(), null, false);

        AuditLog::record($request, 'user.password_changed', $user->name.' changed their password'.($wasForced ? ' (replacing a temporary one).' : '.'), [
            'user_id' => $user->id,
        ]);

        return redirect()->route('dashboard')->with('status', 'Your new password is saved.');
    }
}
