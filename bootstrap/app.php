<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
            \App\Http\Middleware\EnforceInactivityTimeout::class,
        ]);

        $middleware->alias([
            'admin_or_staff' => \App\Http\Middleware\EnsureAdminOrStaff::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'device_verified' => \App\Http\Middleware\EnsureDeviceVerified::class,
            'password_valid' => \App\Http\Middleware\EnsurePasswordNotExpired::class,
            'no_cache' => \App\Http\Middleware\NoHttpCache::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin*', 'staff*', 'dashboard*', '*/dashboard*')) {
                abort(404);
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Not Found'], 404);
            }

            return \Inertia\Inertia::render('NotFound')
                ->toResponse($request)
                ->setStatusCode(404);
        });
    })->create();
