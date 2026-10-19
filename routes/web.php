<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/products', 'Products/Index')->name('products.index');
Route::inertia('/company', 'Company/Index')->name('company.index');
Route::get('/quotation', [PublicQuotationController::class, 'create'])->name('quotations.create');
Route::post('/quotation', [PublicQuotationController::class, 'store'])->middleware('throttle:10,1')->name('quotations.store');

Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    if (! $user) {
        return redirect('/login');
    }
    $team = $user->currentTeam ?: $user->teams()->first() ?: \App\Models\Team::first();
    if ($team) {
        if (! $user->belongsToTeam($team)) {
            $user->teams()->syncWithoutDetaching([$team->id => ['role' => \App\Enums\TeamRole::Owner->value]]);
        }
        $user->switchTeam($team);
        return redirect("/{$team->slug}/dashboard");
    }
    return redirect('/');
})->middleware(['auth'])->name('dashboard.redirect');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        
        // User Management Module (Database-backed)
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        
        // Inventory Management Module
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

        // Supplier Management Module (Database-backed + AHP)
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        Route::post('suppliers/evaluate-ahp', [SupplierController::class, 'evaluate'])->name('suppliers.evaluate');
        
        // Quotation Management Module
        Route::get('quotations', [QuotationController::class, 'index'])->name('admin.quotations.index');
        Route::post('quotations', [QuotationController::class, 'store'])->name('admin.quotations.store');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('admin.quotations.update');
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('admin.quotations.updateStatus');
        Route::post('quotations/{quotation}/convert-to-sale', [QuotationController::class, 'convertToSale'])->name('admin.quotations.convertToSale');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('admin.quotations.destroy');

        // Procurement Monitoring Module (Database-backed)
        Route::get('procurement', [ProcurementController::class, 'index'])->name('procurement.index');
        Route::post('procurement', [ProcurementController::class, 'store'])->name('procurement.store');
        Route::patch('procurement/{procurement}/status', [ProcurementController::class, 'updateStatus'])->name('procurement.updateStatus');
        Route::delete('procurement/{procurement}', [ProcurementController::class, 'destroy'])->name('procurement.destroy');

        // Sales & POS Module (Database-backed)
        Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
        Route::post('sales', [SalesController::class, 'store'])->name('sales.store');

        // Demand Forecasting Module (Database-backed)
        Route::get('forecast', [ForecastController::class, 'index'])->name('forecast.index');

        // System Reports Module (Database-backed + Real CSV Exports)
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/inventory', [ReportController::class, 'exportInventory'])->name('reports.export.inventory');
        Route::get('reports/export/quotations', [ReportController::class, 'exportQuotations'])->name('reports.export.quotations');
        Route::get('reports/export/sales', [ReportController::class, 'exportSales'])->name('reports.export.sales');
        Route::get('reports/export/suppliers', [ReportController::class, 'exportSuppliers'])->name('reports.export.suppliers');
        Route::get('reports/export/master', [ReportController::class, 'exportMaster'])->name('reports.export.master');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
