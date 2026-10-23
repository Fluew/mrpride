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
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            abort(404);
        }

        // Immediately block deactivated accounts
        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(404);
        }

        // Admin can access ALL modules unconditionally
        if ($user->isAdmin()) {
            return $next($request);
        }

        // For staff or other roles, check against requested roles
        $isAuthorized = in_array($user->role, $roles, true);

        // Also check if any module-level shorthand was passed (e.g. 'inventory' matches 'staff_inventory')
        if (! $isAuthorized) {
            foreach ($roles as $requiredRole) {
                if ($user->role === 'staff_' . $requiredRole || ($user->role === 'staff' && $requiredRole === 'inventory')) {
                    $isAuthorized = true;
                    break;
                }
            }
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

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized access. Your assigned role lacks permissions for this operation.'], 403);
            }

            // For browser / non-admin attempting admin module
            if (in_array('admin', $roles, true)) {
                abort(404);
            }

            abort(403, 'Unauthorized access. Your assigned role lacks permissions for this operation.');
        }

        return $next($request);
    }
}
