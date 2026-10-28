<?php

use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->team = Team::factory()->create();
});

function paymentActor(string $role, Team $team): array
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

it('flags sale with proof of payment for staff verification and tracks balance', function () {
    [$user, $cookies] = paymentActor('admin', $this->team);

    $product = Product::create([
        'team_id' => $this->team->id,
        'name' => 'Schneider Circuit Breaker 30A',
        'sku' => 'SCB-30A-TEST',
        'category' => 'Breakers',
        'price' => 500.00,
        'cost_price' => 350.00,
        'stock' => 20,
        'status' => 'In Stock',
        'is_active' => true,
    ]);

    $proofFile = UploadedFile::fake()->image('gcash_receipt.png', 400, 400);

    $response = $this->actingAs($user)->withCookies($cookies)->post("/{$this->team->slug}/sales", [
        'customer' => 'Mega Builders Inc',
        'payment_method' => 'GCash',
        'payment_reference' => 'GCASH-99887766',
        'amount_paid' => 1000.00,
        'payment_date' => now()->toDateString(),
        'proof_of_payment' => $proofFile,
        'items' => [
            ['product_id' => $product->id, 'qty' => 2],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $sale = Sale::where('payment_reference', 'GCASH-99887766')->first();
    expect($sale)->not->toBeNull();
    expect($sale->payment_status)->toBe('Pending Verification');
    expect((float) $sale->outstanding_balance)->toBe(0.00);
    expect($sale->proof_of_payment)->not->toBeNull();
    Storage::disk('local')->assertExists($sale->proof_of_payment);
});

it('allows authorized staff to verify customer payment', function () {
    [$user, $cookies] = paymentActor('admin', $this->team);

    $sale = Sale::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-TEST-VERIFY-001',
        'client_name' => 'Titan Construction',
        'sale_date' => now(),
        'subtotal' => 2000.00,
        'discount' => 0.00,
        'tax_amount' => 0.00,
        'grand_total' => 2000.00,
        'status' => 'Pending Payment',
        'payment_method' => 'Bank Transfer',
        'payment_reference' => 'BDO-55443322',
        'amount_paid' => 2000.00,
        'outstanding_balance' => 0.00,
        'payment_status' => 'Pending Verification',
        'processed_by_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->withCookies($cookies)->post("/{$this->team->slug}/sales/{$sale->id}/verify-payment", [
        'status' => 'Verified',
        'notes' => 'Confirmed matching deposit on BDO online portal.',
    ]);

    $response->assertSessionHasNoErrors();
    $sale->refresh();

    expect($sale->payment_status)->toBe('Verified');
    expect($sale->status)->toBe('Completed');
    expect($sale->verified_by_id)->toBe($user->id);
    expect($sale->verified_at)->not->toBeNull();
});
