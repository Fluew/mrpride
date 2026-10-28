<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Encrypt Inertia's client-side history entries for authenticated sessions.
     *
     * Page props are otherwise stored in plain text in the browser's history state, which
     * the Back button restores without contacting the server. Encrypted entries become
     * unreadable once the key is cleared on logout (see LogoutResponse).
     */
    public function handle(Request $request, Closure $next)
    {
        Inertia::encryptHistory($request->user() !== null);

        return parent::handle($request, $next);
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // Modules the current user may access (read from the database on each request).
                'modules' => fn () => $user ? $user->assignedModules() : [],
                // From User::isAdmin(). UI hints only; the server enforces both (role / admin_delete middleware).
                'is_admin' => (bool) $user?->isAdmin(),
                'can_delete' => (bool) $user?->isAdmin(),
                'is_first_login' => fn () => (bool) $request->session()->get('is_first_login', false),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'device_notice' => fn () => $request->session()->get('device_notice'),
            ],
            'device_notice' => fn () => $request->session()->get('device_notice'),
        ];
    }
}
