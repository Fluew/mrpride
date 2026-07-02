<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/products', 'Products/Index')->name('products.index');
Route::inertia('/company', 'Company/Index')->name('company.index');
Route::inertia('/quotation', 'Quotations/Create')->name('quotations.create');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        
        // Capstone Modules (Frontend Testing Prototype)
        Route::inertia('users', 'Users/Index')->name('users.index');
        Route::inertia('inventory', 'Inventory/Index')->name('inventory.index');
        Route::inertia('suppliers', 'Suppliers/Index')->name('suppliers.index');
        Route::inertia('quotations', 'Quotations/AdminIndex')->name('admin.quotations.index');
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
