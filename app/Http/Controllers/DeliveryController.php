<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    /**
     * Display deliveries monitoring module.
     */
    public function index(Request $request): Response
    {
        $team = $this->getTeam($request);
        abort_if(! $team, 403, 'No active team selected.');

        $search = $request->input('search');
        $status = $request->input('status', 'All');
        $supplierId = $request->input('supplier_id', 'All');
        $unit = $request->input('unit', 'All');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Delivery::where('team_id', $team->id)
            ->with([
                'supplier:id,name',
                'purchaseOrder:id,po_number',
                'receivedBy:id,name',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('delivery_number', 'like', "%{$search}%")
                    ->orWhere('purchase_order_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status && $status !== 'All') {
            $query->where('status', $status);
        }

        if ($supplierId && $supplierId !== 'All') {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($unit && $unit !== 'All') {
            $query->where('unit', $unit);
        }

        if ($dateFrom) {
            $query->whereDate('delivery_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('delivery_date', '<=', $dateTo);
        }

        $deliveries = $query->latest('delivery_date')->latest('id')->paginate(15)->withQueryString();

        // Metrics
        $baseDeliveryQuery = fn () => Delivery::where('team_id', $team->id);
        $metrics = [
            'totalDeliveries' => $baseDeliveryQuery()->count(),
            'totalQuantityDelivered' => (int) $baseDeliveryQuery()->sum('quantity'),
            'linkedPosCount' => $baseDeliveryQuery()->whereNotNull('purchase_order_number')->where('purchase_order_number', '!=', '')->distinct('purchase_order_number')->count('purchase_order_number'),
            'deliveredCount' => $baseDeliveryQuery()->where('status', 'Delivered')->count(),
        ];

        // Catalogs for forms
        $products = Product::where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'cost_price', 'stock']);

        $suppliers = Supplier::where('team_id', $team->id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $purchaseOrders = PurchaseOrder::where('team_id', $team->id)
            ->latest('po_date')
            ->get(['id', 'po_number', 'item_sku', 'description', 'unit_of_measure', 'quantity', 'supplier_id']);

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'supplier_id' => $supplierId,
                'unit' => $unit,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'metrics' => $metrics,
            'products' => $products,
            'suppliers' => $suppliers,
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    /**
     * Store a new delivery record.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $this->getTeam($request);
        abort_if(! $team, 403, 'No active team selected.');

        $validated = $request->validate([
            'delivery_date' => ['required', 'date'],
            'delivery_number' => ['nullable', 'string', 'max:100'],
            'purchase_order_number' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->where('team_id', $team->id)],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:1000'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('team_id', $team->id)],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $deliveryNumber = ! empty($validated['delivery_number'])
            ? trim($validated['delivery_number'])
            : Delivery::generateDeliveryNumber($team->id, $validated['delivery_date']);

        $poNumber = ! empty($validated['purchase_order_number']) ? trim($validated['purchase_order_number']) : null;
        $poId = $validated['purchase_order_id'] ?? null;
        $supplierId = $validated['supplier_id'] ?? null;

        // Auto-link purchase order if PO number provided
        if ($poNumber && ! $poId) {
            $foundPo = PurchaseOrder::where('team_id', $team->id)
                ->where('po_number', $poNumber)
                ->first();
            if ($foundPo) {
                $poId = $foundPo->id;
                if (! $supplierId && $foundPo->supplier_id) {
                    $supplierId = $foundPo->supplier_id;
                }
            }
        } elseif ($poId && ! $poNumber) {
            $foundPo = PurchaseOrder::find($poId);
            if ($foundPo) {
                $poNumber = $foundPo->po_number;
                if (! $supplierId && $foundPo->supplier_id) {
                    $supplierId = $foundPo->supplier_id;
                }
            }
        }

        Delivery::create([
            'team_id' => $team->id,
            'delivery_date' => $validated['delivery_date'],
            'delivery_number' => $deliveryNumber,
            'purchase_order_number' => $poNumber,
            'purchase_order_id' => $poId,
            'quantity' => (int) $validated['quantity'],
            'unit' => ! empty($validated['unit']) ? trim($validated['unit']) : 'PCS',
            'description' => $validated['description'],
            'supplier_id' => $supplierId,
            'received_by_id' => $request->user()?->id,
            'status' => $validated['status'] ?? 'Delivered',
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Delivery recorded successfully.');
    }

    /**
     * Delete a delivery record.
     */
    public function destroy(Request $request, mixed $current_team = null, mixed $delivery = null): RedirectResponse
    {
        $delivery = $this->resolveDelivery($delivery, $current_team, $request);
        $team = $this->getTeam($request, $current_team);

        if (! $team) {
            $team = $delivery->team;
        }

        abort_if(! $team || $delivery->team_id !== $team->id, 403, 'Unauthorized delivery deletion.');

        $delivery->delete();

        return back()->with('success', 'Delivery record deleted successfully.');
    }

    /**
     * Process CSV Import for Deliveries.
     * Required headers: Date, Delivery Number, Purchase Order Number, Quantity, Unit, Description
     */
    public function importCsv(Request $request): JsonResponse|RedirectResponse
    {
        $team = $this->getTeam($request);
        abort_if(! $team, 404, 'Team not found.');

        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt'],
            'execute' => ['nullable', 'boolean'],
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

        $rawHeaders = $lines[0];
        $headers = array_map(function ($h) {
            $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            return preg_replace('/[^a-z0-9]/', '', strtolower($cleaned));
        }, $rawHeaders);

        $dateIdx = $this->findHeaderIndex($headers, ['date', 'deliverydate']);
        $delNumIdx = $this->findHeaderIndex($headers, ['deliverynumber', 'deliveryno', 'delno', 'delivery']);
        $poNumIdx = $this->findHeaderIndex($headers, ['purchaseordernumber', 'purchaseorderno', 'pono', 'ponumber', 'purchaseorder', 'po']);
        $qtyIdx = $this->findHeaderIndex($headers, ['quantity', 'qty', 'count']);
        $unitIdx = $this->findHeaderIndex($headers, ['unit', 'um', 'uom', 'unitofmeasure', 'measure']);
        $descIdx = $this->findHeaderIndex($headers, ['description', 'desc', 'itemdescription', 'item', 'product']);

        if ($dateIdx === -1 || $qtyIdx === -1 || $descIdx === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Required column headers missing. CSV must include headers: Date, Delivery Number, Purchase Order Number, Quantity, Unit, Description.',
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

            // 1. DATE
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

            // 2. DELIVERY NUMBER
            $rawDelNum = $delNumIdx !== -1 && isset($row[$delNumIdx]) ? trim($row[$delNumIdx]) : '';
            if ($rawDelNum === '' && $parsedDate) {
                $rawDelNum = Delivery::generateDeliveryNumber($team->id, $parsedDate);
            }

            // 3. PURCHASE ORDER NUMBER
            $rawPoNum = $poNumIdx !== -1 && isset($row[$poNumIdx]) ? trim($row[$poNumIdx]) : '';

            // 4. QUANTITY
            $rawQty = isset($row[$qtyIdx]) ? preg_replace('/[^0-9]/', '', (string) $row[$qtyIdx]) : '0';
            $qty = is_numeric($rawQty) ? (int) $rawQty : 0;
            if ($qty <= 0) {
                $errors[] = 'Quantity must be at least 1.';
            }

            // 5. UNIT
            $rawUnit = $unitIdx !== -1 && isset($row[$unitIdx]) && trim($row[$unitIdx]) !== '' ? trim($row[$unitIdx]) : 'PCS';

            // 6. DESCRIPTION
            $desc = isset($row[$descIdx]) ? trim($row[$descIdx]) : '';
            if ($desc === '') {
                $errors[] = 'Description is required.';
            }

            if (! empty($errors)) {
                $invalidRows[] = [
                    'row_number' => $rowNumber,
                    'date' => $rawDate,
                    'delivery_number' => $rawDelNum,
                    'purchase_order_number' => $rawPoNum,
                    'quantity' => $rawQty,
                    'unit' => $rawUnit,
                    'description' => $desc,
                    'errors' => $errors,
                ];
            } else {
                $validRows[] = [
                    'row_number' => $rowNumber,
                    'date' => $parsedDate,
                    'display_date' => $displayDate,
                    'delivery_number' => $rawDelNum,
                    'purchase_order_number' => $rawPoNum,
                    'quantity' => $qty,
                    'unit' => $rawUnit,
                    'description' => $desc,
                ];
            }
        }

        $execute = $request->boolean('execute');

        if ($execute && count($validRows) > 0) {
            DB::transaction(function () use ($team, $validRows, $request) {
                foreach ($validRows as $r) {
                    $poId = null;
                    $supplierId = null;
                    if (! empty($r['purchase_order_number'])) {
                        $po = PurchaseOrder::where('team_id', $team->id)->where('po_number', $r['purchase_order_number'])->first();
                        if ($po) {
                            $poId = $po->id;
                            $supplierId = $po->supplier_id;
                        }
                    }

                    Delivery::create([
                        'team_id' => $team->id,
                        'delivery_date' => $r['date'],
                        'delivery_number' => $r['delivery_number'],
                        'purchase_order_number' => $r['purchase_order_number'] ?: null,
                        'purchase_order_id' => $poId,
                        'quantity' => $r['quantity'],
                        'unit' => $r['unit'],
                        'description' => $r['description'],
                        'supplier_id' => $supplierId,
                        'received_by_id' => $request->user()?->id,
                        'status' => 'Delivered',
                    ]);
                }
            });

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully imported ' . count($validRows) . ' delivery record(s).',
                    'count' => count($validRows),
                    'invalid_count' => count($invalidRows),
                ]);
            }

            return back()->with('success', 'Successfully imported ' . count($validRows) . ' delivery record(s).');
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'total_rows' => count($lines) - 1,
                'valid_count' => count($validRows),
                'invalid_count' => count($invalidRows),
            ],
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
        ]);
    }

    /**
     * Find header index matching candidate strings.
     */
    protected function findHeaderIndex(array $headers, array $candidates): int
    {
        foreach ($headers as $index => $header) {
            foreach ($candidates as $candidate) {
                if ($header === $candidate) {
                    return $index;
                }
            }
        }
        return -1;
    }

    /**
     * Helper to retrieve team.
     */
    protected function getTeam(Request $request, mixed $teamParam = null): ?Team
    {
        if ($teamParam instanceof Team) {
            return $teamParam;
        }

        if (is_string($teamParam) && ! empty($teamParam)) {
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
     * Resolve delivery from route parameter.
     */
    protected function resolveDelivery(mixed $delivery, mixed $fallback = null, ?Request $request = null): Delivery
    {
        if ($delivery instanceof Delivery) {
            return $delivery;
        }

        $candidates = array_filter([
            $delivery,
            $fallback,
            $request?->route('delivery'),
        ], fn ($val) => $val !== null && $val !== '');

        foreach ($candidates as $cand) {
            if ($cand instanceof Delivery) {
                return $cand;
            }
            if (is_numeric($cand)) {
                $found = Delivery::find((int) $cand);
                if ($found) {
                    return $found;
                }
            }
            if (is_string($cand) && ! empty($cand)) {
                $found = Delivery::where('delivery_number', $cand)->first();
                if ($found) {
                    return $found;
                }
            }
        }

        abort(404, 'Delivery record not found.');
    }
}
