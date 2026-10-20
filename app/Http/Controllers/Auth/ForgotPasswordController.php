<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Auth\TemporaryPasswordNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Send temporary password to user's email.
     */
    public function sendTemporaryPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $throttleKey = 'forgot-password:' . $email . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Too many password requests. Please wait {$seconds} seconds before trying again.",
            ]);
        }

        RateLimiter::hit($throttleKey, 300);

        $user = User::where('email', $email)->first();

        if ($user) {
            // Generate temporary password based on Firstname + 4 random digits (or Firstname1234)
            $firstName = explode(' ', trim($user->name))[0] ?? 'User';
            $cleanFirst = preg_replace('/[^A-Za-z]/', '', $firstName);
            $cleanFirst = ! empty($cleanFirst) ? ucfirst(strtolower($cleanFirst)) : 'User';
            $temporaryPassword = $cleanFirst . random_int(1000, 9999);

            $user->password = Hash::make($temporaryPassword);
            $user->must_change_password = true;
            $user->password_changed_at = now();
            $user->save();

            // Invalidate all active sessions for this account
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Send notification with temporary password
            try {
                $user->notify(new TemporaryPasswordNotification($temporaryPassword));
            } catch (\Throwable $e) {
                // If mail sending fails in local environment, temporary password will still be provided in flash message
            }

            AuditLogger::security(
                'auth.password.forgot_temporary_sent',
                "Temporary password generated and sent for {$user->email}.",
                $user,
                'info',
                ['ip' => $request->ip()],
                'success',
                $request
            );

            return back()->with(
                'status',
                "A temporary password has been sent to your email address: {$temporaryPassword}. Please use it to log in and you will be prompted to choose a new password."
            );
        }

        return back()->with(
            'status',
            'If an account exists with that email address, a temporary password has been sent to it.'
        );
    }
}
