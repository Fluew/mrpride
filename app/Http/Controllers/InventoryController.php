<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Team;
use App\Services\Inventory\InventoryExporter;
use App\Services\MaterialList\ExtractionException;
use App\Services\MaterialList\FileReader;
use App\Services\MaterialList\MaterialParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryController extends Controller
{
    private const THRESHOLD_SQL = 'COALESCE(NULLIF(reorder_level, 0), NULLIF(min_stock, 0), 10)';

    /**
     * Active products for the inventory list, with the page's search / category / status filters.
     *
     * Sorted by stock availability so urgent items come first: out of stock, then low stock
     * (0 < stock <= threshold), then in stock; alphabetically within each group.
     */
    private function productListQuery(int $teamId, ?string $search, ?string $category, ?string $status): Builder
    {
        $thresholdSql = self::THRESHOLD_SQL;

        $query = Product::where('team_id', $teamId)
            ->where('is_active', true)
            ->with('supplier:id,name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category && $category !== 'All Categories') {
            $query->where('category', $category);
        }

        if ($status && $status !== 'All') {
            if ($status === 'Out of Stock') {
                $query->where('stock', '<=', 0);
            } elseif ($status === 'Low Stock') {
                $query->where('stock', '>', 0)->whereRaw("stock <= {$thresholdSql}");
            } elseif ($status === 'In Stock') {
                $query->whereRaw("stock > {$thresholdSql}");
            } elseif ($status === 'Needs Reorder') {
                $query->whereRaw("stock <= {$thresholdSql}");
            } else {
                $query->where('status', $status);
            }
        }

        return $query->orderByRaw("
            CASE
                WHEN stock <= 0 THEN 1
                WHEN stock <= {$thresholdSql} THEN 2
                ELSE 3
            END ASC
        ")->orderBy('name', 'asc');
    }

    /**
     * Display a listing of inventory products with metrics and filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $search = $request->input('search');
        $category = $request->input('category', 'All Categories');
        $status = $request->input('status', 'All');

        $thresholdSql = self::THRESHOLD_SQL;

        $products = $this->productListQuery($team->id, $search, $category, $status)
            ->paginate(10)
            ->withQueryString();

        // Calculate inventory summary counts using dynamic stock thresholds
        $baseCountQuery = fn () => Product::where('team_id', $team->id)->where('is_active', true);

        $totalItems = $baseCountQuery()->count();
        $outOfStockCount = $baseCountQuery()->where('stock', '<=', 0)->count();
        $lowStockCount = $baseCountQuery()->where('stock', '>', 0)->whereRaw("stock <= {$thresholdSql}")->count();
        $inStockCount = $baseCountQuery()->whereRaw("stock > {$thresholdSql}")->count();
        $requiringReorderCount = $baseCountQuery()->whereRaw("stock <= {$thresholdSql}")->count();

        // Categories list
        $categories = Product::where('team_id', $team->id)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();
        if (empty($categories)) {
            $categories = ['Breakers', 'Wiring', 'Lighting', 'Controls', 'Tools', 'General'];
        }

        // Suppliers list
        $suppliers = Supplier::where('team_id', $team->id)
            ->where('status', 'Active')
            ->select('id', 'name', 'contact_person', 'phone')
            ->orderBy('name')
            ->get();

        // Recent stock movements for audit (filtered by search, category, and status when supplied)
        $movementsQuery = StockMovement::where('team_id', $team->id)
            ->with(['product:id,name,sku,category,status,stock,reorder_level,min_stock', 'user:id,name', 'supplier:id,name'])
            ->latest('movement_date');

        if ($search) {
            $movementsQuery->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        }

        if ($category && $category !== 'All Categories') {
            $movementsQuery->whereHas('product', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }

        if ($status && $status !== 'All') {
            $movementsQuery->whereHas('product', function ($q) use ($status, $thresholdSql) {
                if ($status === 'Out of Stock') {
                    $q->where('stock', '<=', 0);
                } elseif ($status === 'Low Stock') {
                    $q->where('stock', '>', 0)->whereRaw("stock <= {$thresholdSql}");
                } elseif ($status === 'In Stock') {
                    $q->whereRaw("stock > {$thresholdSql}");
                } elseif ($status === 'Needs Reorder') {
                    $q->whereRaw("stock <= {$thresholdSql}");
                } else {
                    $q->where('status', $status);
                }
            });
        }

        $recentMovements = $movementsQuery->take(50)->get();

        return Inertia::render('Inventory/Index', [
            'products' => $products,
            'summary' => [
                'total' => $totalItems,
                'in_stock' => $inStockCount,
                'low_stock' => $lowStockCount,
                'out_of_stock' => $outOfStockCount,
                'requiring_reorder' => $requiringReorderCount,
            ],
            'categories' => array_values(array_unique(array_merge(['Breakers', 'Wiring', 'Lighting', 'Controls', 'Tools', 'General'], $categories))),
            'suppliers' => $suppliers,
            'recent_movements' => $recentMovements,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'view' => $request->input('view', 'table'),
            ],
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products')->where(fn ($q) => $q->where('team_id', $team->id)),
            ],
            'category' => ['required', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $reorderLevel = (int) ($validated['reorder_level'] ?? 10);
        $reorderQty = (int) ($validated['reorder_quantity'] ?? 50);
        $stock = (int) $validated['stock'];

        $status = 'In Stock';
        if ($stock <= 0) {
            $status = 'Out of Stock';
        } elseif ($stock <= $reorderLevel) {
            $status = 'Low Stock';
        }

        DB::transaction(function () use ($team, $request, $validated, $reorderLevel, $reorderQty, $stock, $status) {
            $product = Product::create([
                'team_id' => $team->id,
                'sku' => strtoupper(trim($validated['sku'])),
                'name' => trim($validated['name']),
                'category' => $validated['category'],
                'brand' => filled($validated['brand'] ?? null) ? trim($validated['brand']) : null,
                'unit' => $validated['unit'] ?? 'pcs',
                'price' => $validated['price'],
                'cost_price' => $validated['cost_price'] ?? 0.00,
                'stock' => $stock,
                'min_stock' => $reorderLevel,
                'reorder_level' => $reorderLevel,
                'reorder_quantity' => $reorderQty,
                'status' => $status,
                'description' => $validated['description'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'is_active' => true,
            ]);

            // If initial stock is greater than 0, create initial movement log
            if ($stock > 0) {
                StockMovement::create([
                    'team_id' => $team->id,
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'supplier_id' => $product->supplier_id,
                    'type' => 'stock_in',
                    'quantity' => $stock,
                    'previous_stock' => 0,
                    'new_stock' => $stock,
                    'reference_number' => 'INIT-'.$product->sku,
                    'reason' => 'Initial Stock Entry',
                    'remarks' => 'Initial stock registered upon product creation.',
                    'movement_date' => now(),
                ]);
            }
        });

        return back()->with('success', "Product '{$validated['name']}' created successfully!");
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, mixed $current_team = null, mixed $product = null): RedirectResponse
    {
        $product = $this->resolveProduct($product, $current_team, $request);
        $team = $request->user()->currentTeam;
        if (! $team || $product->team_id !== $team->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products')->where(fn ($q) => $q->where('team_id', $team->id))->ignore($product->id),
            ],
            'category' => ['required', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $reorderLevel = (int) ($validated['reorder_level'] ?? 10);
        $reorderQty = (int) ($validated['reorder_quantity'] ?? 50);

        $product->update([
            'sku' => strtoupper(trim($validated['sku'])),
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'brand' => filled($validated['brand'] ?? null) ? trim($validated['brand']) : null,
            'unit' => $validated['unit'] ?? $product->unit ?? 'pcs',
            'price' => $validated['price'],
            'cost_price' => $validated['cost_price'] ?? $product->cost_price,
            'min_stock' => $reorderLevel,
            'reorder_level' => $reorderLevel,
            'reorder_quantity' => $reorderQty,
            'description' => $validated['description'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
        ]);

        $product->updateCalculatedStatus();

        return back()->with('success', "Product '{$product->name}' updated successfully!");
    }

    /**
     * Remove or archive the specified product.
     */
    public function destroy(Request $request, mixed $current_team = null, mixed $product = null): RedirectResponse
    {
        $product = $this->resolveProduct($product, $current_team, $request);
        $team = $request->user()->currentTeam;
        if (! $team || $product->team_id !== $team->id) {
            abort(403);
        }

        $name = $product->name;

        // If product has linked quotations or sales, soft archive it
        if ($product->quotationItems()->exists() || $product->saleItems()->exists() || $product->stockMovements()->exists()) {
            $product->update(['is_active' => false]);

            return back()->with('info', "Product '{$name}' has been archived from active inventory.");
        }

        $product->delete();

        return back()->with('success', "Product '{$name}' deleted successfully.");
    }

    /**
     * Record a Stock-In operation.
     */
    public function stockIn(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('team_id', $team->id)],
            'quantity' => ['required', 'integer', 'min:1'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('team_id', $team->id)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'movement_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($team, $request, $validated) {
            $product = Product::lockForUpdate()->find($validated['product_id']);
            $prevStock = $product->stock;
            $qty = (int) $validated['quantity'];
            $newStock = $prevStock + $qty;

            $product->stock = $newStock;
            $product->save();
            $product->updateCalculatedStatus();

            StockMovement::create([
                'team_id' => $team->id,
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'supplier_id' => $validated['supplier_id'] ?? $product->supplier_id,
                'type' => 'stock_in',
                'quantity' => $qty,
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'reference_number' => $validated['reference_number'] ?: ('IN-'.strtoupper(uniqid())),
                'reason' => 'Supplier Restock',
                'remarks' => $validated['remarks'] ?? null,
                'movement_date' => $validated['movement_date'] ?? now(),
            ]);
        });

        return back()->with('success', 'Stock-In recorded successfully! Inventory updated.');
    }

    /**
     * Record a Stock-Out operation.
     */
    public function stockOut(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('team_id', $team->id)],
            'quantity' => ['required', 'integer', 'min:1'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:100'],
            'movement_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::find($validated['product_id']);
        $qty = (int) $validated['quantity'];

        if ($qty > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock. Available quantity: {$product->stock}.",
            ]);
        }

        DB::transaction(function () use ($team, $request, $validated, $product, $qty) {
            $product = Product::lockForUpdate()->find($product->id);
            $prevStock = $product->stock;

            if ($qty > $prevStock) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock. Available quantity: {$prevStock}.",
                ]);
            }

            $newStock = $prevStock - $qty;
            $product->stock = $newStock;
            $product->save();
            $product->updateCalculatedStatus();

            StockMovement::create([
                'team_id' => $team->id,
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'type' => 'stock_out',
                'quantity' => $qty,
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'reference_number' => $validated['reference_number'] ?: ('OUT-'.strtoupper(uniqid())),
                'reason' => $validated['reason'],
                'remarks' => $validated['remarks'] ?? null,
                'movement_date' => $validated['movement_date'] ?? now(),
            ]);
        });

        return back()->with('success', 'Stock-Out recorded successfully! Inventory updated.');
    }

    /**
     * Record an Inventory Adjustment for physical count corrections.
     */
    public function adjustStock(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('team_id', $team->id)],
            'physical_count' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'in:Physical Count Correction,Damaged,Lost,Data Correction'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($team, $request, $validated) {
            $product = Product::lockForUpdate()->find($validated['product_id']);
            $prevStock = $product->stock;
            $newStock = (int) $validated['physical_count'];
            $diff = $newStock - $prevStock;

            $product->stock = $newStock;
            $product->save();
            $product->updateCalculatedStatus();

            StockMovement::create([
                'team_id' => $team->id,
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'type' => 'adjustment',
                'quantity' => abs($diff),
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'reference_number' => 'ADJ-'.strtoupper(uniqid()),
                'reason' => $validated['reason'],
                'remarks' => $validated['remarks'] ?: "Adjusted from {$prevStock} to {$newStock} ({$diff})",
                'movement_date' => now(),
            ]);
        });

        return back()->with('success', 'Inventory adjustment successfully recorded and audited.');
    }

    /**
     * Get stock movement history for a product or whole inventory.
     */
    public function history(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $productId = $request->input('product_id');

        $query = StockMovement::where('team_id', $team->id)
            ->with(['product:id,name,sku', 'user:id,name', 'supplier:id,name'])
            ->latest('movement_date');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $movements = $query->paginate(20);

        return response()->json($movements);
    }

    /**
     * Process CSV Import: supports previewing and executing imports.
     */
    public function importCsv(Request $request, FileReader $reader): JsonResponse|RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:'.implode(',', [...FileReader::EXTENSIONS, 'txt'])],
            'mode' => ['nullable', 'in:append,replace'],
            'execute' => ['nullable', 'boolean'],
        ], [
            'file.max' => 'The file is larger than 10 MB. Please upload a smaller file.',
            'file.extensions' => 'This file type is not supported. Please upload a PDF, Excel (.xlsx/.xls), CSV, JPG or PNG file.',
        ]);

        // Replace mode archives (inventory) or overwrites (quotations) existing records, so it is admin-only.
        abort_if($request->input('mode') === 'replace' && ! $request->user()->isAdmin(), 403, 'Only administrators can use Replace mode.');

        try {
            [$lines, $method] = $this->readImportFile($request->file('file'), $reader);
        } catch (ExtractionException $e) {
            return $this->importFailure($request, $e->getMessage());
        }
        $ocr = in_array($method, ['ocr', 'pdf-ocr'], true);

        // The header row is usually first, but PDFs and photos often have a title above it.
        // Below the first row only exact header names count, so a data row is never taken for one.
        $headerAt = null;
        foreach (array_slice($lines, 0, 30, true) as $i => $row) {
            if ($this->isHeaderRow($row, $i > 0)) {
                $headerAt = $i;
                break;
            }
        }
        $cols = $headerAt !== null ? $this->importColumns($lines[$headerAt]) : null;

        if (count($lines) < 2) {
            return $this->importFailure($request, 'The uploaded file is empty or missing headers.');
        }
        if ($headerAt === null) {
            return $this->importFailure($request, $ocr
                ? 'We could not find the SKU and Product Name columns in this file. If it is a photo or scan, make sure the column headers are clearly visible, or upload an Excel/CSV file instead.'
                : 'Required column headers missing. The file must include SKU and Product Name columns.');
        }

        [
            'sku' => $skuIdx, 'name' => $nameIdx, 'category' => $catIdx, 'brand' => $brandIdx, 'unit' => $unitIdx,
            'cost' => $costIdx, 'price' => $priceIdx, 'stock' => $stockIdx, 'reorder_level' => $reorderLvlIdx, 'reorder_qty' => $reorderQtyIdx,
        ] = $cols;

        $validRows = [];
        $invalidRows = [];
        $seenSkus = [];

        // Fetch existing SKUs in team for uniqueness check
        $existingSkus = Product::where('team_id', $team->id)->pluck('id', 'sku')->toArray();

        for ($i = $headerAt + 1; $i < count($lines); $i++) {
            $row = $lines[$i];
            if (implode('', $row) === '') {
                continue;
            }
            if ($ocr) {
                // OCR misreads digits as look-alike letters ("1O" for 10, "l5" for 15).
                foreach ([$costIdx, $priceIdx, $stockIdx, $reorderLvlIdx, $reorderQtyIdx] as $idx) {
                    if ($idx !== -1 && isset($row[$idx]) && preg_match('/\d/', $row[$idx]) && preg_match('/^[\d\s.,₱OoIl|]+$/u', $row[$idx])) {
                        $row[$idx] = strtr($row[$idx], ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', '|' => '1']);
                    }
                }
            }

            $rowNumber = $i + 1;
            $sku = isset($row[$skuIdx]) ? strtoupper(trim($row[$skuIdx])) : '';
            $name = isset($row[$nameIdx]) ? trim($row[$nameIdx]) : '';
            $category = ($catIdx !== -1 && isset($row[$catIdx]) && trim($row[$catIdx]) !== '') ? trim($row[$catIdx]) : 'General';
            $brand = ($brandIdx !== -1 && isset($row[$brandIdx]) && trim($row[$brandIdx]) !== '') ? mb_substr(trim($row[$brandIdx]), 0, 100) : null;
            $unit = ($unitIdx !== -1 && isset($row[$unitIdx]) && trim($row[$unitIdx]) !== '') ? trim($row[$unitIdx]) : 'pcs';

            $costPriceStr = $costIdx !== -1 && isset($row[$costIdx]) ? preg_replace('/[^0-9.-]/', '', (string) $row[$costIdx]) : '0';
            $costPrice = is_numeric($costPriceStr) ? (float) $costPriceStr : 0.00;

            $priceStr = $priceIdx !== -1 && isset($row[$priceIdx]) ? preg_replace('/[^0-9.-]/', '', (string) $row[$priceIdx]) : '0';
            $price = is_numeric($priceStr) ? (float) $priceStr : 0.00;

            $stockStr = $stockIdx !== -1 && isset($row[$stockIdx]) ? preg_replace('/[^0-9-]/', '', (string) $row[$stockIdx]) : '0';
            $stock = is_numeric($stockStr) ? (int) $stockStr : 0;

            $reorderLvlStr = $reorderLvlIdx !== -1 && isset($row[$reorderLvlIdx]) ? preg_replace('/[^0-9-]/', '', (string) $row[$reorderLvlIdx]) : '10';
            $reorderLevel = is_numeric($reorderLvlStr) ? (int) $reorderLvlStr : 10;

            $reorderQtyStr = $reorderQtyIdx !== -1 && isset($row[$reorderQtyIdx]) ? preg_replace('/[^0-9-]/', '', (string) $row[$reorderQtyIdx]) : '50';
            $reorderQuantity = is_numeric($reorderQtyStr) ? (int) $reorderQtyStr : 50;

            // Validation rules
            $errors = [];
            if ($sku === '') {
                $errors[] = 'SKU is required.';
            }
            if ($name === '') {
                $errors[] = 'Product Name is required.';
            }
            if ($price < 0) {
                $errors[] = 'Selling price cannot be negative.';
            }
            if ($costPrice < 0) {
                $errors[] = 'Cost price cannot be negative.';
            }
            if ($stock < 0) {
                $errors[] = 'Stock quantity cannot be negative.';
            }
            if ($reorderLevel < 0) {
                $errors[] = 'Reorder level cannot be negative.';
            }
            if ($reorderQuantity < 0) {
                $errors[] = 'Reorder quantity cannot be negative.';
            }
            if (isset($seenSkus[$sku])) {
                $errors[] = "Duplicate SKU '{$sku}' found in row {$seenSkus[$sku]}.";
            } else {
                $seenSkus[$sku] = $rowNumber;
            }

            if (! empty($errors)) {
                $invalidRows[] = [
                    'row' => $rowNumber,
                    'sku' => $sku ?: 'N/A',
                    'name' => $name ?: 'N/A',
                    'errors' => $errors,
                ];
            } else {
                $status = 'In Stock';
                if ($stock <= 0) {
                    $status = 'Out of Stock';
                } elseif ($stock <= $reorderLevel) {
                    $status = 'Low Stock';
                }

                $validRows[] = [
                    'sku' => $sku,
                    'name' => $name,
                    'category' => $category,
                    'brand' => $brand,
                    'unit' => $unit,
                    'cost_price' => $costPrice,
                    'price' => $price,
                    'stock' => $stock,
                    'reorder_level' => $reorderLevel,
                    'reorder_quantity' => $reorderQuantity,
                    'status' => $status,
                    'is_update' => isset($existingSkus[$sku]),
                ];
            }
        }

        // If not executing, return JSON preview response
        if (! $request->boolean('execute')) {
            return response()->json([
                'success' => true,
                'method' => $method,
                'warning' => $ocr ? 'This file was read with text recognition (OCR). Check every row against the original before importing.' : null,
                'total_rows' => count($lines) - $headerAt - 1,
                'valid_count' => count($validRows),
                'invalid_count' => count($invalidRows),
                'valid_rows' => $validRows,
                'invalid_rows' => $invalidRows,
            ]);
        }

        // Execute import in database transaction
        $mode = $request->input('mode', 'append');
        $importedCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($team, $request, $validRows, $mode, &$importedCount, &$updatedCount) {
            if ($mode === 'replace') {
                // Soft archive or clear active products
                Product::where('team_id', $team->id)->update(['is_active' => false]);
            }

            foreach ($validRows as $item) {
                $existing = Product::where('team_id', $team->id)->where('sku', $item['sku'])->first();

                if ($existing) {
                    $prevStock = $existing->stock;
                    $existing->update([
                        'name' => $item['name'],
                        'category' => $item['category'],
                        'unit' => $item['unit'],
                        'cost_price' => $item['cost_price'],
                        'price' => $item['price'],
                        'stock' => $item['stock'],
                        'min_stock' => $item['reorder_level'],
                        'reorder_level' => $item['reorder_level'],
                        'reorder_quantity' => $item['reorder_quantity'],
                        'status' => $item['status'],
                        'is_active' => true,
                    ] + ($item['brand'] !== null ? ['brand' => $item['brand']] : []));

                    if ($prevStock !== $item['stock']) {
                        StockMovement::create([
                            'team_id' => $team->id,
                            'product_id' => $existing->id,
                            'user_id' => $request->user()->id,
                            'type' => 'adjustment',
                            'quantity' => abs($item['stock'] - $prevStock),
                            'previous_stock' => $prevStock,
                            'new_stock' => $item['stock'],
                            'reference_number' => 'CSV-IMP-'.strtoupper(uniqid()),
                            'reason' => 'File Import Update',
                            'remarks' => "Stock updated via file import from {$prevStock} to {$item['stock']}.",
                            'movement_date' => now(),
                        ]);
                    }
                    $updatedCount++;
                } else {
                    $product = Product::create([
                        'team_id' => $team->id,
                        'sku' => $item['sku'],
                        'name' => $item['name'],
                        'category' => $item['category'],
                        'brand' => $item['brand'],
                        'unit' => $item['unit'],
                        'cost_price' => $item['cost_price'],
                        'price' => $item['price'],
                        'stock' => $item['stock'],
                        'min_stock' => $item['reorder_level'],
                        'reorder_level' => $item['reorder_level'],
                        'reorder_quantity' => $item['reorder_quantity'],
                        'status' => $item['status'],
                        'is_active' => true,
                    ]);

                    if ($item['stock'] > 0) {
                        StockMovement::create([
                            'team_id' => $team->id,
                            'product_id' => $product->id,
                            'user_id' => $request->user()->id,
                            'type' => 'stock_in',
                            'quantity' => $item['stock'],
                            'previous_stock' => 0,
                            'new_stock' => $item['stock'],
                            'reference_number' => 'CSV-NEW-'.strtoupper(uniqid()),
                            'reason' => 'File Import New Entry',
                            'remarks' => "New product registered with initial stock {$item['stock']}.",
                            'movement_date' => now(),
                        ]);
                    }
                    $importedCount++;
                }
            }
        });

        return back()->with('success', "Import completed! {$importedCount} new products added, {$updatedCount} updated.");
    }

    /**
     * Export active inventory records to CSV.
     */
    public function exportCsv(Request $request, InventoryExporter $exporter): SymfonyResponse
    {
        $request->merge(['format' => 'csv']);

        return $this->export($request, $exporter);
    }

    /**
     * Download the inventory list (with the page's current filters) as CSV, Excel, PDF, PNG or JPG.
     */
    public function export(Request $request, InventoryExporter $exporter): SymfonyResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team, 403, 'No active team selected.');

        $validated = $request->validate([
            'format' => ['nullable', Rule::in(InventoryExporter::FORMATS)],
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $search = $validated['search'] ?? null;
        $category = $validated['category'] ?? 'All Categories';
        $status = $validated['status'] ?? 'All';
        $products = $this->productListQuery($team->id, $search, $category, $status)->get();

        $filters = array_filter([
            $search ? "Search: \"{$search}\"" : null,
            $category !== 'All Categories' ? "Category: {$category}" : null,
            $status !== 'All' ? "Status: {$status}" : null,
        ]);

        return $exporter->download($validated['format'] ?? 'csv', $products, [
            'team' => $team->name,
            'filters' => $filters ? implode(', ', $filters) : 'All active products',
        ]);
    }

    /**
     * Read an uploaded inventory file (PDF, Excel, CSV, JPG, PNG) into rows of text cells.
     * Spreadsheets are read as they are. PDFs and images are rebuilt from word positions so
     * their columns stay apart (FileReader::readTable); plain text is the fallback.
     *
     * @return array{0: array<int, array<int, string>>, 1: string} rows and how they were read
     */
    private function readImportFile(UploadedFile $file, FileReader $reader): array
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $name = strtolower($file->getClientOriginalExtension()) === 'txt' ? 'import.csv' : $file->getClientOriginalName();
        $type = $reader->detectType($path, $name);

        try {
            $read = $reader->readTable($path, $type, fn (array $cells) => $this->isHeaderRow($cells, true))
                ?? $reader->read($path, $type);
        } catch (ExtractionException $e) {
            // The shared reader's wording is written for the quotation form's material list.
            throw new ExtractionException(match ($e->reason) {
                'ocr_unavailable' => 'Text recognition (OCR) is not available on the server right now, so photos and scanned PDFs cannot be read. Please upload an Excel or CSV file instead.',
                'ocr_failed' => 'Text recognition failed or took too long for this file. Please try a clearer image, or upload an Excel or CSV file.',
                'pdf_render_failed' => 'We could not read this scanned PDF. Please upload a clearer copy, or an Excel or CSV file.',
                default => $e->getMessage(),
            }, $e->reason);
        }

        $rows = isset($read['rows'])
            ? $read['rows']
            : array_map(fn ($line) => explode("\t", $line), MaterialParser::cleanLines($read['pages']));

        $rows = array_map(fn ($row) => array_map(function ($cell) {
            if (is_float($cell) && floor($cell) === $cell) {
                $cell = (int) $cell;
            }

            return is_scalar($cell) && ! is_bool($cell) ? trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell)) : '';
        }, array_values($row)), $rows);

        return [$rows, $read['method']];
    }

    /**
     * Column positions in a header row (-1 when absent). $exactOnly skips the loose
     * "contains" matching, for telling a header apart from data rows below a title.
     */
    private function importColumns(array $row, bool $exactOnly = false): array
    {
        $headers = array_map(fn ($h) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $h)), $row);
        $find = fn (array $candidates) => $this->findHeaderIndex($headers, $candidates, $exactOnly);

        return [
            'sku' => $find(['sku', 'code', 'partnumber', 'itemcode', 'id']),
            'name' => $find(['productname', 'name', 'itemname', 'description', 'title', 'product']),
            'category' => $find(['category', 'cat', 'type', 'group']),
            'brand' => $find(['brand', 'brandname', 'manufacturer', 'make']),
            'unit' => $find(['unit', 'uom', 'measure']),
            'cost' => $find(['costprice', 'cost', 'purchaseprice', 'unitcost']),
            'price' => $find(['price', 'sellingprice', 'unitprice', 'rate']),
            'stock' => $find(['stock', 'currentstock', 'qty', 'quantity', 'count']),
            'reorder_level' => $find(['reorderlevel', 'minstock', 'threshold', 'minlevel', 'reorder']),
            'reorder_qty' => $find(['reorderquantity', 'reorderqty', 'suggestedqty', 'orderqty']),
        ];
    }

    /** A header row names both an SKU column and a product name column. */
    private function isHeaderRow(array $row, bool $exactOnly): bool
    {
        $cols = $this->importColumns($row, $exactOnly);

        return $cols['sku'] !== -1 && $cols['name'] !== -1 && $cols['sku'] !== $cols['name'];
    }

    /** Preview requests get JSON; the final import (an Inertia visit) gets a validation error. */
    private function importFailure(Request $request, string $message): JsonResponse
    {
        if ($request->boolean('execute')) {
            throw ValidationException::withMessages(['file' => $message]);
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'valid_rows' => [],
            'invalid_rows' => [],
        ], 422);
    }

    /**
     * Helper to find matching header index.
     */
    private function findHeaderIndex(array $headers, array $candidates, bool $exactOnly = false): int
    {
        // Exact match first
        foreach ($candidates as $cand) {
            foreach ($headers as $idx => $header) {
                if ($header === $cand) {
                    return $idx;
                }
            }
        }
        if ($exactOnly) {
            return -1;
        }

        // Substring match
        foreach ($candidates as $cand) {
            foreach ($headers as $idx => $header) {
                if ($header !== '' && (str_contains($header, $cand) || str_contains($cand, $header))) {
                    return $idx;
                }
            }
        }

        return -1;
    }

    /**
     * Resolve product from route parameters.
     */
    protected function resolveProduct(mixed $product, mixed $fallback = null, ?Request $request = null): Product
    {
        if ($product instanceof Product) {
            return $product;
        }

        $candidates = array_filter([
            $product,
            $fallback,
            $request?->route('product'),
        ], fn ($val) => $val !== null && $val !== '');

        foreach ($candidates as $cand) {
            if ($cand instanceof Product) {
                return $cand;
            }
            if (is_numeric($cand)) {
                $found = Product::find((int) $cand);
                if ($found) {
                    return $found;
                }
            }
        }

        abort(404, 'Product not found.');
    }
}
