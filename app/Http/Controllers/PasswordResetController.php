<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Forgot password?" (added 2026-10-04), on Laravel's own password broker and the
 * password_reset_tokens table that already exists from the users migration. Links
 * expire after 60 minutes (config/auth.php). The email itself is
 * App\Models\User::sendPasswordResetNotification().
 *
 * The "we've sent a link" message is identical whether or not the email has an
 * account, so the form can't be used to find out who uses Mindwell.
 */
class PasswordResetController extends Controller
{
    private const SENT = "If there's a Mindwell account for that email, we've sent it a link to reset the password. The link works for 60 minutes — check your spam folder too.";

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', self::SENT);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $resetUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser) {
                $user->forceFill([
                    'password' => $password, // hashed by the User model's cast
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($user));
                $resetUser = $user;
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            AuditLog::record($request, 'user.password_reset', ($resetUser?->name ?? 'A user').' reset their password with an email link.', [
                'user_id' => $resetUser?->id,
            ]);

            return redirect()->route('login')->with('status', 'Your password has been changed. You can log in now.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'This reset link is invalid or has expired. Please ask for a new one.']);
    }
}
