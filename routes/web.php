<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\DeviceVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\Security\AuditLogController;
use App\Http\Controllers\Security\TrustedDeviceController;
use App\Http\Controllers\Security\UserSessionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

// Public routes
Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/products', 'Products/Index')->name('products.index');
Route::inertia('/company', 'Company/Index')->name('company.index');
Route::get('/quotation', [PublicQuotationController::class, 'create'])->name('quotations.create');
Route::post('/quotation', [PublicQuotationController::class, 'store'])->middleware('throttle:10,1')->name('quotations.store');

// Password change flow (authenticated)
Route::middleware(['auth'])->group(function () {
    Route::get('/password/change', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::put('/password/change', [ChangePasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.change.update');
});

// Forgot password: send temporary password
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendTemporaryPassword'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('password.email');

// Public device authorization links (single-use email link and superadmin enrollment invitation)
Route::get('/device/access/{token}', [DeviceVerificationController::class, 'authorizeDevice'])->name('device.access');
Route::get('/device/enroll/{token}', [DeviceVerificationController::class, 'authorizeDevice'])->name('device.enroll');

// Authenticated dashboard redirect
Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    if (! $user) {
        return redirect('/login');
    }

    // Regular users attempting to access admin/staff dashboard are redirected to home
    if (! $user->isAdminOrStaff()) {
        return redirect('/');
    }

    // Admin/Staff device verification check
    $deviceUuid = $request->cookie('mrpride_device_id');
    $device = $deviceUuid
        ? $user->trustedDevices()->where('device_uuid', $deviceUuid)->first()
        : null;

    if (! $device || ! $device->isApproved()) {
        $token = \Illuminate\Support\Str::random(48);
        $expiresAt = now()->addHours(24);
        $assignedUuid = $deviceUuid ?: (string) \Illuminate\Support\Str::uuid();
        cookie()->queue(cookie()->forever('mrpride_device_id', $assignedUuid, httpOnly: true, sameSite: 'lax'));

        $deviceName = 'Web Browser';
        $agent = $request->userAgent() ?? '';
        if (str_contains($agent, 'Windows')) $deviceName = 'Windows Device';
        elseif (str_contains($agent, 'Macintosh')) $deviceName = 'macOS Device';
        elseif (str_contains($agent, 'Linux')) $deviceName = 'Linux Device';
        elseif (str_contains($agent, 'Android')) $deviceName = 'Android Device';
        elseif (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) $deviceName = 'iOS Device';

        \App\Models\TrustedDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_uuid' => $assignedUuid],
            [
                'device_name' => $deviceName,
                'ip_address' => $request->ip(),
                'status' => 'pending',
                'enrollment_token' => $token,
                'enrollment_token_expires_at' => $expiresAt,
            ]
        );

        $accessUrl = url("/device/access/{$token}");
        try {
            $user->notify(new \App\Notifications\Auth\DeviceAccessNotification(
                accessUrl: $accessUrl,
                deviceName: $deviceName,
                ipAddress: $request->ip() ?? 'Unknown',
                expiresAt: $expiresAt->format('M d, Y H:i')
            ));
        } catch (\Throwable) {}

        auth()->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $parts = explode('@', $user->email);
        $maskedEmail = substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? '');

        return redirect('/')->with(
            'device_notice',
            "Unauthorized device detected. A secure authorization link has been sent to your email ({$maskedEmail}). Open the link on this device to access the login page and dashboard."
        );
    }

    // Check mandatory password change or expiration
    if ($user->isPasswordExpired()) {
        return redirect()->route('password.change');
    }

    $team = $user->currentTeam ?: $user->teams()->first() ?: \App\Models\Team::first();
    if ($team) {
        $user->switchTeam($team);
        return redirect("/{$team->slug}/dashboard");
    }

    return redirect('/');
})->middleware(['auth'])->name('dashboard.redirect');

// Admin & Staff Workspace routes with full security middleware stack
Route::prefix('{current_team}')
    ->middleware([
        'auth',
        'verified',
        EnsureTeamMembership::class,
        'admin_or_staff',
        'device_verified',
        'password_valid',
        'no_cache',
    ])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Centralized User Management & Security Administration Module (Admin & Superadmin)
        Route::get('users', [UserController::class, 'index'])->middleware('role:admin')->name('users.index');
        Route::post('users', [UserController::class, 'store'])->middleware('role:admin')->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('role:admin')->name('users.update');
        Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->middleware('role:superadmin')->name('users.status');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('role:admin')->name('users.destroy');

        // Centralized Security Actions inside User Management (Superadmin only)
        Route::delete('users/sessions/{sessionId}', [UserController::class, 'revokeSession'])->middleware('role:superadmin')->name('users.sessions.destroy');
        Route::post('users/{user}/sessions/revoke', [UserController::class, 'revokeUserSessions'])->middleware('role:superadmin')->name('users.sessions.revoke-user');
        Route::post('users/devices/enroll', [UserController::class, 'enrollDevice'])->middleware('role:superadmin')->name('users.devices.enroll');
        Route::post('users/devices/{device}/approve', [UserController::class, 'approveDevice'])->middleware('role:superadmin')->name('users.devices.approve');
        Route::post('users/devices/{device}/revoke', [UserController::class, 'revokeDevice'])->middleware('role:superadmin')->name('users.devices.revoke');
        Route::delete('users/devices/{device}', [UserController::class, 'destroyDevice'])->middleware('role:superadmin')->name('users.devices.destroy');
        Route::post('users/recovery-key/regenerate', [UserController::class, 'regenerateRecoveryKey'])->middleware('role:superadmin')->name('users.recovery-key.regenerate');

        // Backwards-compatible security redirects to centralized tabs
        Route::redirect('security/devices', 'users?tab=devices');
        Route::redirect('security/sessions', 'users?tab=sessions');
        Route::redirect('security/audit-logs', 'users?tab=audit');

        // Inventory Management Module (Staff & Admin accessible)
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory/products', [InventoryController::class, 'store'])->name('inventory.products.store');
        Route::put('inventory/products/{product}', [InventoryController::class, 'update'])->name('inventory.products.update');
        Route::delete('inventory/products/{product}', [InventoryController::class, 'destroy'])->name('inventory.products.destroy');
        Route::post('inventory/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
        Route::post('inventory/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');
        Route::post('inventory/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
        Route::get('inventory/history', [InventoryController::class, 'history'])->name('inventory.history');
        Route::post('inventory/import-csv', [InventoryController::class, 'importCsv'])->name('inventory.import-csv');
        Route::get('inventory/export-csv', [InventoryController::class, 'exportCsv'])->name('inventory.export-csv');

        // Suppliers & AHP Evaluation Module (Admin only - Staff restricted)
        Route::middleware('role:admin')->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
            Route::post('suppliers/ahp', [SupplierController::class, 'runAhp'])->name('suppliers.ahp');
        });

        // Quotation Management Module (Staff & Admin accessible)
        Route::get('quotations', [QuotationController::class, 'index'])->name('admin.quotations.index');
        Route::post('quotations', [QuotationController::class, 'store'])->name('admin.quotations.store');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('admin.quotations.update');
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('admin.quotations.updateStatus');
        Route::post('quotations/{quotation}/convert-to-sale', [QuotationController::class, 'convertToSale'])->name('admin.quotations.convertToSale');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('admin.quotations.destroy');

        // Procurement Module (Staff & Admin accessible)
        Route::get('procurement', [PurchaseRequestController::class, 'index'])->name('procurement.index');
        Route::post('procurement', [PurchaseRequestController::class, 'store'])->name('procurement.store');
        Route::patch('procurement/{purchaseRequest}/status', [PurchaseRequestController::class, 'updateStatus'])->name('procurement.updateStatus');
        Route::delete('procurement/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('procurement.destroy');

        // Sales & POS Module (Staff & Admin accessible)
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

        // Demand Forecasting Module (Staff & Admin accessible)
        Route::get('forecast', [ForecastController::class, 'index'])->name('forecast.index');
        Route::get('forecast/export-csv', [ForecastController::class, 'exportCsv'])->name('forecast.export-csv');

        // Reports & Analytics Module (Admin only - Staff restricted)
        Route::middleware('role:admin')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export/inventory', [ReportController::class, 'exportInventory'])->name('reports.export.inventory');
            Route::get('reports/export/low-stock', [ReportController::class, 'exportLowStock'])->name('reports.export.low-stock');
            Route::get('reports/export/quotations-sales', [ReportController::class, 'exportQuotationsSales'])->name('reports.export.quotations-sales');
            Route::get('reports/export/forecast', [ReportController::class, 'exportForecast'])->name('reports.export.forecast');
            Route::get('reports/export/procurement', [ReportController::class, 'exportProcurement'])->name('reports.export.procurement');
            Route::get('reports/export/ahp', [ReportController::class, 'exportAhp'])->name('reports.export.ahp');
            Route::get('reports/export/all', [ReportController::class, 'exportAll'])->name('reports.export.all');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
