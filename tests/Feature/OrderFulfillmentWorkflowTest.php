<?php

use App\Enums\QuotationStatus;
use App\Enums\TeamRole;
use App\Models\OrderFulfillment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->team = Team::factory()->create();
});

function fulfillmentActor(string $role, Team $team): array
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

it('automatically initializes order fulfillment upon quotation conversion to sale', function () {
    [$user, $cookies] = fulfillmentActor('admin', $this->team);

    $product = Product::create([
        'team_id' => $this->team->id,
        'name' => 'Heavy Duty Magnetic Contactor',
        'sku' => 'HD-MC-TEST',
        'price' => 1200.00,
        'cost_price' => 800.00,
        'stock' => 10,
        'status' => 'In Stock',
        'is_active' => true,
    ]);

    $quotation = Quotation::create([
        'team_id' => $this->team->id,
        'quotation_number' => 'Q-FULFILL-001',
        'client_name' => 'Metro Power Corp',
        'contact_person' => 'Engr. Roberto Cruz',
        'phone' => '09181112233',
        'address' => 'Unit 402 Industrial Complex, Taguig City',
        'quotation_date' => now()->toDateString(),
        'valid_until' => now()->addDays(14)->toDateString(),
        'subtotal' => 2400.00,
        'discount' => 0.00,
        'tax_rate' => 12.00,
        'tax_amount' => 288.00,
        'grand_total' => 2688.00,
        'status' => QuotationStatus::Approved,
    ]);

    $quotation->items()->create([
        'product_id' => $product->id,
        'sku' => $product->sku,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 1200.00,
        'subtotal' => 2400.00,
    ]);

    $response = $this->actingAs($user)->withCookies($cookies)->post("/{$this->team->slug}/quotations/{$quotation->id}/convert-to-sale", [
        'payment_method' => 'Bank Transfer',
        'amount_paid' => 2688.00,
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Site Receiver Mark',
        'contact_number' => '09189998877',
        'delivery_address' => 'Substation Project B, Pasig',
        'delivery_instructions' => 'Call 30 mins before arrival',
    ]);

    $response->assertSessionHasNoErrors();

    $quotation->refresh();
    expect($quotation->status)->toBe(QuotationStatus::Completed);
    expect($quotation->converted_to_sale_id)->not->toBeNull();

    $sale = Sale::find($quotation->converted_to_sale_id);
    expect($sale)->not->toBeNull();
    expect($sale->fulfillment)->not->toBeNull();

    $fulfillment = $sale->fulfillment;
    expect($fulfillment->fulfillment_type)->toBe('Delivery');
    expect($fulfillment->recipient_name)->toBe('Site Receiver Mark');
    expect($fulfillment->status)->toBe('Preparing');

    // Product stock deducted once upon conversion
    $product->refresh();
    expect($product->stock)->toBe(8);
});

it('ensures fulfillment progression from preparing to delivered does not duplicate inventory movements', function () {
    [$user, $cookies] = fulfillmentActor('admin', $this->team);

    $product = Product::create([
        'team_id' => $this->team->id,
        'name' => 'Thermal Overload Relay',
        'sku' => 'TOR-TEST',
        'price' => 850.00,
        'stock' => 15,
        'is_active' => true,
    ]);

    $sale = Sale::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-FUL-002',
        'client_name' => 'Delta Engg',
        'sale_date' => now(),
        'subtotal' => 850.00,
        'discount' => 0.00,
        'tax_amount' => 0.00,
        'grand_total' => 850.00,
        'status' => 'Completed',
        'processed_by_id' => $user->id,
    ]);

    $fulfillment = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'FUL-TEST-002',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Site Engr Arthur',
        'contact_number' => '09170001122',
        'status' => 'Preparing',
        'handled_by_id' => $user->id,
    ]);

    $initialStock = $product->stock;
    $initialMovementsCount = StockMovement::where('product_id', $product->id)->count();

    // Advance to Dispatched
    $this->actingAs($user)->withCookies($cookies)->patch("/{$this->team->slug}/fulfillments/{$fulfillment->id}/status", [
        'status' => 'Dispatched',
        'notes' => 'Handed over to delivery van rider #12.',
    ])->assertSessionHasNoErrors();

    $fulfillment->refresh();
    expect($fulfillment->status)->toBe('Dispatched');
    expect($fulfillment->dispatched_at)->not->toBeNull();

    // Advance to Delivered with Proof
    $proof = UploadedFile::fake()->image('signed_waybill.jpg', 300, 300);
    $this->actingAs($user)->withCookies($cookies)->patch("/{$this->team->slug}/fulfillments/{$fulfillment->id}/status", [
        'status' => 'Delivered',
        'proof_of_delivery' => $proof,
        'notes' => 'Client signed received copy.',
    ])->assertSessionHasNoErrors();

    $fulfillment->refresh();
    expect($fulfillment->status)->toBe('Delivered');
    expect($fulfillment->delivered_at)->not->toBeNull();
    expect($fulfillment->proof_of_delivery)->not->toBeNull();

    // Stock must not change during dispatch or delivery
    $product->refresh();
    expect($product->stock)->toBe($initialStock);
    expect(StockMovement::where('product_id', $product->id)->count())->toBe($initialMovementsCount);
});
