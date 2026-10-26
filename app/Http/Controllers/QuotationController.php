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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $firstSku = $products->first()?->sku;
        $nextNumber = Quotation::generateNumber($team->id, $firstSku);

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

            $firstSku = $validated['items'][0]['sku'] ?? null;
            $quotationNumber = Quotation::generateNumber($team->id, $firstSku, $validated['quotation_date']);

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

    /**
     * Process CSV Import for Quotations.
     * Required headers: DATE, QUOTE NUMBER, QUANTITY, DESCRIPTION, UNIT PRICE, AMOUNT
     */
    public function importCsv(Request $request): JsonResponse|RedirectResponse
    {
        $team = $this->getTeam($request);
        abort_if(! $team, 404, 'Team not found.');

        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt'],
            'mode' => ['nullable', 'in:append,replace'],
            'execute' => ['nullable', 'boolean'],
            'default_client_name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $filePath = $file->getRealPath() ?: $file->getPathname();
        $content = file_get_contents($filePath);
        $rawLines = preg_split('/\r\n|\n|\r/', trim($content));

        if (empty($rawLines) || count($rawLines) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'The uploaded CSV file is empty or missing headers.',
                'valid_rows' => [],
                'invalid_rows' => [],
            ], 422);
        }

        // Determine delimiter from first line (support tab, comma, semicolon)
        $firstLine = $rawLines[0];
        $delimiter = ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        $lines = [];
        foreach ($rawLines as $rawLine) {
            if (trim($rawLine) === '') {
                continue;
            }
            $row = str_getcsv($rawLine, $delimiter);
            if (! empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                $lines[] = $row;
            }
        }

        if (count($lines) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'The uploaded CSV file contains no data rows.',
                'valid_rows' => [],
                'invalid_rows' => [],
            ], 422);
        }

        // Clean headers (remove UTF-8 BOM, trim, lowercase, strip non-alphanumeric)
        $rawHeaders = $lines[0];
        $headers = array_map(function ($h) {
            $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            return preg_replace('/[^a-z0-9]/', '', strtolower($cleaned));
        }, $rawHeaders);

        $dateIdx = $this->findHeaderIndex($headers, ['date', 'quotedate', 'quotationdate']);
        $quoteNumIdx = $this->findHeaderIndex($headers, ['quotenumber', 'quoteno', 'quotationnumber', 'quote', 'quotation']);
        $qtyIdx = $this->findHeaderIndex($headers, ['quantity', 'qty', 'count']);
        $descIdx = $this->findHeaderIndex($headers, ['description', 'productname', 'itemname', 'product', 'item', 'desc']);
        $priceIdx = $this->findHeaderIndex($headers, ['unitprice', 'price', 'rate', 'cost', 'unitcost']);
        $amountIdx = $this->findHeaderIndex($headers, ['amount', 'total', 'subtotal', 'totalamount', 'linetotal']);

        if ($dateIdx === -1 || $qtyIdx === -1 || $descIdx === -1 || $priceIdx === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Required column headers missing. CSV must include headers: DATE, QUOTE NUMBER, QUANTITY, DESCRIPTION, UNIT PRICE, AMOUNT.',
                'valid_rows' => [],
                'invalid_rows' => [],
            ], 422);
        }

        $validRows = [];
        $invalidRows = [];

        for ($i = 1; $i < count($lines); $i++) {
            $row = $lines[$i];
            if (empty(array_filter($row))) {
                continue;
            }

            $rowNumber = $i + 1;
            $errors = [];

            // 1. DATE parsing (support MONTH-DAY-YEAR e.g. 10-25-2026, 01-04-2023, MM/DD/YYYY, YYYY-MM-DD)
            $rawDate = isset($row[$dateIdx]) ? trim($row[$dateIdx]) : '';
            $parsedDate = null;
            $displayDate = $rawDate;

            if ($rawDate === '') {
                $errors[] = 'Date is required.';
            } else {
                if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $rawDate, $m)) {
                    $month = (int) $m[1];
                    $day = (int) $m[2];
                    $year = (int) $m[3];
                    if (checkdate($month, $day, $year)) {
                        $parsedDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $displayDate = sprintf('%02d-%02d-%04d', $month, $day, $year);
                    } else {
                        $errors[] = "Invalid calendar date: '{$rawDate}'.";
                    }
                } else {
                    try {
                        $carbon = Carbon::parse($rawDate);
                        $parsedDate = $carbon->toDateString();
                        $displayDate = $carbon->format('m-d-Y');
                    } catch (\Throwable $e) {
                        $errors[] = "Invalid date format: '{$rawDate}'. Expected MONTH-DAY-YEAR (eg 10-25-2026).";
                    }
                }
            }

            // 2. QUANTITY
            $rawQty = isset($row[$qtyIdx]) ? preg_replace('/[^0-9]/', '', (string) $row[$qtyIdx]) : '0';
            $qty = is_numeric($rawQty) ? (int) $rawQty : 0;
            if ($qty <= 0) {
                $errors[] = 'Quantity must be at least 1.';
            }

            // 3. DESCRIPTION
            $desc = isset($row[$descIdx]) ? trim($row[$descIdx]) : '';
            if ($desc === '') {
                $errors[] = 'Description is required.';
            }

            // 4. UNIT PRICE
            $rawPrice = isset($row[$priceIdx]) ? preg_replace('/[^0-9.-]/', '', (string) $row[$priceIdx]) : '0';
            $unitPrice = is_numeric($rawPrice) ? (float) $rawPrice : 0.00;
            if ($unitPrice < 0) {
                $errors[] = 'Unit price cannot be negative.';
            }

            // 5. AMOUNT
            $rawAmount = $amountIdx !== -1 && isset($row[$amountIdx]) ? preg_replace('/[^0-9.-]/', '', (string) $row[$amountIdx]) : '';
            $amount = (is_numeric($rawAmount) && (float) $rawAmount > 0)
                ? (float) $rawAmount
                : round($qty * $unitPrice, 2);

            // 6. QUOTE NUMBER (e.g. Q010423-068SSCR2)
            $rawQuoteNum = $quoteNumIdx !== -1 && isset($row[$quoteNumIdx]) ? trim($row[$quoteNumIdx]) : '';
            $extractedSku = null;

            if ($rawQuoteNum !== '') {
                if (preg_match('/^[Qq]\d{6,8}-([A-Za-z0-9_-]+)$/', $rawQuoteNum, $skuMatch)) {
                    $extractedSku = strtoupper($skuMatch[1]);
                } elseif (str_contains($rawQuoteNum, '-')) {
                    $parts = explode('-', $rawQuoteNum, 2);
                    $extractedSku = strtoupper(trim($parts[1]));
                }
            } else {
                $extractedSku = 'PROD';
                $rawQuoteNum = Quotation::generateNumber($team->id, $extractedSku, $parsedDate);
            }

            if (! empty($errors)) {
                $invalidRows[] = [
                    'row_number' => $rowNumber,
                    'date' => $rawDate,
                    'quote_number' => $rawQuoteNum,
                    'quantity' => $rawQty,
                    'description' => $desc,
                    'unit_price' => $rawPrice,
                    'amount' => $rawAmount,
                    'errors' => $errors,
                ];
            } else {
                $validRows[] = [
                    'row_number' => $rowNumber,
                    'date' => $parsedDate,
                    'display_date' => $displayDate,
                    'quote_number' => $rawQuoteNum,
                    'sku' => $extractedSku ?: 'SKU-' . strtoupper(substr(md5($desc), 0, 6)),
                    'quantity' => $qty,
                    'description' => $desc,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ];
            }
        }

        // Preview validation mode
        if (! $request->boolean('execute')) {
            return response()->json([
                'success' => true,
                'total_rows' => count($lines) - 1,
                'valid_count' => count($validRows),
                'invalid_count' => count($invalidRows),
                'valid_rows' => array_slice($validRows, 0, 50),
                'invalid_rows' => $invalidRows,
            ]);
        }

        if (empty($validRows)) {
            return back()->withErrors(['error' => 'No valid rows found to import from the CSV.']);
        }

        $mode = $request->input('mode', 'append');
        $defaultClientName = $request->input('default_client_name') ?: 'Direct Customer';

        // Group items by Quote Number
        $grouped = [];
        foreach ($validRows as $item) {
            $qNum = $item['quote_number'];
            $grouped[$qNum][] = $item;
        }

        DB::transaction(function () use ($team, $grouped, $mode, $defaultClientName, $request) {
            $client = Client::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'company_name' => $defaultClientName,
                ],
                [
                    'contact_person' => 'Direct Quotation Contact',
                ]
            );

            foreach ($grouped as $quoteNumber => $items) {
                $first = $items[0];
                $qDate = $first['date'];
                $validUntil = Carbon::parse($qDate)->addDays(30)->toDateString();
                $totalAmount = array_sum(array_column($items, 'amount'));

                $existing = Quotation::where('team_id', $team->id)->where('quotation_number', $quoteNumber)->first();

                if ($existing && $mode === 'replace') {
                    $quotation = $existing;
                    $quotation->update([
                        'quotation_date' => $qDate,
                        'valid_until' => $validUntil,
                        'subtotal' => $totalAmount,
                        'grand_total' => $totalAmount,
                        'status' => QuotationStatus::Pending,
                    ]);
                    $quotation->items()->delete();
                } else {
                    $finalQuoteNumber = $quoteNumber;
                    if ($existing && $mode === 'append') {
                        $counter = 1;
                        do {
                            $finalQuoteNumber = sprintf('%s-%02d', $quoteNumber, $counter);
                            $counter++;
                        } while (Quotation::where('team_id', $team->id)->where('quotation_number', $finalQuoteNumber)->exists());
                    }

                    $quotation = Quotation::create([
                        'team_id' => $team->id,
                        'quotation_number' => $finalQuoteNumber,
                        'client_id' => $client->id,
                        'client_name' => $client->company_name,
                        'quotation_date' => $qDate,
                        'valid_until' => $validUntil,
                        'subtotal' => $totalAmount,
                        'discount' => 0.00,
                        'tax_rate' => 0.00,
                        'tax_amount' => 0.00,
                        'grand_total' => $totalAmount,
                        'status' => QuotationStatus::Pending,
                        'notes' => 'Imported via CSV file with headers: DATE, QUOTE NUMBER, QUANTITY, DESCRIPTION, UNIT PRICE, AMOUNT',
                        'terms_and_conditions' => $this->defaultTerms(),
                        'prepared_by_id' => $request->user()?->id,
                    ]);
                }

                foreach ($items as $item) {
                    $matchedProduct = Product::where('team_id', $team->id)
                        ->where(function ($q) use ($item) {
                            $q->where('sku', $item['sku'])
                              ->orWhere('name', $item['description']);
                        })->first();

                    $quotation->items()->create([
                        'product_id' => $matchedProduct?->id,
                        'sku' => $matchedProduct?->sku ?? $item['sku'],
                        'product_name' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount' => 0.00,
                        'subtotal' => $item['amount'],
                    ]);
                }
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully imported ' . count($grouped) . ' quotation(s) with ' . count($validRows) . ' line item(s).',
            ]);
        }

        return back()->with('success', 'Successfully imported ' . count($grouped) . ' quotation(s) with ' . count($validRows) . ' line item(s).');
    }

    /**
     * Helper to find matching header index.
     */
    protected function findHeaderIndex(array $headers, array $candidates): int
    {
        foreach ($candidates as $cand) {
            $idx = array_search($cand, $headers, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }
        foreach ($candidates as $cand) {
            foreach ($headers as $idx => $h) {
                if (str_contains($h, $cand)) {
                    return (int) $idx;
                }
            }
        }
        return -1;
    }
}
