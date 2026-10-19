<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    /**
     * Display a listing of sales transactions.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $search = $request->input('search');

        $query = Sale::where('team_id', $team->id)
            ->with(['items.product', 'processedBy:id,name']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        $sales = $query->orderByDesc('sale_date')->get()->map(function (Sale $sale) {
            return [
                'id' => $sale->invoice_number,
                'db_id' => $sale->id,
                'date' => $sale->sale_date ? $sale->sale_date->format('Y-m-d H:i') : $sale->created_at->format('Y-m-d H:i'),
                'customer' => $sale->client_name,
                'contact_person' => $sale->contact_person,
                'items_count' => $sale->items->sum('quantity'),
                'items' => $sale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                ]),
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) $sale->discount,
                'tax_amount' => (float) $sale->tax_amount,
                'total' => (float) $sale->grand_total,
                'status' => $sale->status ?? 'Completed',
                'payment_method' => $sale->payment_method ?? 'Cash / Bank Transfer',
                'processed_by' => $sale->processedBy?->name ?? 'System Staff',
            ];
        });

        // Load available inventory items for the POS terminal
        $products = Product::where('team_id', $team->id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'price', 'stock'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'price' => (float) ($p->price > 0 ? $p->price : 100),
                'stock' => $p->stock,
            ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'products' => $products,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Store a newly created direct sales / POS transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $validated = $request->validate([
            'customer' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($team, $request, $validated) {
            $invoiceNumber = Sale::generateInvoiceNumber($team->id);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += ($item['price'] * $item['qty']);
            }

            $sale = Sale::create([
                'team_id' => $team->id,
                'invoice_number' => $invoiceNumber,
                'client_name' => $validated['customer'],
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax_amount' => 0,
                'grand_total' => $subtotal,
                'status' => 'Completed',
                'payment_method' => $validated['payment_method'],
                'processed_by_id' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $itemData) {
                $product = Product::find($itemData['id']);
                if ($product) {
                    $itemSubtotal = $itemData['price'] * $itemData['qty'];
                    $sale->items()->create([
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'product_name' => $product->name,
                        'quantity' => $itemData['qty'],
                        'unit_price' => $itemData['price'],
                        'subtotal' => $itemSubtotal,
                    ]);

                    $previousStock = $product->stock;
                    $product->decrement('stock', $itemData['qty']);

                    StockMovement::create([
                        'team_id' => $team->id,
                        'product_id' => $product->id,
                        'user_id' => $request->user()->id,
                        'type' => 'sale',
                        'quantity' => $itemData['qty'],
                        'previous_stock' => $previousStock,
                        'new_stock' => max(0, $previousStock - $itemData['qty']),
                        'reference_number' => $invoiceNumber,
                        'reason' => 'Direct POS Sale',
                        'movement_date' => now(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Sale transaction completed and inventory updated.');
    }
}
