<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    /**
     * Display a listing of quotations.
     */
    public function index(Request $request): Response
    {
        $team = $this->getTeam($request);
        abort_if(! $team, 404, 'Team not found.');

        // Automatically mark expired quotations
        Quotation::where('team_id', $team->id)
            ->whereIn('status', [QuotationStatus::Draft->value, QuotationStatus::Pending->value])
            ->where('valid_until', '<', now()->toDateString())
            ->update(['status' => QuotationStatus::Expired->value]);

        $query = Quotation::query()
            ->with(['items', 'client', 'preparedBy', 'sale'])
            ->where('team_id', $team->id);

        // Search query
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status !== 'All') {
                $query->where('status', $status);
            }
        }

        // Date range filter
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('quotation_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('quotation_date', '<=', $dateTo);
        }

        $quotations = $query->latest('quotation_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        // Summary metrics using SQL aggregates instead of loading all models into PHP memory
        $baseMetricsQuery = fn () => Quotation::where('team_id', $team->id);
        $metrics = [
            'totalQuotations' => $baseMetricsQuery()->count(),
            'pendingCount' => $baseMetricsQuery()->where('status', QuotationStatus::Pending->value)->count(),
            'approvedCount' => $baseMetricsQuery()->where('status', QuotationStatus::Approved->value)->count(),
            'totalQuotedValue' => (float) $baseMetricsQuery()->whereIn('status', [
                QuotationStatus::Pending->value,
                QuotationStatus::Approved->value,
                QuotationStatus::Completed->value,
            ])->sum('grand_total'),
        ];

        // Available inventory products for selection
        $products = Product::where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'category', 'price', 'stock', 'status']);

        // Existing client records for auto-complete/quick pick
        $clients = Client::where('team_id', $team->id)
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'contact_person', 'email', 'phone', 'address']);

        // Default next quotation number
        $nextNumber = Quotation::generateNumber($team->id);

        return Inertia::render('Quotations/AdminIndex', [
            'quotations' => $quotations,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'metrics' => $metrics,
            'products' => $products,
            'clients' => $clients,
            'nextNumber' => $nextNumber,
            'statusOptions' => collect(QuotationStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'badgeClass' => $s->badgeClass(),
            ]),
        ]);
    }

    /**
     * Store a newly created quotation.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $this->getTeam($request);
        abort_if(! $team, 404, 'Team not found.');

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:quotation_date'],
            'status' => ['required', Rule::enum(QuotationStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:2000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($team, $request, $validated) {
            // Find or create client
            $client = Client::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'company_name' => $validated['client_name'],
                ],
                [
                    'contact_person' => $validated['contact_person'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]
            );

            // Compute financial totals accurately
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $itemDiscount = (float) ($item['discount'] ?? 0);
                $unitPrice = (float) $item['unit_price'];
                $qty = (int) $item['quantity'];
                $itemSubtotal = max(0, ($unitPrice - $itemDiscount) * $qty);

                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product_id' => $item['product_id'] ?? null,
                    'sku' => $item['sku'],
                    'product_name' => $item['product_name'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $overallDiscount = (float) ($validated['discount'] ?? 0);
            $taxableAmount = max(0, $subtotal - $overallDiscount);
            $taxRate = isset($validated['tax_rate']) ? (float) $validated['tax_rate'] : 12.00;
            $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
            $grandTotal = $taxableAmount + $taxAmount;

            $quotationNumber = Quotation::generateNumber($team->id);

            $quotation = Quotation::create([
                'team_id' => $team->id,
                'quotation_number' => $quotationNumber,
                'client_id' => $client->id,
                'client_name' => $validated['client_name'],
                'contact_person' => $validated['contact_person'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'],
                'subtotal' => $subtotal,
                'discount' => $overallDiscount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? $this->defaultTerms(),
                'prepared_by_id' => $request->user()->id,
            ]);

            foreach ($itemsData as $data) {
                $quotation->items()->create($data);
            }
        });

        return back()->with('success', 'Quotation created successfully.');
    }

    /**
     * Update the specified quotation.
     */
    public function update(Request $request, mixed $current_team = null, mixed $quotation = null): RedirectResponse
    {
        $quotation = $this->resolveQuotation($quotation, $current_team);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $quotation->team_id !== $team->id, 403, 'Unauthorized quotation access.');

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:quotation_date'],
            'status' => ['required', Rule::enum(QuotationStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:2000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($quotation, $validated) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $itemDiscount = (float) ($item['discount'] ?? 0);
                $unitPrice = (float) $item['unit_price'];
                $qty = (int) $item['quantity'];
                $itemSubtotal = max(0, ($unitPrice - $itemDiscount) * $qty);

                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product_id' => $item['product_id'] ?? null,
                    'sku' => $item['sku'],
                    'product_name' => $item['product_name'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $overallDiscount = (float) ($validated['discount'] ?? 0);
            $taxableAmount = max(0, $subtotal - $overallDiscount);
            $taxRate = isset($validated['tax_rate']) ? (float) $validated['tax_rate'] : (float) $quotation->tax_rate;
            $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
            $grandTotal = $taxableAmount + $taxAmount;

            $quotation->update([
                'client_name' => $validated['client_name'],
                'contact_person' => $validated['contact_person'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'],
                'subtotal' => $subtotal,
                'discount' => $overallDiscount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? $quotation->terms_and_conditions,
            ]);

            // Re-sync line items
            $quotation->items()->delete();
            foreach ($itemsData as $data) {
                $quotation->items()->create($data);
            }
        });

        return back()->with('success', 'Quotation updated successfully.');
    }

    /**
     * Update quotation status.
     */
    public function updateStatus(Request $request, mixed $current_team = null, mixed $quotation = null): RedirectResponse
    {
        $quotation = $this->resolveQuotation($quotation, $current_team);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $quotation->team_id !== $team->id, 403, 'Unauthorized quotation access.');

        $validated = $request->validate([
            'status' => ['required', Rule::enum(QuotationStatus::class)],
        ]);

        $status = $validated['status'] instanceof QuotationStatus
            ? $validated['status']
            : QuotationStatus::from($validated['status']);

        $quotation->update([
            'status' => $status,
        ]);

        return back()->with('success', "Status updated to {$status->value}.");
    }

    /**
     * Convert an approved quotation to a sales transaction.
     */
    public function convertToSale(Request $request, mixed $current_team = null, mixed $quotation = null): RedirectResponse
    {
        $quotation = $this->resolveQuotation($quotation, $current_team);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $quotation->team_id !== $team->id, 403, 'Unauthorized quotation access.');

        if ($quotation->status !== QuotationStatus::Approved) {
            return back()->withErrors(['error' => 'Only Approved quotations can be converted to sales transactions.']);
        }

        if ($quotation->converted_to_sale_id) {
            return back()->withErrors(['error' => 'This quotation has already been converted to a sales transaction.']);
        }

        $sale = DB::transaction(function () use ($team, $request, $quotation) {
            $invoiceNumber = Sale::generateInvoiceNumber($team->id);

            // Create Sale record
            $sale = Sale::create([
                'team_id' => $team->id,
                'invoice_number' => $invoiceNumber,
                'quotation_id' => $quotation->id,
                'client_name' => $quotation->client_name,
                'contact_person' => $quotation->contact_person,
                'email' => $quotation->email,
                'phone' => $quotation->phone,
                'address' => $quotation->address,
                'sale_date' => now(),
                'subtotal' => $quotation->subtotal,
                'discount' => $quotation->discount,
                'tax_amount' => $quotation->tax_amount,
                'grand_total' => $quotation->grand_total,
                'status' => 'Completed',
                'payment_method' => $request->input('payment_method', 'Bank Transfer / Cash'),
                'notes' => "Converted from Quotation {$quotation->quotation_number}. " . ($quotation->notes ?? ''),
                'processed_by_id' => $request->user()->id,
            ]);

            // Transfer items & deduct inventory
            foreach ($quotation->items as $qItem) {
                $sale->items()->create([
                    'product_id' => $qItem->product_id,
                    'sku' => $qItem->sku,
                    'product_name' => $qItem->product_name,
                    'quantity' => $qItem->quantity,
                    'unit_price' => $qItem->unit_price,
                    'subtotal' => $qItem->subtotal,
                ]);

                // Deduct inventory when sale is executed
                if ($qItem->product_id) {
                    $product = Product::find($qItem->product_id);
                    if ($product) {
                        $prevStock = $product->stock;
                        $product->decrement('stock', $qItem->quantity);
                        $product->updateCalculatedStatus();

                        StockMovement::create([
                            'team_id' => $quotation->team_id,
                            'product_id' => $product->id,
                            'user_id' => $request->user()->id,
                            'type' => 'sale',
                            'quantity' => $qItem->quantity,
                            'previous_stock' => $prevStock,
                            'new_stock' => $product->stock,
                            'reference_number' => $sale->invoice_number,
                            'reason' => 'Sale Invoice Deduction',
                            'remarks' => "Deducted via Sale Conversion from Quotation {$quotation->quotation_number}",
                            'movement_date' => now(),
                        ]);
                    }
                }
            }

            // Mark quotation as completed and link sale
            $quotation->update([
                'status' => QuotationStatus::Completed,
                'converted_to_sale_id' => $sale->id,
            ]);

            return $sale;
        });

        return back()->with('success', "Quotation successfully converted to Sale Invoice {$sale->invoice_number}. Inventory has been updated.");
    }

    /**
     * Delete the specified quotation.
     */
    public function destroy(Request $request, mixed $current_team = null, mixed $quotation = null): RedirectResponse
    {
        $quotation = $this->resolveQuotation($quotation, $current_team);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $quotation->team_id !== $team->id, 403, 'Unauthorized quotation access.');

        $quotation->delete();

        return back()->with('success', 'Quotation deleted.');
    }

    /**
     * Helper to retrieve team from request or route parameter.
     */
    protected function getTeam(Request $request, mixed $teamParam = null): ?Team
    {
        if ($teamParam instanceof Team) {
            return $teamParam;
        }

        if (is_string($teamParam) && !empty($teamParam)) {
            $found = Team::where('slug', $teamParam)->first();
            if ($found) {
                return $found;
            }
        }

        $team = $request->route('current_team') ?? $request->route('team') ?? $teamParam;
        if (is_string($team)) {
            $found = Team::where('slug', $team)->first();
            if ($found) {
                return $found;
            }
        }
        if ($team instanceof Team) {
            return $team;
        }
        return $request->user()?->currentTeam;
    }

    /**
     * Resolve quotation from route parameters.
     */
    protected function resolveQuotation(mixed $quotation, mixed $fallback = null): Quotation
    {
        if ($quotation instanceof Quotation) {
            return $quotation;
        }

        if (is_numeric($quotation)) {
            return Quotation::findOrFail((int) $quotation);
        }

        if ($fallback instanceof Quotation) {
            return $fallback;
        }

        if (is_numeric($fallback)) {
            return Quotation::findOrFail((int) $fallback);
        }

        abort(404, 'Quotation not found.');
    }

    /**
     * Standard Electrical Supply Terms and Conditions template.
     */
    protected function defaultTerms(): string
    {
        return "1. Price Validity: Quotation is valid until the stated expiration date.\n"
             . "2. Payment Terms: 50% down payment upon order confirmation, 50% upon delivery/completion.\n"
             . "3. Delivery: Within 5-7 business days upon receipt of purchase order and down payment.\n"
             . "4. Warranty: 1-Year standard manufacturer warranty against factory defects.\n"
             . "5. Acceptance: To confirm this quote, please sign and return a copy via email.";
    }
}
