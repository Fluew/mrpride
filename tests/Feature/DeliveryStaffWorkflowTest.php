<?php

use App\Enums\TeamRole;
use App\Models\OrderFulfillment;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->team = Team::factory()->create();
});

function createDeliveryActor(string $role, Team $team): array
{
    $user = User::factory()->create([
        'role' => $role,
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($user, ['role' => $role === 'admin' ? TeamRole::Owner->value : TeamRole::Member->value]);
    $deviceData = createTrustedDeviceFor($user);

    return [$user, $deviceData['cookies']];
}

it('allows administrator to view all deliveries and assign delivery to delivery staff', function () {
    [$admin, $adminCookies] = createDeliveryActor('admin', $this->team);
    [$driver, $driverCookies] = createDeliveryActor('staff_deliveries', $this->team);

    $sale = Sale::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-DEL-001',
        'client_name' => 'Solar Solutions Ph',
        'sale_date' => now(),
        'subtotal' => 15000.00,
        'discount' => 0.00,
        'tax_amount' => 0.00,
        'grand_total' => 15000.00,
        'status' => 'Completed',
        'processed_by_id' => $admin->id,
    ]);

    $fulfillment = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-TEST-001',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Engr. Manuel Roxas',
        'contact_number' => '09175554433',
        'delivery_address' => 'Phase 2 Industrial Park, Calamba, Laguna',
        'status' => 'Pending',
        'handled_by_id' => null,
    ]);

    // Admin assigns delivery to driver
    $response = $this->actingAs($admin)->withCookies($adminCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/assign", [
        'staff_id' => $driver->id,
    ]);

    $response->assertSessionHasNoErrors();
    $fulfillment->refresh();
    expect($fulfillment->handled_by_id)->toBe($driver->id);
    expect($fulfillment->attemptLogs)->toHaveCount(1);
    expect($fulfillment->attemptLogs->first()->remarks)->toContain($driver->name);
});

it('scopes dashboard so delivery staff views only their assigned deliveries while admin views all', function () {
    [$admin, $adminCookies] = createDeliveryActor('admin', $this->team);
    [$driver1, $driver1Cookies] = createDeliveryActor('staff_deliveries', $this->team);
    [$driver2, $driver2Cookies] = createDeliveryActor('staff_deliveries', $this->team);

    $sale = Sale::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-DEL-SCOPE',
        'client_name' => 'Prime Distribution',
        'sale_date' => now(),
        'subtotal' => 5000.00,
        'discount' => 0.00,
        'tax_amount' => 0.00,
        'grand_total' => 5000.00,
        'status' => 'Completed',
        'processed_by_id' => $admin->id,
    ]);

    // Fulfillment 1 assigned to driver 1
    $ful1 = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-DRIVER1-01',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Customer A',
        'contact_number' => '09171111111',
        'status' => 'Pending',
        'handled_by_id' => $driver1->id,
    ]);

    // Fulfillment 2 assigned to driver 2
    $ful2 = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-DRIVER2-02',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Customer B',
        'contact_number' => '09172222222',
        'status' => 'Pending',
        'handled_by_id' => $driver2->id,
    ]);

    // Driver 1 visits dashboard -> sees only FUL-DRIVER1-01
    $responseDriver1 = $this->actingAs($driver1)->withCookies($driver1Cookies)->get("/{$this->team->slug}/deliveries");
    $responseDriver1->assertOk();
    $pageProps = $responseDriver1->original->getData()['page']['props'];
    $outboundData = $pageProps['outboundDeliveries']['data'];
    $fulfillmentNumbers = array_column($outboundData, 'fulfillment_number');
    expect($fulfillmentNumbers)->toContain('FUL-DRIVER1-01');
    expect($fulfillmentNumbers)->not->toContain('FUL-DRIVER2-02');
    expect($pageProps['outboundMetrics']['totalAssigned'])->toBe(1);

    // Admin visits dashboard -> sees both
    $responseAdmin = $this->actingAs($admin)->withCookies($adminCookies)->get("/{$this->team->slug}/deliveries");
    $responseAdmin->assertOk();
    $adminProps = $responseAdmin->original->getData()['page']['props'];
    $adminFulfillments = array_column($adminProps['outboundDeliveries']['data'], 'fulfillment_number');
    expect($adminFulfillments)->toContain('FUL-DRIVER1-01');
    expect($adminFulfillments)->toContain('FUL-DRIVER2-02');
    expect($adminProps['outboundMetrics']['totalAssigned'])->toBe(2);
});

function createTestSale(Team $team, User $user, string $invoicePrefix = 'INV-TEST'): Sale
{
    return Sale::create([
        'team_id' => $team->id,
        'invoice_number' => $invoicePrefix.'-'.uniqid(),
        'client_name' => 'Test Client Corp',
        'sale_date' => now(),
        'subtotal' => 1000.00,
        'discount' => 0.00,
        'tax_amount' => 0.00,
        'grand_total' => 1000.00,
        'status' => 'Completed',
        'processed_by_id' => $user->id,
    ]);
}

it('prevents delivery staff from updating another staff members delivery', function () {
    [$driver1, $driver1Cookies] = createDeliveryActor('staff_deliveries', $this->team);
    [$driver2, $driver2Cookies] = createDeliveryActor('staff_deliveries', $this->team);

    $sale = createTestSale($this->team, $driver1, 'INV-SEC');

    $ful = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-SEC-01',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Secret Client',
        'contact_number' => '09173333333',
        'status' => 'Pending',
        'handled_by_id' => $driver1->id,
    ]);

    // Driver 2 attempts to start Driver 1's delivery -> 403 Forbidden
    $response = $this->actingAs($driver2)->withCookies($driver2Cookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$ful->id}/start");
    $response->assertForbidden();
});

it('enforces strict three-attempt limit and disables further attempts after 3/3', function () {
    [$driver, $driverCookies] = createDeliveryActor('staff_deliveries', $this->team);

    $sale = createTestSale($this->team, $driver, 'INV-ATTEMPT');

    $fulfillment = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-ATTEMPT-3X',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Warehouse Recipient',
        'contact_number' => '09180009988',
        'delivery_address' => 'Bldg 5 Cyberzone, Ortigas',
        'status' => 'Pending',
        'attempt_count' => 0,
        'handled_by_id' => $driver->id,
    ]);

    // 1. Start delivery -> Out for Delivery
    $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/start")
        ->assertSessionHasNoErrors();
    expect($fulfillment->fresh()->status)->toBe('Out for Delivery');

    // 2. Failed attempt 1
    $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/attempt", [
        'reason' => 'Customer unavailable',
        'remarks' => 'Gate closed, security said office is closed today.',
    ])->assertSessionHasNoErrors();

    $fulfillment->refresh();
    expect($fulfillment->attempt_count)->toBe(1);
    expect($fulfillment->status)->toBe('Delivery Attempt Failed');
    expect($fulfillment->remaining_attempts)->toBe(2);

    // 3. Retry delivery -> Out for Delivery -> Failed attempt 2
    $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/start");
    $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/attempt", [
        'reason' => 'Customer unreachable',
        'remarks' => 'Called 4 times, phone kept ringing with no answer.',
    ])->assertSessionHasNoErrors();

    $fulfillment->refresh();
    expect($fulfillment->attempt_count)->toBe(2);
    expect($fulfillment->status)->toBe('Delivery Attempt Failed');
    expect($fulfillment->remaining_attempts)->toBe(1);

    // 4. Retry delivery -> Out for Delivery -> Failed attempt 3
    $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/start");
    $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/attempt", [
        'reason' => 'Customer refused delivery',
        'remarks' => 'Customer refused to accept package citing site renovation delay.',
    ])->assertSessionHasNoErrors();

    $fulfillment->refresh();
    expect($fulfillment->attempt_count)->toBe(3);
    expect($fulfillment->status)->toBe('Maximum Attempts Reached');
    expect($fulfillment->remaining_attempts)->toBe(0);

    // 5. Attempt 4 must be strictly blocked by the system
    $responseAttempt4 = $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/attempt", [
        'reason' => 'Other reason',
        'remarks' => 'Trying a fourth time anyway.',
    ]);

    $fulfillment->refresh();
    expect($fulfillment->attempt_count)->toBe(3); // count must remain 3
    expect($fulfillment->attemptLogs)->toHaveCount(6); // 3 start logs + 3 attempt logs
});

it('requires valid photo proof to mark delivery as Delivered and stops further attempts', function () {
    [$driver, $driverCookies] = createDeliveryActor('staff_deliveries', $this->team);

    $sale = createTestSale($this->team, $driver, 'INV-PROOF');

    $fulfillment = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-PROOF-TEST',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Engr. Santos',
        'contact_number' => '09178887766',
        'delivery_address' => 'Unit 102 Metro Tech Center',
        'status' => 'Out for Delivery',
        'attempt_count' => 1,
        'handled_by_id' => $driver->id,
    ]);

    // 1. Missing proof photo -> fails validation
    $responseNoProof = $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/deliver", [
        'recipient_name' => 'Engr. Santos',
    ]);
    $responseNoProof->assertSessionHasErrors('proof_of_delivery');

    // 2. Invalid file type (e.g. text/pdf instead of jpg/png) -> fails validation
    $fakePdf = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');
    $responsePdf = $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/deliver", [
        'proof_of_delivery' => $fakePdf,
    ]);
    $responsePdf->assertSessionHasErrors('proof_of_delivery');

    // 3. Valid JPG photo proof -> successfully marks Delivered
    $proofPhoto = UploadedFile::fake()->image('delivery_receipt.jpg', 600, 600);
    $responseSuccess = $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/deliver", [
        'proof_of_delivery' => $proofPhoto,
        'recipient_name' => 'Engr. Santos (Signed)',
        'remarks' => 'Received in good physical condition with 2 boxes.',
    ]);
    $responseSuccess->assertSessionHasNoErrors();

    $fulfillment->refresh();
    expect($fulfillment->status)->toBe('Delivered');
    expect($fulfillment->proof_of_delivery)->not->toBeNull();
    expect($fulfillment->delivered_at)->not->toBeNull();
    Storage::disk('local')->assertExists($fulfillment->proof_of_delivery);

    // Verify authenticated proof viewing
    $this->actingAs($driver)->withCookies($driverCookies)->get("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/proof")
        ->assertOk();

    // Delivered order stops further attempts
    $responsePostDelivered = $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/attempt", [
        'reason' => 'Other reason',
        'remarks' => 'Should be blocked.',
    ]);
    expect($fulfillment->fresh()->status)->toBe('Delivered');
});

it('allows administrator to reschedule delivery and reset attempts after limit is reached', function () {
    [$admin, $adminCookies] = createDeliveryActor('admin', $this->team);
    [$driver, $driverCookies] = createDeliveryActor('staff_deliveries', $this->team);

    $sale = createTestSale($this->team, $admin, 'INV-LIMIT');

    $fulfillment = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-LIMIT-REACHED',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Alpha Plant',
        'contact_number' => '09191234567',
        'status' => 'Maximum Attempts Reached',
        'attempt_count' => 3,
        'handled_by_id' => $driver->id,
    ]);

    // Driver cannot reschedule
    $this->actingAs($driver)->withCookies($driverCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/reschedule", [
        'delivery_schedule' => now()->addDays(2)->toDateTimeString(),
        'reset_attempts' => true,
    ])->assertForbidden();

    // Admin authorizes rescheduling with fresh attempts
    $newSchedule = now()->addDays(3)->toDateTimeString();
    $this->actingAs($admin)->withCookies($adminCookies)->post("/{$this->team->slug}/deliveries/fulfillments/{$fulfillment->id}/reschedule", [
        'delivery_schedule' => $newSchedule,
        'reset_attempts' => true,
        'notes' => 'Customer confirmed site is now open and ready for handover.',
    ])->assertSessionHasNoErrors();

    $fulfillment->refresh();
    expect($fulfillment->status)->toBe('Pending');
    expect($fulfillment->attempt_count)->toBe(0);
    expect($fulfillment->remaining_attempts)->toBe(3);
    expect($fulfillment->rescheduled_at)->not->toBeNull();
    expect($fulfillment->rescheduled_by_id)->toBe($admin->id);
});
