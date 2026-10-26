<?php

use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can access recycle bin page', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $admin = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($admin);

    $response = $this->actingAs($admin)
        ->withCookies($deviceData['cookies'])
        ->get("/{$team->slug}/recycle-bin");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('RecycleBin/Index'));
});

test('non-admin staff is forbidden from accessing recycle bin page', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $staff = User::factory()->create([
        'role' => 'staff_inventory',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($staff, ['role' => TeamRole::Member->value]);
    $deviceData = createTrustedDeviceFor($staff);

    $response = $this->actingAs($staff)
        ->withCookies($deviceData['cookies'])
        ->get("/{$team->slug}/recycle-bin");

    $response->assertNotFound(); // EnsureRole returns 404 for admin-only routes to protect obscurity
});

test('archived records display with deleted_by tracking information', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $admin = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($admin);

    $product = Product::create([
        'team_id' => $team->id,
        'name' => 'Archived Relay Module',
        'sku' => 'REL-001',
        'category' => 'Relays',
        'price' => 450.00,
        'stock' => 12,
        'is_active' => true,
    ]);

    // Delete product as admin to trigger bootTracksDeletions
    $this->actingAs($admin)
        ->withCookies($deviceData['cookies'])
        ->delete("/{$team->slug}/inventory/products/{$product->id}");

    $this->assertSoftDeleted('products', ['id' => $product->id]);

    $response = $this->actingAs($admin)
        ->withCookies($deviceData['cookies'])
        ->get("/{$team->slug}/recycle-bin");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('RecycleBin/Index')
        ->has('records.data', 1)
        ->where('records.data.0.title', 'Archived Relay Module')
        ->where('records.data.0.type', 'product')
        ->where('records.data.0.deleted_by.email', $admin->email)
    );
});

test('admin can restore an archived record back to active use', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $admin = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($admin);

    $product = Product::create([
        'team_id' => $team->id,
        'name' => 'Recoverable Sensor',
        'sku' => 'SNS-001',
        'category' => 'Sensors',
        'price' => 300.00,
        'stock' => 5,
        'is_active' => true,
    ]);
    $product->deleted_by_id = $admin->id;
    $product->saveQuietly();
    $product->delete();

    $response = $this->actingAs($admin)
        ->withCookies($deviceData['cookies'])
        ->post("/{$team->slug}/recycle-bin/product/{$product->id}/restore");

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'deleted_at' => null,
        'is_active' => 1,
    ]);

    // Check audit log
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'recycle_bin.restore',
        'user_id' => $admin->id,
    ]);
});

test('admin can permanently delete an archived record with audit log', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $admin = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($admin);

    $supplier = Supplier::create([
        'team_id' => $team->id,
        'name' => 'Obsolete Supplier',
        'email' => 'obsolete@example.com',
        'status' => 'Active',
    ]);
    $supplier->deleted_by_id = $admin->id;
    $supplier->saveQuietly();
    $supplier->delete();

    $response = $this->actingAs($admin)
        ->withCookies($deviceData['cookies'])
        ->delete("/{$team->slug}/recycle-bin/supplier/{$supplier->id}/force");

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseMissing('suppliers', [
        'id' => $supplier->id,
    ]);

    // Check audit log
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'recycle_bin.permanent_delete',
        'user_id' => $admin->id,
    ]);
});

test('admin can bulk restore multiple archived records', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $admin = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($admin);

    $prod1 = Product::create([
        'team_id' => $team->id,
        'name' => 'Prod 1',
        'sku' => 'P1',
        'price' => 10,
        'stock' => 1,
    ]);
    $prod1->delete();

    $inquiry = Inquiry::create([
        'team_id' => $team->id,
        'name' => 'Inquiry 1',
        'email' => 'client@test.com',
        'message' => 'Test message',
        'status' => 'unread',
        'priority' => 'normal',
    ]);
    $inquiry->delete();

    $response = $this->actingAs($admin)
        ->withCookies($deviceData['cookies'])
        ->post("/{$team->slug}/recycle-bin/bulk-restore", [
            'items' => [
                ['type' => 'product', 'id' => $prod1->id],
                ['type' => 'inquiry', 'id' => $inquiry->id],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $prod1->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'deleted_at' => null]);
});

test('admin can empty the recycle bin', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $admin = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($admin);

    $prod = Product::create([
        'team_id' => $team->id,
        'name' => 'Prod To Purge',
        'sku' => 'PURGE-1',
        'price' => 10,
        'stock' => 1,
    ]);
    $prod->delete();

    $response = $this->actingAs($admin)
        ->withCookies($deviceData['cookies'])
        ->post("/{$team->slug}/recycle-bin/empty");

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseMissing('products', ['id' => $prod->id]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'recycle_bin.empty',
        'user_id' => $admin->id,
    ]);
});
