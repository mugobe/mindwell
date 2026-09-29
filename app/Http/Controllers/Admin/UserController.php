<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', compact('users'));
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
}
