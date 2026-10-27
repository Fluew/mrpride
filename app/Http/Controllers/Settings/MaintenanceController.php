<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    /**
     * Show Maintenance Mode settings and control dashboard (Admin only).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Maintenance controls require administrative privileges.');

        return Inertia::render('settings/Maintenance', [
            'status' => MaintenanceService::getStatus(),
            'isSuperAdmin' => (bool) $user->isAdmin(),
        ]);
    }

    /**
     * Step 1 & 2 — Admin enables maintenance mode.
     */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Maintenance controls require administrative privileges.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'expected_return_time' => ['nullable', 'string', 'max:100'],
        ]);

        MaintenanceService::enable($validated, $user);

        return back()->with('success', 'Maintenance Mode has been enabled. The system is protected and displaying the maintenance notice.');
    }

    /**
     * Reopen system and disable maintenance mode.
     */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Maintenance controls require administrative privileges.');

        MaintenanceService::disable($user);

        return back()->with('success', 'Maintenance Mode disabled. MRP ElectroDesk is now reopened and fully operational.');
    }

    /**
     * Step 4 — Run pre-reopening system readiness verification checks.
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Maintenance controls require administrative privileges.');

        $results = MaintenanceService::verifyReadiness();

        return response()->json($results);
    }
}
