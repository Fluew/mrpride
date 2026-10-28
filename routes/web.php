<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\DeviceVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MaterialListController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RecycleBinController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/products', 'Products/Index')->name('products.index');
Route::inertia('/company', 'Company/Index')->name('company.index');
Route::inertia('/contact-us', 'Contact/Index')->name('contact.index');
Route::get('/quotation', [PublicQuotationController::class, 'create'])->name('quotations.create');
Route::post('/quotation', [PublicQuotationController::class, 'store'])->middleware('throttle:10,1')->name('quotations.store');
// Material list upload on the quotation form: read the file and return the lines for review.
Route::post('/quotation/materials/extract', [MaterialListController::class, 'extract'])->middleware('throttle:10,1')->name('quotations.materials.extract');
Route::post('/quotation/materials/remap', [MaterialListController::class, 'remap'])->middleware('throttle:30,1')->name('quotations.materials.remap');

// Password change flow (authenticated)
Route::middleware(['auth', 'no_cache'])->group(function () {
    Route::get('/password/change', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::put('/password/change', [ChangePasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.change.update');
});

// Forgot password OTP flow via Resend
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('password.email');

Route::post('/forgot-password/check-otp', [ForgotPasswordController::class, 'checkOtp'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('password.check-otp');

Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtpAndResetPassword'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('password.verify-otp');

Route::post('/forgot-password/resend-otp', [ForgotPasswordController::class, 'resendOtp'])
    ->middleware(['guest', 'throttle:4,1'])
    ->name('password.resend-otp');

// Fortify's link-based reset is replaced by the OTP flow above. Its endpoint must stay closed:
// the OTP is stored in the same password_reset_tokens table, so POST /reset-password would accept
// the 6-digit code with no guess limit. Names are kept so any route('password.reset') still resolves.
Route::get('/reset-password/{token}', fn () => redirect()->route('password.request'))
    ->middleware('guest')
    ->name('password.reset');
Route::post('/reset-password', fn () => abort(404))
    ->name('password.update');

// Public device enrollment & access-denied routes
Route::get('/access-denied', [DeviceVerificationController::class, 'accessDenied'])->name('device.access-denied');
Route::get('/device/enroll', [DeviceVerificationController::class, 'showEnroll'])->name('device.enroll');
Route::post('/device/enroll', [DeviceVerificationController::class, 'processEnroll'])->middleware('throttle:10,1')->name('device.enroll-submit');
Route::get('/device/access/{token}', [DeviceVerificationController::class, 'authorizeDevice'])->name('device.access');
Route::get('/device/enroll/{token}', [DeviceVerificationController::class, 'showEnrollWithToken'])->name('device.enroll-token');

// Admin role route convention
Route::prefix('admin')->middleware(['device_verified'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(404);
        }
        $team = $user->currentTeam ?: $user->teams()->first() ?: Team::first();
        $teamSlug = $team?->slug ?? 'mr-pride';

        return redirect("/{$teamSlug}/dashboard");
    })->name('admin.dashboard');

    Route::any('{any}', function () {
        abort(404);
    })->where('any', '.*');
});

// Staff role route convention
Route::prefix('staff')->middleware(['device_verified'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        if (! $user || ! $user->isStaff()) {
            abort(404);
        }
        $team = $user->currentTeam ?: $user->teams()->first() ?: Team::first();
        $teamSlug = $team?->slug ?? 'mr-pride';

        return redirect("/{$teamSlug}/".$user->assignedModuleSlug());
    })->name('staff.dashboard');

    Route::any('{any}', function () {
        abort(404);
    })->where('any', '.*');
});

// Authenticated dashboard redirect
Route::get('/dashboard', function (Request $request) {
    $user = $request->user();
    if (! $user || ! $user->isAdminOrStaff()) {
        return redirect('/');
    }

    // Check mandatory password change or expiration
    if ($user->isPasswordExpired()) {
        return redirect()->route('password.change');
    }

    $team = $user->currentTeam ?: $user->teams()->first() ?: Team::first();
    $teamSlug = $team?->slug ?? 'mr-pride';
    if ($team) {
        $user->switchTeam($team);
        if ($user->isAdmin()) {
            return redirect("/{$teamSlug}/dashboard");
        }

        return redirect("/{$teamSlug}/".$user->assignedModuleSlug());
    }

    return redirect('/');
})->middleware(['device_verified', 'no_cache'])->name('dashboard.redirect');

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
        // Workspace Overview (Admin default, Staff redirected to their assigned module)
        Route::get('dashboard', function (Request $request) {
            $user = $request->user();
            if ($user && $user->isStaff()) {
                $teamSlug = $request->route('current_team') ?: ($user->currentTeam?->slug ?? 'mr-pride');

                return redirect("/{$teamSlug}/".$user->assignedModuleSlug());
            }

            return app(DashboardController::class)($request);
        })->name('dashboard');

        // Centralized User Management & Security Administration Module (Admin full access)
        Route::middleware('role:admin')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            // Centralized Security Actions inside User Management (Admin accessible)
            Route::delete('users/sessions/{sessionId}', [UserController::class, 'revokeSession'])->name('users.sessions.destroy');
            Route::post('users/{user}/sessions/revoke', [UserController::class, 'revokeUserSessions'])->name('users.sessions.revoke-user');
            Route::post('users/devices/enroll', [UserController::class, 'enrollDevice'])->name('users.devices.enroll');
            Route::patch('users/devices/{device}/rename', [UserController::class, 'renameDevice'])->name('users.devices.rename');
            Route::post('users/devices/{device}/approve', [UserController::class, 'approveDevice'])->name('users.devices.approve');
            Route::post('users/devices/{device}/revoke', [UserController::class, 'revokeDevice'])->name('users.devices.revoke');
            Route::delete('users/devices/{device}', [UserController::class, 'destroyDevice'])->name('users.devices.destroy');
            Route::post('users/recovery-key/regenerate', [UserController::class, 'regenerateRecoveryKey'])->name('users.recovery-key.regenerate');

            // Centralized Archive / Recycle Bin Module (Admin only)
            Route::get('recycle-bin', [RecycleBinController::class, 'index'])->name('recycle-bin.index');
            Route::post('recycle-bin/bulk-restore', [RecycleBinController::class, 'bulkRestore'])->name('recycle-bin.bulk-restore');
            Route::post('recycle-bin/bulk-force-delete', [RecycleBinController::class, 'bulkForceDelete'])->name('recycle-bin.bulk-force-delete');
            Route::post('recycle-bin/empty', [RecycleBinController::class, 'emptyBin'])->name('recycle-bin.empty');
            Route::post('recycle-bin/{type}/{id}/restore', [RecycleBinController::class, 'restore'])->name('recycle-bin.restore');
            Route::delete('recycle-bin/{type}/{id}/force', [RecycleBinController::class, 'forceDelete'])->name('recycle-bin.force-delete');
        });

        // Backwards-compatible security redirects to centralized tabs
        Route::redirect('security/devices', 'users?tab=devices');
        Route::redirect('security/sessions', 'users?tab=sessions');
        Route::redirect('security/audit-logs', 'users?tab=audit');

        // Inventory Management Module (Admin and Inventory Staff)
        Route::middleware('role:admin,staff_inventory,staff')->group(function () {
            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::post('inventory/products', [InventoryController::class, 'store'])->name('inventory.products.store');
            Route::put('inventory/products/{product}', [InventoryController::class, 'update'])->name('inventory.products.update');
            Route::delete('inventory/products/{product}', [InventoryController::class, 'destroy'])->middleware('admin_delete')->name('inventory.products.destroy');
            Route::post('inventory/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
            Route::post('inventory/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');
            Route::post('inventory/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
            Route::get('inventory/history', [InventoryController::class, 'history'])->name('inventory.history');
            Route::post('inventory/import-csv', [InventoryController::class, 'importCsv'])->name('inventory.import-csv');
            Route::get('inventory/export-csv', [InventoryController::class, 'exportCsv'])->name('inventory.export-csv');
            Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
        });

        // Suppliers & AHP Evaluation Module (Admin and Suppliers Staff)
        Route::middleware('role:admin,staff_suppliers')->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('admin_delete')->name('suppliers.destroy');
            Route::post('suppliers/ahp', [SupplierController::class, 'runAhp'])->name('suppliers.ahp');
        });

        // Quotation Management Module (Admin and Quotations Staff)
        Route::middleware('role:admin,staff_quotations')->group(function () {
            Route::get('quotations', [QuotationController::class, 'index'])->name('admin.quotations.index');
            Route::post('quotations', [QuotationController::class, 'store'])->name('admin.quotations.store');
            Route::post('quotations/import-csv', [QuotationController::class, 'importCsv'])->name('admin.quotations.importCsv');
            Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('admin.quotations.update');
            Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('admin.quotations.updateStatus');
            Route::post('quotations/{quotation}/convert-to-sale', [QuotationController::class, 'convertToSale'])->name('admin.quotations.convertToSale');
            Route::get('quotations/{quotation}/attachment', [QuotationController::class, 'downloadAttachment'])->name('quotations.attachment');
            Route::get('quotations/{quotation}/files/{file}', [QuotationController::class, 'downloadFile'])->name('quotations.files.show');
            Route::post('quotations/import-materials', [QuotationController::class, 'importMaterials'])->name('admin.quotations.importMaterials');
            Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->middleware('admin_delete')->name('admin.quotations.destroy');
        });

        // Procurement Module (Admin and Procurement Staff)
        Route::middleware('role:admin,staff_procurement')->group(function () {
            Route::get('procurement', [PurchaseRequestController::class, 'index'])->name('procurement.index');
            Route::post('procurement', [PurchaseRequestController::class, 'store'])->name('procurement.store');
            Route::patch('procurement/{purchaseRequest}/status', [PurchaseRequestController::class, 'updateStatus'])->name('procurement.updateStatus');
            Route::delete('procurement/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->middleware('admin_delete')->name('procurement.destroy');

            // Commercial Invoices
            Route::post('procurement/commercial-invoices', [PurchaseRequestController::class, 'storeCommercialInvoice'])->name('procurement.commercialInvoices.store');
            Route::post('procurement/commercial-invoices/import-csv', [PurchaseRequestController::class, 'importCommercialInvoicesCsv'])->name('procurement.commercialInvoices.importCsv');
            Route::delete('procurement/commercial-invoices/{commercialInvoice}', [PurchaseRequestController::class, 'destroyCommercialInvoice'])->middleware('admin_delete')->name('procurement.commercialInvoices.destroy');

            // Purchase Orders
            Route::post('procurement/purchase-orders', [PurchaseRequestController::class, 'storePurchaseOrder'])->name('procurement.purchaseOrders.store');
            Route::post('procurement/purchase-orders/import-csv', [PurchaseRequestController::class, 'importPurchaseOrdersCsv'])->name('procurement.purchaseOrders.importCsv');
            Route::delete('procurement/purchase-orders/{purchaseOrder}', [PurchaseRequestController::class, 'destroyPurchaseOrder'])->middleware('admin_delete')->name('procurement.purchaseOrders.destroy');
        });

        // Delivery Module (Admin, Procurement, and Inventory Staff)
        Route::middleware('role:admin,staff_procurement,staff_inventory,staff_deliveries')->group(function () {
            Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
            Route::post('deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
            Route::post('deliveries/import-csv', [DeliveryController::class, 'importCsv'])->name('deliveries.importCsv');
            Route::delete('deliveries/{delivery}', [DeliveryController::class, 'destroy'])->middleware('admin_delete')->name('deliveries.destroy');

            // Outbound Customer Delivery Staff Actions
            Route::post('deliveries/fulfillments/{fulfillment}/start', [DeliveryController::class, 'startDelivery'])->name('deliveries.fulfillments.start');
            Route::post('deliveries/fulfillments/{fulfillment}/attempt', [DeliveryController::class, 'recordAttempt'])->name('deliveries.fulfillments.attempt');
            Route::post('deliveries/fulfillments/{fulfillment}/deliver', [DeliveryController::class, 'markDelivered'])->name('deliveries.fulfillments.deliver');
            Route::post('deliveries/fulfillments/{fulfillment}/return', [DeliveryController::class, 'returnToWarehouse'])->name('deliveries.fulfillments.return');
            Route::post('deliveries/fulfillments/{fulfillment}/assign', [DeliveryController::class, 'assignStaff'])->name('deliveries.fulfillments.assign');
            Route::post('deliveries/fulfillments/{fulfillment}/reschedule', [DeliveryController::class, 'reschedule'])->name('deliveries.fulfillments.reschedule');
            Route::get('deliveries/fulfillments/{fulfillment}/proof', [DeliveryController::class, 'fulfillmentProof'])->name('deliveries.fulfillments.proof');
            Route::get('deliveries/attempts/{attemptLog}/proof', [DeliveryController::class, 'attemptProof'])->name('deliveries.attempts.proof');
        });

        // Sales & POS Module (Admin and Sales Staff)
        Route::middleware('role:admin,staff_sales')->group(function () {
            Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
            Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
            Route::get('sales/{sale}/proof', [SaleController::class, 'proof'])->name('sales.proof');
            Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
            Route::post('sales/{sale}/verify-payment', [SaleController::class, 'verifyPayment'])->name('sales.verifyPayment');
            Route::post('sales/{sale}/fulfillment', [SaleController::class, 'storeFulfillment'])->name('sales.fulfillment.store');
            Route::patch('fulfillments/{fulfillment}/status', [SaleController::class, 'updateFulfillmentStatus'])->name('fulfillments.updateStatus');
            Route::get('fulfillments/{fulfillment}/proof', [SaleController::class, 'fulfillmentProof'])->name('fulfillments.proof');
            Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->middleware('admin_delete')->name('sales.destroy');
        });

        // Demand Forecasting Module (Admin and Demand Forecasting Staff)
        Route::middleware('role:admin,staff_forecast')->group(function () {
            Route::get('forecast', [ForecastController::class, 'index'])->name('forecast.index');
            Route::get('forecast/export-csv', [ForecastController::class, 'exportCsv'])->name('forecast.export-csv');
        });

        // Reports & Analytics Module (Admin and Reports Staff)
        Route::middleware('role:admin,staff_reports')->group(function () {
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

Route::middleware(['auth', 'no_cache'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
