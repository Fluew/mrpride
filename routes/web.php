<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\Teams\TeamInvitationController;
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
        
        // Capstone Modules
        Route::inertia('users', 'Users/Index')->name('users.index');
        
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

        Route::inertia('suppliers', 'Suppliers/Index')->name('suppliers.index');
        
        // Quotation Management Module
        Route::get('quotations', [QuotationController::class, 'index'])->name('admin.quotations.index');
        Route::post('quotations', [QuotationController::class, 'store'])->name('admin.quotations.store');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('admin.quotations.update');
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('admin.quotations.updateStatus');
        Route::post('quotations/{quotation}/convert-to-sale', [QuotationController::class, 'convertToSale'])->name('admin.quotations.convertToSale');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('admin.quotations.destroy');

        Route::inertia('procurement', 'Procurement/Index')->name('procurement.index');
        Route::inertia('sales', 'Sales/Index')->name('sales.index');
        Route::inertia('forecast', 'Forecast/Index')->name('forecast.index');
        Route::inertia('reports', 'Reports/Index')->name('reports.index');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
