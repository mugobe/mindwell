<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\MindwellNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /** Filter tabs on the users page (2026-10-08 redesign). */
    public const TABS = ['all' => 'All', 'client' => 'Clients', 'therapist' => 'Therapists', 'admin' => 'Admins', 'suspended' => 'Suspended'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'all';
        $q = trim((string) $request->query('q', ''));

        $applyTab = fn ($query, string $t) => match ($t) {
            'suspended' => $query->where('status', 'suspended'),
            'all' => $query,
            default => $query->where('role', $t),
        };

        $counts = [];
        foreach (array_keys(self::TABS) as $t) {
            $counts[$t] = $applyTab(User::query(), $t)->count();
        }

        $users = $applyTab(User::query(), $tab)
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->with('clientProfile')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'tabs' => self::TABS,
            'tab' => $tab,
            'counts' => $counts,
            'q' => $q,
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, "You can't suspend your own account.");

        $user->update(['status' => 'suspended']);

        AuditLog::record($request, 'user.suspended', $user->name.' ('.$user->email.') was suspended.', ['user_id' => $user->id]);

        return back()->with('status', $user->name.' has been suspended.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $user->update(['status' => 'active']);

        AuditLog::record($request, 'user.reactivated', $user->name.' ('.$user->email.') was reactivated.', ['user_id' => $user->id]);

        return back()->with('status', $user->name.' has been reactivated.');
    }

    /**
     * Email the user the same reset link as "Forgot password?" (added 2026-10-04).
     * The admin never sees or sets the password.
     */
    public function sendResetLink(Request $request, User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('error', 'A reset link was sent to '.$user->name.' less than a minute ago — please wait a moment before sending another.');
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', "The reset link couldn't be sent to ".$user->name.'.');
        }

        AuditLog::record($request, 'user.reset_link_sent', 'Sent a password reset link to '.$user->name.' ('.$user->email.').', ['user_id' => $user->id]);

        return back()->with('status', 'A password reset link has been emailed to '.$user->email.'. It works for 60 minutes.');
    }

    /**
     * For someone who can't get to their email (added 2026-10-04, decided with Collins):
     * set a one-off temporary password, shown to the admin ONCE to pass on in person or
     * by phone. The user must choose their own at their next login
     * (EnsurePasswordChanged), their "remember me" logins are ended, and they get an
     * email saying an administrator did this.
     */
    public function temporaryPassword(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Use "Change password" in Settings for your own account.');

        $temporary = self::readablePassword();

        $user->forceFill([
            'password' => $temporary, // hashed by the User model's cast
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        // Mobile app (2026-10-09): sign the person out on every phone too.
        rescue(fn () => $user->tokens()->delete(), null, false);

        AuditLog::record($request, 'user.temporary_password_set', 'Set a temporary password for '.$user->name.' ('.$user->email.').', ['user_id' => $user->id]);

        try {
            $user->notify(new MindwellNotice(
                'Your Mindwell password was reset',
                'Hi '.(trim(explode(' ', trim((string) $user->name))[0] ?? '') ?: 'there').',',
                [
                    'A Mindwell administrator has set a temporary password on your account, as requested.',
                    "You'll be asked to choose your own password the next time you log in.",
                ],
                'Log in to Mindwell',
                route('login'),
                "If you didn't ask for this, please contact Mindwell straight away.",
            ));
        } catch (Throwable $e) {
            Log::warning('Temporary-password notice email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return back()->with('temp_password', ['name' => $user->name, 'email' => $user->email, 'password' => $temporary]);
    }

    /** 10 characters, no look-alikes (0/O, 1/l/I), easy to read out over the phone. */
    private static function readablePassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
