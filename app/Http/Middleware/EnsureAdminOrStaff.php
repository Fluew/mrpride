<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest('/login');
        }

        // Deactivated accounts cannot access workspace
        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }

        // Regular users attempting to access admin or staff routes are redirected to home
        if (! $user->isAdminOrStaff()) {
            \App\Services\AuditLogger::security(
                'auth.unauthorized_access',
                "Regular user {$user->email} redirected to home from admin/staff path {$request->path()}.",
                $user,
                'warning',
                ['path' => $request->path()],
                'blocked',
                $request
            );

            return redirect('/');
        }

        return $next($request);
    }
}
