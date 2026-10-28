<?php

namespace App\Http\Controllers;

use App\Models\OrderFulfillment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $query = Sale::where('team_id', $team->id)->with(['items', 'fulfillment', 'verifiedBy:id,name', 'processedBy:id,name']);

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

        if (is_string($request->input('items'))) {
            $decoded = json_decode($request->input('items'), true);
            if (is_array($decoded)) {
                $request->merge(['items' => $decoded]);
            }
        }

        $validated = $request->validate([
            'customer' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', Rule::in(['Bank Transfer', 'GCash'])],
            'payment_reference' => ['required', 'string', 'max:100'],
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'proof_of_payment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
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

            $amountPaid = (float) $validated['amount_paid'];
            if ($amountPaid < $subtotal) {
                return back()->withErrors([
                    'amount_paid' => 'The amount paid cannot be less than the total amount.',
                ]);
            }

            $outstanding = max(0, round($subtotal - $amountPaid, 2));
            $isFullyPaid = $outstanding <= 0.001;

            // Proofs hold customer payment details, so keep them on the private disk and serve
            // them only through the authenticated proof() route.
            $proofPath = null;
            if ($request->hasFile('proof_of_payment')) {
                $proofPath = $request->file('proof_of_payment')->store('proofs/sales', 'local');
            }

            // Newly submitted proof requires staff verification. Otherwise if staff enters cash/direct fully paid, mark Verified.
            $paymentStatus = $proofPath ? 'Pending Verification' : ($isFullyPaid ? 'Verified' : 'Pending Verification');
            $saleStatus = ($isFullyPaid && $paymentStatus === 'Verified') ? 'Completed' : 'Pending Payment';

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
                'status' => $saleStatus,
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'],
                'amount_paid' => $amountPaid,
                'outstanding_balance' => $outstanding,
                'payment_date' => $validated['payment_date'],
                'payment_status' => $paymentStatus,
                'proof_of_payment' => $proofPath,
                'verified_by_id' => $paymentStatus === 'Verified' ? $request->user()->id : null,
                'verified_at' => $paymentStatus === 'Verified' ? now() : null,
                'notes' => $validated['notes'] ?? null,
                'processed_by_id' => $request->user()->id,
            ]);

            // Initialize Order Fulfillment
            OrderFulfillment::create([
                'team_id' => $team->id,
                'sale_id' => $sale->id,
                'fulfillment_number' => OrderFulfillment::generateFulfillmentNumber($team->id),
                'fulfillment_type' => $request->input('fulfillment_type', 'Delivery'),
                'recipient_name' => $request->input('recipient_name') ?: $validated['customer'],
                'contact_number' => $request->input('contact_number') ?: 'N/A',
                'delivery_address' => $request->input('delivery_address'),
                'delivery_schedule' => $request->input('delivery_schedule'),
                'instructions' => $request->input('delivery_instructions') ?: ($validated['notes'] ?? null),
                'status' => 'Preparing',
                'handled_by_id' => $request->user()->id,
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
    public function show(Request $request, mixed $current_team = null, mixed $sale = null): Response
    {
        $sale = $this->resolveSale($sale, $current_team, $request);
        $team = $request->user()->currentTeam;
        abort_if(! $team || $sale->team_id !== $team->id, 403);

        $sale->load([
            'items.product:id,name,sku',
            'processedBy:id,name',
            'verifiedBy:id,name',
            'fulfillment.handledBy:id,name',
        ]);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }

    /**
     * Verify or reject submitted proof of payment.
     */
    public function verifyPayment(Request $request, mixed $current_team = null, mixed $sale = null): RedirectResponse
    {
        $sale = $this->resolveSale($sale, $current_team, $request);
        $team = $request->user()->currentTeam;
        abort_if(! $team || $sale->team_id !== $team->id, 403, 'Unauthorized sale access.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Verified', 'Rejected'])],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['amount_paid'])) {
            $sale->amount_paid = (float) $validated['amount_paid'];
            $sale->outstanding_balance = max(0, round($sale->grand_total - $sale->amount_paid, 2));
        }

        $sale->payment_status = $validated['status'];
        $sale->verified_by_id = $request->user()->id;
        $sale->verified_at = now();

        if ($validated['status'] === 'Verified' && $sale->outstanding_balance <= 0.001) {
            $sale->status = 'Completed';
        } elseif ($validated['status'] === 'Rejected') {
            $sale->status = 'Pending Payment';
        }

        if (! empty($validated['notes'])) {
            $sale->notes = trim(($sale->notes ?? '')."\n[Payment Audit]: ".$validated['notes']);
        }

        $sale->save();

        return back()->with('success', "Payment {$validated['status']} successfully.");
    }

    /**
     * Store or update order fulfillment delivery details.
     */
    public function storeFulfillment(Request $request, mixed $current_team = null, mixed $sale = null): RedirectResponse
    {
        $sale = $this->resolveSale($sale, $current_team, $request);
        $team = $request->user()->currentTeam;
        abort_if(! $team || $sale->team_id !== $team->id, 403, 'Unauthorized sale access.');

        $validated = $request->validate([
            'fulfillment_type' => ['required', Rule::in(['Delivery', 'Store Pickup'])],
            'recipient_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'delivery_schedule' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $fulfillment = $sale->fulfillment ?: new OrderFulfillment;
        $fulfillment->fill([
            'team_id' => $team->id,
            'sale_id' => $sale->id,
            'fulfillment_number' => $fulfillment->fulfillment_number ?: OrderFulfillment::generateFulfillmentNumber($team->id),
            'fulfillment_type' => $validated['fulfillment_type'],
            'recipient_name' => $validated['recipient_name'],
            'contact_number' => $validated['contact_number'],
            'delivery_address' => $validated['delivery_address'] ?? null,
            'delivery_schedule' => $validated['delivery_schedule'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'status' => $fulfillment->status ?: 'Preparing',
            'handled_by_id' => $request->user()->id,
        ]);
        $fulfillment->save();

        return back()->with('success', 'Fulfillment details updated.');
    }

    /**
     * Update order fulfillment status (e.g. Preparing -> Dispatched -> Delivered).
     */
    public function updateFulfillmentStatus(Request $request, mixed $current_team = null, mixed $fulfillment = null): RedirectResponse
    {
        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $request->user()->currentTeam;
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized fulfillment access.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Preparing', 'Ready for Pickup', 'Dispatched', 'Delivered', 'Cancelled'])],
            'proof_of_delivery' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $fulfillment->status = $validated['status'];
        $fulfillment->handled_by_id = $request->user()->id;

        if ($validated['status'] === 'Dispatched' && ! $fulfillment->dispatched_at) {
            $fulfillment->dispatched_at = now();
        } elseif ($validated['status'] === 'Delivered') {
            if (! $fulfillment->dispatched_at) {
                $fulfillment->dispatched_at = now();
            }
            $fulfillment->delivered_at = now();
        }

        if ($request->hasFile('proof_of_delivery')) {
            $fulfillment->proof_of_delivery = $request->file('proof_of_delivery')->store('proofs/deliveries', 'local');
        }

        if (! empty($validated['notes'])) {
            $fulfillment->notes = trim(($fulfillment->notes ?? '')."\n".$validated['notes']);
        }

        $fulfillment->save();

        return back()->with('success', "Fulfillment status updated to {$validated['status']}.");
    }

    /**
     * Stream fulfillment proof of delivery file securely.
     */
    public function fulfillmentProof(Request $request, mixed $current_team = null, mixed $fulfillment = null): StreamedResponse
    {
        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $request->user()->currentTeam;
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized fulfillment access.');
        abort_if(! $fulfillment->proof_of_delivery, 404, 'No proof of delivery uploaded.');

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($fulfillment->proof_of_delivery)) {
                return Storage::disk($disk)->response($fulfillment->proof_of_delivery, null, [
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        abort(404, 'Proof of delivery file missing.');
    }

    /**
     * Resolve order fulfillment from route parameters.
     */
    protected function resolveFulfillment(mixed $fulfillment, mixed $fallback = null, ?Request $request = null): OrderFulfillment
    {
        if ($fulfillment instanceof OrderFulfillment) {
            return $fulfillment;
        }

        $candidates = array_filter([
            $fulfillment,
            $fallback,
            $request?->route('fulfillment'),
        ], fn ($val) => $val !== null && $val !== '');

        foreach ($candidates as $cand) {
            if ($cand instanceof OrderFulfillment) {
                return $cand;
            }
            if (is_numeric($cand)) {
                $found = OrderFulfillment::find((int) $cand);
                if ($found) {
                    return $found;
                }
            }
        }

        abort(404, 'Order fulfillment record not found.');
    }

    /**
     * Stream a sale's proof of payment to an authorised user of the same team.
     */
    public function proof(Request $request, mixed $current_team = null, mixed $sale = null): StreamedResponse
    {
        $sale = $this->resolveSale($sale, $current_team, $request);
        $team = $request->user()->currentTeam;
        abort_if(! $team || $sale->team_id !== $team->id, 403);
        abort_if(! $sale->proof_of_payment, 404);

        // Older uploads may still sit on the public disk; serve them from there too.
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($sale->proof_of_payment)) {
                return Storage::disk($disk)->response($sale->proof_of_payment, null, [
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        abort(404);
    }

    /**
     * Delete / void a sale transaction and restore inventory stock.
     */
    public function destroy(Request $request, mixed $current_team = null, mixed $sale = null): RedirectResponse
    {
        $sale = $this->resolveSale($sale, $current_team, $request);
        $team = $request->user()->currentTeam;
        abort_if(! $team || $sale->team_id !== $team->id, 403, 'Unauthorized sale access.');

        DB::transaction(function () use ($sale, $team, $request) {
            $sale->load('items.product');

            // Restore product stock and record stock movement for cancellation
            foreach ($sale->items as $item) {
                if ($item->product) {
                    $previousStock = $item->product->stock;
                    $item->product->increment('stock', $item->quantity);

                    StockMovement::create([
                        'team_id' => $team->id,
                        'product_id' => $item->product->id,
                        'user_id' => $request->user()->id,
                        'type' => 'adjustment',
                        'quantity' => $item->quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $previousStock + $item->quantity,
                        'reference_number' => $sale->invoice_number,
                        'reason' => "Sale {$sale->invoice_number} voided / deleted",
                        'movement_date' => now(),
                    ]);

                    $item->product->refresh();
                    $item->product->updateCalculatedStatus();
                }
            }

            $sale->delete();
        });

        return back()->with('success', "Sale {$sale->invoice_number} deleted successfully.");
    }

    /**
     * Resolve sale from route parameters.
     */
    protected function resolveSale(mixed $sale, mixed $fallback = null, ?Request $request = null): Sale
    {
        if ($sale instanceof Sale) {
            return $sale;
        }

        $candidates = array_filter([
            $sale,
            $fallback,
            $request?->route('sale'),
        ], fn ($val) => $val !== null && $val !== '');

        foreach ($candidates as $cand) {
            if ($cand instanceof Sale) {
                return $cand;
            }
            if (is_numeric($cand)) {
                $found = Sale::find((int) $cand);
                if ($found) {
                    return $found;
                }
            }
        }

        abort(404, 'Sale record not found.');
    }
}
