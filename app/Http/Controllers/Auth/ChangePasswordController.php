<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    /**
     * Show mandatory password change screen.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdminOrStaff(), 403);

        $daysSinceChange = $user->password_changed_at ? $user->password_changed_at->diffInDays(now()) : 90;
        $reason = $user->must_change_password
            ? 'Initial login: You are using a temporary initial password. Please choose a permanent, secure password to continue.'
            : 'Security policy: Your password was last changed ' . $daysSinceChange . ' days ago and has expired (90-day rotation required).';

        return Inertia::render('auth/ChangePassword', [
            'reason' => $reason,
            'isTemporary' => (bool) $user->must_change_password,
            'notice' => session('notice'),
        ]);
    }

    /**
     * Process password change.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdminOrStaff(), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        // Validate current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The provided current password does not match our records.']);
        }

        // Prevent reuse of current password
        if (Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['password' => 'Your new password cannot be the same as your current password.']);
        }

        // Prevent reuse of recent passwords from history
        $history = $user->password_history ?? [];
        foreach ($history as $oldHash) {
            if (Hash::check($validated['password'], $oldHash)) {
                return back()->withErrors(['password' => 'You cannot reuse any of your recent passwords. Please choose a new, unique password.']);
            }
        }

        // Update history (keep last 3 passwords)
        $history[] = $user->password;
        if (count($history) > 3) {
            $history = array_slice($history, -3);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'password_history' => $history,
        ])->save();

        // Invalidate other active sessions for this user
        $currentSessionId = $request->session()->getId();
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        AuditLogger::log(
            'auth.password.changed',
            "Password successfully updated for {$user->email}. Other active sessions invalidated.",
            $user,
            ['reason' => $user->must_change_password ? 'initial_change' : 'expired_rotation'],
            $request
        );

        return redirect('/dashboard')->with('success', 'Your password has been successfully updated and secured.');
    }
}
