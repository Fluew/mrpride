<?php

use App\Enums\QuotationStatus;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\OrderFulfillment;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Services\ResendService;

/**
 * Automatic customer emails: quotation approved, sale completed, delivery out for delivery.
 * ResendService is mocked, so nothing leaves the machine; we record what would be sent.
 */
beforeEach(function () {
    $this->sent = collect();
    $this->sendOk = true;
    $this->mock(ResendService::class)
        ->shouldReceive('sendCustomerEmail')
        ->andReturnUsing(function ($to, $subject, $html, $kind) {
            $this->sent->push(compact('to', 'subject', 'html', 'kind'));

            return $this->sendOk
                ? ['success' => true, 'id' => 'test-id', 'error' => null]
                : ['success' => false, 'id' => null, 'error' => 'Resend API error (403): not allowed'];
        });

    $this->team = Team::factory()->create(['slug' => 'mail-team']);
});

function mailQuotation(Team $team, array $overrides = []): Quotation
{
    $quotation = Quotation::create($overrides + [
        'team_id' => $team->id,
        'quotation_number' => 'QT-2026-0001',
        'client_name' => 'Apex Builders',
        'contact_person' => 'Ana Reyes',
        'email' => 'ana@apex.example',
        'quotation_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'subtotal' => 2000, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'grand_total' => 2000,
        'status' => QuotationStatus::Pending,
    ]);
    $quotation->items()->create([
        'sku' => 'BRK-60A', 'product_name' => 'Circuit Breaker 60A', 'quantity' => 2, 'unit_price' => 1000, 'discount' => 0, 'subtotal' => 2000,
    ]);

    return $quotation;
}

function mailSale(Team $team, array $overrides = []): Sale
{
    $sale = Sale::create($overrides + [
        'team_id' => $team->id,
        'invoice_number' => 'INV-2026-0001',
        'client_name' => 'Apex Builders',
        'email' => 'ana@apex.example',
        'sale_date' => now(),
        'subtotal' => 1500, 'discount' => 0, 'tax_amount' => 0, 'grand_total' => 1500,
        'amount_paid' => 1500, 'outstanding_balance' => 0,
        'payment_method' => 'GCash', 'payment_reference' => 'GC-123',
        'status' => 'Pending Payment',
    ]);
    $sale->items()->create(['sku' => 'LED-40', 'product_name' => 'LED Light Bar', 'quantity' => 1, 'unit_price' => 1500, 'subtotal' => 1500]);

    return $sale;
}

// --- Quotation -----------------------------------------------------------------------------

it('emails the quotation to the client when it is approved', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password_changed_at' => now()]);
    $this->team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
    $admin->forceFill(['current_team_id' => $this->team->id])->save();
    $quotation = mailQuotation($this->team);

    expect($this->sent)->toHaveCount(0); // created as Pending: nothing yet

    $this->actingAs($admin)->withCookies(createTrustedDeviceFor($admin)['cookies'])
        ->patch("/mail-team/quotations/{$quotation->id}/status", ['status' => 'Approved'])
        ->assertSessionHasNoErrors();

    expect($this->sent)->toHaveCount(1);
    $mail = $this->sent->first();
    expect($mail['to'])->toBe('ana@apex.example')
        ->and($mail['subject'])->toContain('QT-2026-0001')
        ->and($mail['html'])->toContain('Circuit Breaker 60A')->toContain('₱2,000.00')->toContain('Ana Reyes');
    expect(AuditLog::where('event', 'email.quotation_sent')->exists())->toBeTrue();
});

it('does not email the quotation for other status changes', function () {
    $quotation = mailQuotation($this->team);
    $quotation->update(['status' => QuotationStatus::Rejected]);
    $quotation->update(['notes' => 'edited']);

    expect($this->sent)->toHaveCount(0);
});

it('falls back to the linked client email when the quotation has none', function () {
    $client = Client::create(['team_id' => $this->team->id, 'company_name' => 'Apex Builders', 'email' => 'buyer@apex.example']);
    $quotation = mailQuotation($this->team, ['email' => null, 'client_id' => $client->id]);

    $quotation->update(['status' => QuotationStatus::Approved]);

    expect($this->sent->first()['to'])->toBe('buyer@apex.example');
});

// --- Sale invoice --------------------------------------------------------------------------

it('emails the invoice when a sale is completed', function () {
    $sale = mailSale($this->team);
    expect($this->sent)->toHaveCount(0);

    $sale->update(['status' => 'Completed']);

    expect($this->sent)->toHaveCount(1);
    expect($this->sent->first()['to'])->toBe('ana@apex.example')
        ->and($this->sent->first()['subject'])->toContain('INV-2026-0001')
        ->and($this->sent->first()['html'])->toContain('LED Light Bar')->toContain('₱1,500.00')->toContain('GC-123');
});

it('uses the quotation email for sales converted from a quotation', function () {
    $quotation = mailQuotation($this->team, ['email' => 'from-quote@apex.example']);
    $sale = mailSale($this->team, ['email' => null, 'quotation_id' => $quotation->id]);

    $sale->update(['status' => 'Completed']);

    expect($this->sent->last()['to'])->toBe('from-quote@apex.example');
});

it('skips the invoice when the sale has no customer email (e.g. walk-in POS)', function () {
    $sale = mailSale($this->team, ['email' => null]);
    $sale->update(['status' => 'Completed']);

    expect($this->sent)->toHaveCount(0);
});

// --- Delivery in transit -------------------------------------------------------------------

it('tells the customer when the delivery goes out for delivery', function () {
    $sale = mailSale($this->team, ['status' => 'Completed']);
    $this->sent = collect(); // ignore the invoice email

    $fulfillment = OrderFulfillment::create([
        'team_id' => $this->team->id,
        'sale_id' => $sale->id,
        'fulfillment_number' => 'DLV-2026-0001',
        'fulfillment_type' => 'Delivery',
        'recipient_name' => 'Ana Reyes',
        'contact_number' => '09170000000',
        'delivery_address' => '12 Rizal St, Manila',
        'status' => 'Pending',
    ]);
    expect($this->sent)->toHaveCount(0);

    $fulfillment->update(['status' => 'Out for Delivery', 'dispatched_at' => now()]);

    expect($this->sent)->toHaveCount(1);
    expect($this->sent->first()['to'])->toBe('ana@apex.example')
        ->and($this->sent->first()['subject'])->toContain('out for delivery')
        ->and($this->sent->first()['html'])->toContain('DLV-2026-0001')->toContain('12 Rizal St, Manila');

    // Later status changes (Delivered) don't send this notice again.
    $fulfillment->update(['status' => 'Delivered']);
    expect($this->sent)->toHaveCount(1);
});

it('also sends the in-transit notice when the Sales page marks it Dispatched, but only once', function () {
    $sale = mailSale($this->team, ['status' => 'Completed']);
    $this->sent = collect();

    $fulfillment = OrderFulfillment::create([
        'team_id' => $this->team->id, 'sale_id' => $sale->id, 'fulfillment_number' => 'DLV-2026-0002',
        'fulfillment_type' => 'Delivery', 'recipient_name' => 'Ana Reyes', 'contact_number' => 'N/A', 'status' => 'Preparing',
    ]);

    $fulfillment->update(['status' => 'Dispatched']);
    $fulfillment->update(['status' => 'Out for Delivery']);

    expect($this->sent)->toHaveCount(1);
});

// --- Failures ------------------------------------------------------------------------------

it('records a failed send without blocking the status change', function () {
    $this->sendOk = false;
    $quotation = mailQuotation($this->team);

    $quotation->update(['status' => QuotationStatus::Approved]);

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Approved);
    $log = AuditLog::where('event', 'email.quotation_sent')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('FAILED');
});

it('escapes customer-entered text in the email', function () {
    $quotation = mailQuotation($this->team, ['contact_person' => '<script>alert(1)</script>']);
    $quotation->update(['status' => QuotationStatus::Approved]);

    expect($this->sent->first()['html'])->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});
