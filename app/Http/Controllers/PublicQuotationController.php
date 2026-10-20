<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicQuotationController extends Controller
{
    /**
     * Show public quotation request form with catalog products.
     */
    public function create(): Response
    {
        $products = Product::where('is_active', true)
            ->select('id', 'name', 'sku', 'category', 'unit', 'description', 'price')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return Inertia::render('Quotations/Create', [
            'products' => $products,
        ]);
    }

    /**
     * Store public quotation request with multi-product items and unlisted materials.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'contactName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'projectType' => ['nullable', 'string', 'max:100'],
            'timeline' => ['nullable', 'string', 'max:100'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.specifications' => ['nullable', 'string', 'max:500'],
            'hasOtherMaterials' => ['nullable', 'boolean'],
            'otherMaterials' => ['nullable', 'string', 'max:3000'],
            'additionalRequirements' => ['nullable', 'string', 'max:3000'],
        ]);

        // Custom validation: at least 1 valid catalog item OR non-empty otherMaterials
        $validItems = collect($request->input('items', []))->filter(function ($item) {
            return ! empty($item['product_id']) && (int) ($item['quantity'] ?? 0) >= 1;
        });

        $hasOther = $request->boolean('hasOtherMaterials') && filled(trim((string) $request->input('otherMaterials', '')));

        if ($validItems->isEmpty() && ! $hasOther) {
            return back()->withErrors([
                'items' => 'Please select at least one product from our catalog or describe unlisted materials in the section below.',
            ])->withInput();
        }

        $team = Team::first();
        if ($team) {
            $client = Client::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'company_name' => $validated['company'],
                ],
                [
                    'contact_person' => $validated['contactName'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                ]
            );

            $quotationNumber = Quotation::generateNumber($team->id);

            // Construct detailed notes for administrative review
            $notes = "Project Type: " . ($validated['projectType'] ?? 'General') . "\n";
            $notes .= "Expected Timeline: " . ($validated['timeline'] ?? 'Flexible') . "\n";

            if ($hasOther) {
                $notes .= "\n--- Unlisted Materials Requested ---\n" . trim((string) $request->input('otherMaterials')) . "\n";
            }

            if (! empty($request->input('additionalRequirements'))) {
                $notes .= "\n--- Additional Project Requirements & Instructions ---\n" . trim((string) $request->input('additionalRequirements')) . "\n";
            }

            $quotation = Quotation::create([
                'team_id' => $team->id,
                'quotation_number' => $quotationNumber,
                'client_id' => $client->id,
                'client_name' => $validated['company'],
                'contact_person' => $validated['contactName'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'quotation_date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(),
                'subtotal' => 0.00,
                'discount' => 0.00,
                'tax_rate' => 12.00,
                'tax_amount' => 0.00,
                'grand_total' => 0.00,
                'status' => QuotationStatus::Pending,
                'notes' => $notes,
            ]);

            $subtotal = 0.00;

            // Create QuotationItem records for each selected catalog item
            foreach ($validItems as $item) {
                $product = Product::find($item['product_id']);
                if (! $product) {
                    continue;
                }

                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = (float) ($product->price ?? 0);
                $itemSubtotal = $unitPrice * $qty;
                $subtotal += $itemSubtotal;

                $productName = $product->name;
                if (! empty($item['specifications'])) {
                    $productName .= ' (' . trim((string) $item['specifications']) . ')';
                }

                $quotation->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_name' => $productName,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => 0.00,
                    'subtotal' => $itemSubtotal,
                ]);
            }

            // Create QuotationItem for unlisted materials if provided
            if ($hasOther) {
                $otherDesc = trim((string) $request->input('otherMaterials'));
                $quotation->items()->create([
                    'product_id' => null,
                    'sku' => 'CUSTOM-REQ',
                    'product_name' => 'Custom / Unlisted Materials: ' . Str::limit($otherDesc, 75),
                    'quantity' => 1,
                    'unit_price' => 0.00,
                    'discount' => 0.00,
                    'subtotal' => 0.00,
                ]);
            }

            // Update financial totals on quotation
            $taxRate = 12.00;
            $taxAmount = round(($subtotal * $taxRate) / 100, 2);
            $grandTotal = $subtotal + $taxAmount;

            $quotation->update([
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
            ]);
        }

        return back()->with('success', 'Thank you! Your quotation request has been received. Our team will review your requested materials and get in touch shortly.');
    }
}
