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

test('direct access to enrollment page without invitation returns 404', function () {
    $this->get('/device/enroll')->assertNotFound();
});

test('candidate device can view enrollment page with valid code or link', function () {
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Pending Workstation',
        'status' => 'pending',
        'enrollment_code' => 'MRP-VIEW01',
        'enrollment_code_expires_at' => now()->addMinutes(5),
    ]);

    $this->get('/device/enroll?code=MRP-VIEW01')->assertSuccessful();
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

test('end-to-end flow: admin creates pending staff, issues 10-minute invitation, staff verifies identity, enrolls browser, accesses login, and authenticates', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
        'password_changed_at' => now(),
    ]);
    $team = $admin->personalTeam() ?? \App\Models\Team::factory()->create();
    $adminDevice = createTrustedDeviceFor($admin);

    // 1. Admin creates staff account (status: pending_activation, temp password: Maria1234)
    $response = $this->actingAs($admin)
        ->withCookies($adminDevice['cookies'])
        ->post("/{$team->slug}/users", [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@mrpride.com',
            'role' => 'staff_inventory',
        ]);

    $response->assertSessionHasNoErrors();
    $staff = User::where('email', 'maria.santos@mrpride.com')->firstOrFail();
    expect($staff->status)->toBe('pending_activation');
    expect($staff->isPendingActivation())->toBeTrue();
    expect($staff->isActive())->toBeTrue(); // allowed to activate
    expect(Hash::check('Maria1234', $staff->password))->toBeTrue();

    // 2. Admin creates 10-minute device enrollment invitation
    $inviteResponse = $this->actingAs($admin)
        ->withCookies($adminDevice['cookies'])
        ->post("/{$team->slug}/users/devices/enroll", [
            'assignment_type' => 'single',
            'user_id' => $staff->id,
            'device_name' => "Maria's Inventory Laptop",
            'expires_in_minutes' => 10,
        ]);

    $inviteResponse->assertSessionHasNoErrors();
    $inviteResponse->assertSessionHas('enrollmentNotice');
    $device = TrustedDevice::where('user_id', $staff->id)->firstOrFail();
    expect($device->status)->toBe('pending');
    expect($device->enrollment_token)->not->toBeNull();
    expect($device->enrollment_token_expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(10);

    // 3. Before enrollment, unauthenticated/unregistered candidate browser gets 404 on /login
    auth()->logout();
    $this->flushSession();
    $this->defaultCookies = [];
    $this->get('/login')->assertNotFound();

    // 4. Staff opens one-time enrollment link on new browser (with bound email)
    $tokenPageResponse = $this->get("/device/enroll/{$device->enrollment_token}?email=" . urlencode('maria.santos@mrpride.com'));
    $tokenPageResponse->assertSuccessful();

    // 4b. Forwarded link opened with another email is rejected (403 Forbidden)
    $forwardedResponse = $this->get("/device/enroll/{$device->enrollment_token}?email=" . urlencode('other.person@mrpride.com'));
    $forwardedResponse->assertForbidden();

    // 5. Staff verifies identity with enrollment code + email + temporary password
    $enrollResponse = $this->post('/device/enroll', [
        'token' => $device->enrollment_token,
        'code' => $device->enrollment_code,
        'email' => 'maria.santos@mrpride.com',
        'password' => 'Maria1234',
        'device_name' => "Maria's Work Laptop",
    ]);

    $enrollResponse->assertRedirect('/login');
    $enrollResponse->assertCookie('mrpride_device_credential');
    $enrollResponse->assertCookie('mrpride_device_uuid');

    // Cookies issued and account activated
    $staff->refresh();
    expect($staff->status)->toBe('active');
    $device->refresh();
    expect($device->status)->toBe('approved');
    expect($device->enrollment_token)->toBeNull(); // single-use token consumed

    // 6. /login is now accessible with the enrolled cookies!
    $deviceCookies = [
        'mrpride_device_uuid' => $enrollResponse->getCookie('mrpride_device_uuid')->getValue(),
        'mrpride_device_credential' => $enrollResponse->getCookie('mrpride_device_credential')->getValue(),
    ];

    $loginPage = $this->withCookies($deviceCookies)->get('/login');
    $loginPage->assertSuccessful();

    // 7. Staff authenticates with Email + Temporary Password
    $authResponse = $this->withCookies($deviceCookies)->post('/login', [
        'email' => 'maria.santos@mrpride.com',
        'password' => 'Maria1234',
    ]);

    // Redirected to mandatory password change because must_change_password is true
    $authResponse->assertRedirect('/password/change');
    $this->assertAuthenticatedAs($staff);
});

