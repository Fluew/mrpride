<?php

use App\Enums\TeamRole;
use App\Models\Supplier;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('runs ahp evaluation with strictly 3 criteria (Price/Cost, Quality, Delivery Reliability)', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $user = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($user);

    $supplierA = Supplier::create([
        'team_id' => $team->id,
        'name' => 'Supplier Alpha',
        'email' => 'alpha@example.com',
        'status' => 'Active',
    ]);

    $supplierB = Supplier::create([
        'team_id' => $team->id,
        'name' => 'Supplier Beta',
        'email' => 'beta@example.com',
        'status' => 'Active',
    ]);

    // Submitting 3 criteria weights (price, quality, delivery)
    $response = $this->actingAs($user)
        ->withCookies($deviceData['cookies'])
        ->post("/{$team->slug}/suppliers/ahp", [
            'criteria_weights' => [
                'price' => 50,
                'quality' => 30,
                'delivery' => 20,
            ],
            'supplier_scores' => [
                [
                    'id' => $supplierA->id,
                    'price' => 9,
                    'quality' => 8,
                    'delivery' => 7,
                ],
                [
                    'id' => $supplierB->id,
                    'price' => 6,
                    'quality' => 9,
                    'delivery' => 9,
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success', 'AHP evaluation completed successfully.');

    $supplierA->refresh();
    $supplierB->refresh();

    // Supplier A calculation: (9/10 * 0.5) + (8/10 * 0.3) + (7/10 * 0.2) = 0.45 + 0.24 + 0.14 = 0.830
    expect((float) $supplierA->ahp_score)->toEqual(0.83);
    expect($supplierA->metrics)->toHaveKeys(['price', 'quality', 'delivery']);
    expect($supplierA->metrics)->not->toHaveKey('service');

    // Supplier B calculation: (6/10 * 0.5) + (9/10 * 0.3) + (9/10 * 0.2) = 0.30 + 0.27 + 0.18 = 0.750
    expect((float) $supplierB->ahp_score)->toEqual(0.75);
});

test('rejects ahp evaluation with old 4 criteria or missing 3 criteria weights', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);
    $user = User::factory()->create([
        'role' => 'admin',
        'current_team_id' => $team->id,
        'password_changed_at' => now(),
    ]);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $deviceData = createTrustedDeviceFor($user);

    $supplier = Supplier::create([
        'team_id' => $team->id,
        'name' => 'Supplier Gamma',
        'email' => 'gamma@example.com',
        'status' => 'Active',
    ]);

    // Submitting 4 criteria weights (including service) should fail size:3 validation
    $response = $this->actingAs($user)
        ->withCookies($deviceData['cookies'])
        ->post("/{$team->slug}/suppliers/ahp", [
            'criteria_weights' => [
                'price' => 45,
                'quality' => 30,
                'delivery' => 15,
                'service' => 10,
            ],
            'supplier_scores' => [
                [
                    'id' => $supplier->id,
                    'price' => 8,
                    'quality' => 8,
                    'delivery' => 8,
                ],
            ],
        ]);

    $response->assertSessionHasErrors(['criteria_weights']);
});
