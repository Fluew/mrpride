<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest('/login');
        }

        // Immediately block deactivated accounts
        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }

        // Check permissions:
        // - Direct role match
        // - 'admin' requested and user is superadmin or admin
        // - 'superadmin' requested and user is superadmin
        $isAuthorized = in_array($user->role, $roles, true);

        if (! $isAuthorized && in_array('admin', $roles, true) && $user->isAdmin()) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            \App\Services\AuditLogger::security(
                'auth.unauthorized_access',
                "User {$user->email} (role: {$user->role}) attempted unauthorized access to {$request->method()} /{$request->path()}.",
                $user,
                'danger',
                ['path' => $request->path(), 'method' => $request->method(), 'required_roles' => $roles],
                'blocked',
                $request
            );

            abort(403, 'Unauthorized access. Your assigned role lacks permissions for this operation.');
        }

        return $next($request);
    }
}
