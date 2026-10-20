<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    /**
     * Display the sales management page with transaction history.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $search = $request->input('search');
        $status = $request->input('status', 'All');

        $query = Sale::where('team_id', $team->id)->with('items');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%");
            });
        }

        if ($status && $status !== 'All') {
            $query->where('status', $status);
        }

        $sales = $query->latest('sale_date')->latest('id')->paginate(15)->withQueryString();

        // Summary metrics
        $baseSalesQuery = fn () => Sale::where('team_id', $team->id);
        $metrics = [
            'totalSales' => $baseSalesQuery()->count(),
            'todaySales' => (float) $baseSalesQuery()->whereDate('sale_date', today())->sum('grand_total'),
            'monthSales' => (float) $baseSalesQuery()->whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year)->sum('grand_total'),
            'pendingPayments' => $baseSalesQuery()->where('status', 'Pending Payment')->count(),
        ];

        // Available products for POS
        $products = Product::where('team_id', $team->id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'price', 'stock', 'category']);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'metrics' => $metrics,
            'products' => $products,
        ]);
    }

    /**
     * Record a new sale / POS transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $validated = $request->validate([
            'customer' => ['required', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('team_id', $team->id)],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($validated, $team, $request) {
            $subtotal = 0;
            $saleItems = [];

            // Validate stock and build items
            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('team_id', $team->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->stock < $item['qty']) {
                    return back()->withErrors([
                        'items' => "Insufficient stock for {$product->name}. Available: {$product->stock}, requested: {$item['qty']}.",
                    ]);
                }

                $lineTotal = $product->price * $item['qty'];
                $subtotal += $lineTotal;

                $saleItems[] = [
                    'product' => $product,
                    'qty' => $item['qty'],
                    'unit_price' => $product->price,
                    'subtotal' => $lineTotal,
                ];
            }

            // Create the sale
            $sale = Sale::create([
                'team_id' => $team->id,
                'invoice_number' => Sale::generateInvoiceNumber($team->id),
                'client_name' => $validated['customer'],
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax_amount' => 0,
                'grand_total' => $subtotal,
                'status' => 'Completed',
                'payment_method' => $validated['payment_method'] ?? 'Cash / Bank Transfer',
                'notes' => $validated['notes'] ?? null,
                'processed_by_id' => $request->user()->id,
            ]);

            // Create sale items and deduct stock
            foreach ($saleItems as $sItem) {
                $product = $sItem['product'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'quantity' => $sItem['qty'],
                    'unit_price' => $sItem['unit_price'],
                    'subtotal' => $sItem['subtotal'],
                ]);

                $previousStock = $product->stock;
                $product->decrement('stock', $sItem['qty']);

                // Record stock movement
                StockMovement::create([
                    'team_id' => $team->id,
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'type' => 'sale',
                    'quantity' => -$sItem['qty'],
                    'previous_stock' => $previousStock,
                    'new_stock' => $previousStock - $sItem['qty'],
                    'reference_number' => $sale->invoice_number,
                    'reason' => "Sale to {$validated['customer']}",
                    'movement_date' => now(),
                ]);

                $product->refresh();
                $product->updateCalculatedStatus();
            }

            return back()->with('success', "Transaction {$sale->invoice_number} completed successfully.");
        });
    }

    /**
     * Show a single sale with all its items (for invoice view/print).
     */
    public function show(Request $request, Sale $sale): Response
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team || $sale->team_id !== $team->id, 403);

        $sale->load(['items.product:id,name,sku', 'processedBy:id,name']);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }
}
