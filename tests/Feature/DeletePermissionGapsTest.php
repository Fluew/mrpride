<?php

use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Covers the deletion pathways that are not plain DELETE routes:
 * "replace" CSV imports, self-deleting an account, permanent deletes from the recycle bin,
 * and the `auth.can_delete` flag the frontend uses to hide delete controls.
 */
function gapActor(string $role, Team $team): array
{
    $user = User::factory()->create([
        'role' => $role,
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($user, ['role' => $role === 'admin' ? TeamRole::Owner->value : TeamRole::Member->value]);

    return [$user, createTrustedDeviceFor($user)['cookies']];
}

function gapProduct(Team $team, string $sku = 'SKU-OLD-1'): Product
{
    return Product::create([
        'team_id' => $team->id, 'sku' => $sku, 'name' => 'Existing Product',
        'price' => 100, 'stock' => 5, 'is_active' => true,
    ]);
}

function gapCsv(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('import.csv', "sku,name,price,stock\nSKU-NEW-1,New Product,250,4\n");
}

// --- Replace-mode CSV imports -------------------------------------------------------------

it('blocks staff from replace-mode inventory imports and leaves the catalog active', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$staff, $cookies] = gapActor('staff_inventory', $team);
    $existing = gapProduct($team);

    $this->actingAs($staff)->withCookies($cookies)
        ->post("/{$team->slug}/inventory/import-csv", ['file' => gapCsv(), 'mode' => 'replace', 'execute' => true])
        ->assertForbidden();

    expect($existing->fresh()->is_active)->toBeTrue()
        ->and(Product::where('sku', 'SKU-NEW-1')->exists())->toBeFalse();
});

it('still lets staff run append-mode inventory imports', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$staff, $cookies] = gapActor('staff_inventory', $team);
    $existing = gapProduct($team);

    $this->actingAs($staff)->withCookies($cookies)
        ->post("/{$team->slug}/inventory/import-csv", ['file' => gapCsv(), 'mode' => 'append', 'execute' => true])
        ->assertSessionHasNoErrors();

    expect(Product::where('team_id', $team->id)->where('sku', 'SKU-NEW-1')->exists())->toBeTrue()
        ->and($existing->fresh()->is_active)->toBeTrue();
});

it('lets admins run replace-mode inventory imports', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$admin, $cookies] = gapActor('admin', $team);
    $existing = gapProduct($team);

    $this->actingAs($admin)->withCookies($cookies)
        ->post("/{$team->slug}/inventory/import-csv", ['file' => gapCsv(), 'mode' => 'replace', 'execute' => true])
        ->assertSessionHasNoErrors();

    expect($existing->fresh()->is_active)->toBeFalse()
        ->and(Product::where('team_id', $team->id)->where('sku', 'SKU-NEW-1')->exists())->toBeTrue();
});

it('blocks staff from replace-mode quotation imports', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$staff, $cookies] = gapActor('staff_quotations', $team);

    $this->actingAs($staff)->withCookies($cookies)
        ->post("/{$team->slug}/quotations/import-csv", ['file' => gapCsv(), 'mode' => 'replace', 'execute' => true])
        ->assertForbidden();

    expect(Quotation::where('team_id', $team->id)->count())->toBe(0);
});

// --- Account self-deletion ----------------------------------------------------------------

it('blocks staff from deleting their own account', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$staff, $cookies] = gapActor('staff_sales', $team);

    $this->actingAs($staff)->withCookies($cookies)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertForbidden();

    expect(User::find($staff->id))->not->toBeNull();
});

it('rejects guests on protected delete routes', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    $product = gapProduct($team);

    $this->delete("/{$team->slug}/inventory/products/{$product->id}")->assertRedirect(route('login'));
    $this->delete('/settings/profile', ['password' => 'password'])->assertRedirect(route('login'));

    expect(Product::find($product->id))->not->toBeNull();
});

// --- Recycle bin: history is never purged -------------------------------------------------

it('refuses to permanently delete a voided sale', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$admin, $cookies] = gapActor('admin', $team);
    $sale = Sale::create([
        'team_id' => $team->id, 'invoice_number' => 'INV-GAP-1', 'client_name' => 'Someone',
        'sale_date' => now(), 'subtotal' => 100, 'discount' => 0, 'tax_amount' => 0,
        'grand_total' => 100, 'status' => 'Completed',
    ]);
    $sale->delete();

    $this->actingAs($admin)->withCookies($cookies)
        ->delete("/{$team->slug}/recycle-bin/sale/{$sale->id}/force")
        ->assertSessionHas('error');

    expect(Sale::withTrashed()->find($sale->id))->not->toBeNull();
});

it('refuses to permanently delete a product with stock history, but purges one without', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$admin, $cookies] = gapActor('admin', $team);

    $withHistory = gapProduct($team, 'SKU-HIST');
    StockMovement::create([
        'team_id' => $team->id, 'product_id' => $withHistory->id, 'user_id' => $admin->id,
        'type' => 'stock_in', 'quantity' => 5, 'previous_stock' => 0, 'new_stock' => 5,
        'reference_number' => 'REF-1', 'reason' => 'Initial', 'movement_date' => now(),
    ]);
    $withHistory->delete();

    $clean = gapProduct($team, 'SKU-CLEAN');
    $clean->delete();

    $this->actingAs($admin)->withCookies($cookies)
        ->post("/{$team->slug}/recycle-bin/bulk-force-delete", ['items' => [
            ['type' => 'product', 'id' => $withHistory->id],
            ['type' => 'product', 'id' => $clean->id],
        ]])
        ->assertSessionHas('success');

    expect(Product::withTrashed()->find($withHistory->id))->not->toBeNull()
        ->and(StockMovement::where('product_id', $withHistory->id)->count())->toBe(1)
        ->and(Product::withTrashed()->find($clean->id))->toBeNull();
});

it('keeps sales when the recycle bin is emptied', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$admin, $cookies] = gapActor('admin', $team);
    $sale = Sale::create([
        'team_id' => $team->id, 'invoice_number' => 'INV-GAP-2', 'client_name' => 'Someone',
        'sale_date' => now(), 'subtotal' => 100, 'discount' => 0, 'tax_amount' => 0,
        'grand_total' => 100, 'status' => 'Completed',
    ]);
    $sale->delete();
    $supplier = Supplier::create(['team_id' => $team->id, 'name' => 'Old Supplier']);
    $supplier->delete();

    $this->actingAs($admin)->withCookies($cookies)
        ->post("/{$team->slug}/recycle-bin/empty")
        ->assertSessionHas('success');

    expect(Sale::withTrashed()->find($sale->id))->not->toBeNull()
        ->and(Supplier::withTrashed()->find($supplier->id))->toBeNull();
});

// --- Frontend flag ------------------------------------------------------------------------

it('tells the frontend only admins can delete', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$staff, $staffCookies] = gapActor('staff_suppliers', $team);
    [$admin, $adminCookies] = gapActor('admin', $team);

    $this->actingAs($staff)->withCookies($staffCookies)
        ->get("/{$team->slug}/suppliers")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.can_delete', false));

    $this->actingAs($admin)->withCookies($adminCookies)
        ->get("/{$team->slug}/suppliers")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.can_delete', true));
});

// --- Recycle bin: restoring a voided sale -------------------------------------------------

function gapSellTwo(Team $team, User $admin, array $cookies, Product $product): Sale
{
    test()->actingAs($admin)->withCookies($cookies)
        ->post("/{$team->slug}/sales", [
            'customer' => 'Restore Test Co', 'payment_method' => 'GCash',
            'payment_reference' => 'GCASH-RESTORE-1', 'amount_paid' => 200,
            'payment_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ])
        ->assertSessionHasNoErrors();

    return Sale::where('team_id', $team->id)->latest('id')->firstOrFail();
}

it('takes the stock back out when a voided sale is restored', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$admin, $cookies] = gapActor('admin', $team);
    $product = Product::create([
        'team_id' => $team->id, 'sku' => 'SKU-RST', 'name' => 'Restorable', 'price' => 100, 'stock' => 10, 'is_active' => true,
    ]);

    $sale = gapSellTwo($team, $admin, $cookies, $product);
    expect($product->fresh()->stock)->toBe(8);

    $this->delete("/{$team->slug}/sales/{$sale->id}");
    expect($product->fresh()->stock)->toBe(10);

    $this->post("/{$team->slug}/recycle-bin/sale/{$sale->id}/restore")->assertSessionHas('success');

    expect(Sale::find($sale->id))->not->toBeNull()
        ->and($product->fresh()->stock)->toBe(8)
        ->and(StockMovement::where('product_id', $product->id)->where('reason', 'like', '%restored from recycle bin%')->count())->toBe(1);
});

it('refuses to restore a voided sale when the stock is no longer there', function () {
    $team = Team::factory()->create(['slug' => 'gap-team']);
    [$admin, $cookies] = gapActor('admin', $team);
    $product = Product::create([
        'team_id' => $team->id, 'sku' => 'SKU-RST2', 'name' => 'Sold Out', 'price' => 100, 'stock' => 10, 'is_active' => true,
    ]);

    $sale = gapSellTwo($team, $admin, $cookies, $product);
    $this->delete("/{$team->slug}/sales/{$sale->id}");
    $product->update(['stock' => 1]); // sold elsewhere in the meantime

    $this->post("/{$team->slug}/recycle-bin/sale/{$sale->id}/restore")->assertSessionHas('error');

    expect(Sale::find($sale->id))->toBeNull()
        ->and(Sale::withTrashed()->find($sale->id))->not->toBeNull()
        ->and($product->fresh()->stock)->toBe(1);
});
