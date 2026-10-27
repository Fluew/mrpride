<?php

use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Ensure clean maintenance state before each test
    if (MaintenanceService::isDown()) {
        $admin = User::factory()->create(['role' => 'admin']);
        MaintenanceService::disable($admin);
    }
});

afterEach(function () {
    if (MaintenanceService::isDown()) {
        $admin = User::where('role', 'admin')->first() ?: User::factory()->create(['role' => 'admin']);
        MaintenanceService::disable($admin);
    }
});

test('admin can access maintenance settings', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password_changed_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/settings/maintenance');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/Maintenance')
        ->has('status')
    );
});

test('non-admin user is rejected from maintenance settings', function () {
    $staff = User::factory()->create([
        'role' => 'staff_inventory',
        'password_changed_at' => now(),
    ]);

    $this->actingAs($staff)->get('/settings/maintenance')->assertNotFound();
});

test('admin can enable maintenance mode, show maintenance page to guests, and verify functions before reopening', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password_changed_at' => now(),
    ]);

    // Step 1: Admin enables maintenance mode
    $enableResponse = $this->actingAs($admin)->post('/settings/maintenance/enable', [
        'reason' => 'Database updates & system upgrades in progress.',
        'duration_minutes' => 30,
        'expected_return_time' => '02:00 PM (approx. 30 mins)',
    ]);

    $enableResponse->assertSessionHas('success');
    expect(MaintenanceService::isDown())->toBeTrue();

    // Verify audit log recorded
    $log = AuditLog::where('event', 'system.maintenance.enabled')->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);

    // Step 3: Guest / customer / staff receives 503 Maintenance page
    auth()->logout();
    $guestResponse = $this->get('/');
    $guestResponse->assertStatus(503);
    $guestResponse->assertInertia(fn (Assert $page) => $page
        ->component('Maintenance')
        ->where('title', 'System Under Maintenance')
        ->where('message', 'MRP ElectroDesk is temporarily unavailable while we perform system maintenance.')
        ->where('reason', 'Database updates & system upgrades in progress.')
        ->where('expectedReturnTime', '02:00 PM (approx. 30 mins)')
    );

    // API / JSON request receives 503 JSON payload
    $apiResponse = $this->getJson('/');
    $apiResponse->assertStatus(503);
    $apiResponse->assertJsonFragment([
        'title' => 'System Under Maintenance',
        'message' => 'MRP ElectroDesk is temporarily unavailable while we perform system maintenance.',
    ]);

    // Admin can still access maintenance settings to monitor and test
    $adminAccess = $this->actingAs($admin)->get('/settings/maintenance');
    $adminAccess->assertSuccessful();

    // Step 4: Admin runs pre-flight system readiness verification
    $verifyResponse = $this->actingAs($admin)->postJson('/settings/maintenance/verify');
    $verifyResponse->assertSuccessful();
    $verifyResponse->assertJsonStructure([
        'all_passed',
        'verified_at',
        'tests' => [
            '*' => ['id', 'title', 'status', 'message'],
        ],
    ]);
    expect($verifyResponse->json('all_passed'))->toBeTrue();

    // Admin reopens system / disables maintenance mode
    $disableResponse = $this->actingAs($admin)->post('/settings/maintenance/disable');
    $disableResponse->assertSessionHas('success');
    expect(MaintenanceService::isDown())->toBeFalse();

    // System is back to normal operations
    auth()->logout();
    $this->get('/')->assertSuccessful();
});

test('admin can run verification using GET or POST when system is under maintenance', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password_changed_at' => now(),
    ]);

    // Enable maintenance mode
    MaintenanceService::enable(['reason' => 'Testing verification under maintenance'], $admin);
    expect(MaintenanceService::isDown())->toBeTrue();

    // Verify GET request works under maintenance
    $getResponse = $this->actingAs($admin)->getJson('/settings/maintenance/verify');
    $getResponse->assertSuccessful();
    $getResponse->assertJsonPath('all_passed', true);
    expect($getResponse->json('tests'))->toHaveCount(6);

    // Verify POST request works under maintenance
    $postResponse = $this->actingAs($admin)->postJson('/settings/maintenance/verify');
    $postResponse->assertSuccessful();
    $postResponse->assertJsonPath('all_passed', true);
});

test('unregistered workstation accessing protected system receives 404 and logs threat event', function () {
    $response = $this->get('/login');
    $response->assertNotFound();
    $response->assertInertia(fn (Assert $page) => $page->component('NotFound'));

    $threatLog = AuditLog::where('event', 'auth.threat.unregistered_device')->latest()->first();
    expect($threatLog)->not->toBeNull();
    expect($threatLog->outcome)->toBe('blocked');
    expect($threatLog->category)->toBe('security');
});
