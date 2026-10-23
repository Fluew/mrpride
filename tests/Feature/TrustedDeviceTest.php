<?php

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('public routes are accessible without any device registration', function () {
    $this->get('/')->assertSuccessful();
    $this->get('/products')->assertSuccessful();
    $this->get('/company')->assertSuccessful();
    $this->get('/contact-us')->assertSuccessful();
    $this->get('/quotation')->assertSuccessful();
});

test('unregistered device attempting to access login receives 404 page not found', function () {
    $response = $this->get('/login');
    $response->assertNotFound();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('NotFound')
    );
});

test('guest accessing dashboard route receives 404 page not found', function () {
    $response = $this->get('/dashboard');
    $response->assertNotFound();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('NotFound')
    );
});

test('guest accessing admin dashboard route receives 404 page not found', function () {
    $response = $this->get('/admin/dashboard');
    $response->assertNotFound();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('NotFound')
    );
});

test('guest accessing staff dashboard route receives 404 page not found', function () {
    $response = $this->get('/staff/dashboard');
    $response->assertNotFound();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('NotFound')
    );
});

test('candidate device can view enrollment page', function () {
    $this->get('/device/enroll')->assertSuccessful();
});

test('submitting an invalid enrollment code is rejected', function () {
    $response = $this->post('/device/enroll', [
        'code' => 'MRP-INVALID',
        'device_name' => 'Test Device',
    ]);

    $response->assertSessionHasErrors('code');
});

test('submitting an expired enrollment code is rejected', function () {
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Expired Workstation',
        'status' => 'pending',
        'enrollment_code' => 'MRP-EXPD01',
        'enrollment_code_expires_at' => now()->subMinutes(5),
    ]);

    $response = $this->post('/device/enroll', [
        'code' => 'MRP-EXPD01',
        'device_name' => 'Test Device',
    ]);

    $response->assertSessionHasErrors('code');
});

test('submitting a valid enrollment code registers device, approves it, and issues cookies', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'password_changed_at' => now(),
    ]);

    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Pending Workstation',
        'user_id' => $user->id,
        'status' => 'pending',
        'enrollment_code' => 'MRP-VALID1',
        'enrollment_code_expires_at' => now()->addMinutes(30),
    ]);

    $response = $this->post('/device/enroll', [
        'code' => 'MRP-VALID1',
        'device_name' => 'My Workstation Laptop',
    ]);

    $response->assertRedirect('/login');
    $response->assertCookie('mrpride_device_credential');
    $response->assertCookie('mrpride_device_uuid');

    $device->refresh();
    expect($device->status)->toBe('approved');
    expect($device->credential_hash)->not->toBeNull();
    expect($device->enrollment_code)->toBeNull(); // code consumed
});

test('reusing an enrollment code is rejected', function () {
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Pending Workstation',
        'status' => 'pending',
        'enrollment_code' => 'MRP-SINGLE',
        'enrollment_code_expires_at' => now()->addMinutes(30),
    ]);

    // First use
    $this->post('/device/enroll', [
        'code' => 'MRP-SINGLE',
        'device_name' => 'First Browser',
    ])->assertRedirect('/login');

    // Second use attempt
    $response = $this->post('/device/enroll', [
        'code' => 'MRP-SINGLE',
        'device_name' => 'Second Browser',
    ]);

    $response->assertSessionHasErrors('code');
});

test('registered and active device can access login page', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'password_changed_at' => now(),
    ]);
    $rawCredential = Str::random(64);
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Admin Laptop',
        'user_id' => $user->id,
        'status' => 'approved',
        'credential_hash' => hash('sha256', $rawCredential),
        'approved_at' => now(),
    ]);

    $response = $this->withCookie('mrpride_device_uuid', $device->device_uuid)
        ->withCookie('mrpride_device_credential', $rawCredential)
        ->get('/login');

    $response->assertSuccessful();
});

test('authorized user on registered device can log in', function () {
    $password = 'Secret123!';
    $user = User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
        'password' => Hash::make($password),
        'password_changed_at' => now(),
    ]);

    $teamSlug = $user->personalTeam()?->slug ?? 'mr-pride';

    $rawCredential = Str::random(64);
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Admin Workstation',
        'user_id' => $user->id,
        'status' => 'approved',
        'credential_hash' => hash('sha256', $rawCredential),
        'approved_at' => now(),
    ]);

    $response = $this->withCookie('mrpride_device_uuid', $device->device_uuid)
        ->withCookie('mrpride_device_credential', $rawCredential)
        ->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

    $response->assertRedirect("/{$teamSlug}/dashboard");
    $this->assertAuthenticatedAs($user);
});

test('account-device mismatch rejects login without creating a session and redirects to login with exact error message', function () {
    $password = 'Secret123!';
    $authorizedUser = User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
        'password' => Hash::make($password),
        'password_changed_at' => now(),
    ]);

    $otherUser = User::factory()->create([
        'role' => 'staff',
        'status' => 'active',
        'password' => Hash::make($password),
        'password_changed_at' => now(),
    ]);

    $rawCredential = Str::random(64);
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Admin Only Workstation',
        'user_id' => $authorizedUser->id,
        'is_shared' => false,
        'status' => 'approved',
        'credential_hash' => hash('sha256', $rawCredential),
        'approved_at' => now(),
    ]);

    $response = $this->withCookie('mrpride_device_uuid', $device->device_uuid)
        ->withCookie('mrpride_device_credential', $rawCredential)
        ->from('/login')
        ->post('/login', [
            'email' => $otherUser->email,
            'password' => $password,
        ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['email' => 'This account is not authorized to use this workstation. Please contact your administrator.']);
    $this->assertGuest();
});

test('shared workstation allows any authorized account in authorized_user_ids', function () {
    $password = 'Secret123!';
    $staff1 = User::factory()->create([
        'role' => 'staff',
        'status' => 'active',
        'password' => Hash::make($password),
        'password_changed_at' => now(),
    ]);

    $staff2 = User::factory()->create([
        'role' => 'staff_quotations',
        'status' => 'active',
        'password' => Hash::make($password),
        'password_changed_at' => now(),
    ]);

    $rawCredential = Str::random(64);
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Shared Front Desk Station',
        'is_shared' => true,
        'authorized_user_ids' => [$staff1->id, $staff2->id],
        'status' => 'approved',
        'credential_hash' => hash('sha256', $rawCredential),
        'approved_at' => now(),
    ]);

    $team1Slug = $staff1->personalTeam()?->slug ?? 'mr-pride';

    // Test staff1
    $response1 = $this->withCookie('mrpride_device_uuid', $device->device_uuid)
        ->withCookie('mrpride_device_credential', $rawCredential)
        ->post('/login', [
            'email' => $staff1->email,
            'password' => $password,
        ]);

    $response1->assertRedirect("/{$team1Slug}/" . $staff1->assignedModuleSlug());
    $this->assertAuthenticatedAs($staff1);

    // Logout
    $this->post('/logout');
    $this->assertGuest();

    $team2Slug = $staff2->personalTeam()?->slug ?? 'mr-pride';

    // Test staff2
    $response2 = $this->withCookie('mrpride_device_uuid', $device->device_uuid)
        ->withCookie('mrpride_device_credential', $rawCredential)
        ->post('/login', [
            'email' => $staff2->email,
            'password' => $password,
        ]);

    $response2->assertRedirect("/{$team2Slug}/" . $staff2->assignedModuleSlug());
    $this->assertAuthenticatedAs($staff2);
});

test('revoked device immediately loses access to protected routes and APIs with 404 page not found', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
        'password_changed_at' => now(),
    ]);
    $rawCredential = Str::random(64);
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Revoked Laptop',
        'user_id' => $user->id,
        'status' => 'revoked',
        'credential_hash' => hash('sha256', $rawCredential),
        'approved_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->withCookie('mrpride_device_uuid', $device->device_uuid)
        ->withCookie('mrpride_device_credential', $rawCredential)
        ->get('/admin/dashboard');

    $response->assertNotFound();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('NotFound')
    );
});

test('role-based access control blocks staff from admin dashboard with 404 page not found', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
        'status' => 'active',
        'password_changed_at' => now(),
    ]);
    $rawCredential = Str::random(64);
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Staff Terminal',
        'user_id' => $staff->id,
        'status' => 'approved',
        'credential_hash' => hash('sha256', $rawCredential),
        'approved_at' => now(),
    ]);

    // Staff visits admin dashboard -> 404 Page Not Found
    $response = $this->actingAs($staff)
        ->withCookie('mrpride_device_uuid', $device->device_uuid)
        ->withCookie('mrpride_device_credential', $rawCredential)
        ->get('/admin/dashboard');

    $response->assertNotFound();
});

test('only authenticated admins can enroll or manage trusted devices', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
        'status' => 'active',
        'password_changed_at' => now(),
    ]);
    $teamSlug = $staff->personalTeam()?->slug ?? 'mr-pride';
    $deviceData = createTrustedDeviceFor($staff);

    // Staff attempts to access device enrollment API -> rejected
    $response = $this->actingAs($staff)
        ->withCookies($deviceData['cookies'])
        ->post("/{$teamSlug}/users/devices/enroll", [
            'assignment_type' => 'single',
            'user_id' => $staff->id,
        ]);

    $response->assertNotFound(); // role:admin middleware blocks non-admins with 404
});
