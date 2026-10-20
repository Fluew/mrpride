<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    /**
     * Display procurement monitoring page.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $search = $request->input('search');
        $status = $request->input('status', 'All');

        $query = PurchaseRequest::where('team_id', $team->id)
            ->with([
                'product:id,name,sku',
                'supplier:id,name',
                'requestedBy:id,name',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status && $status !== 'All') {
            $query->where('status', $status);
        }

        $requests = $query->latest('requested_at')->latest('id')->paginate(15)->withQueryString();

        // Summary metrics
        $basePrQuery = fn () => PurchaseRequest::where('team_id', $team->id);
        $metrics = [
            'totalRequests' => $basePrQuery()->count(),
            'requestedCount' => $basePrQuery()->where('status', 'Requested')->count(),
            'orderedCount' => $basePrQuery()->where('status', 'Ordered')->count(),
            'deliveredCount' => $basePrQuery()->where('status', 'Delivered')->count(),
        ];

        // Available products and suppliers for the create form
        $products = Product::where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'cost_price', 'stock', 'reorder_level']);

        $suppliers = Supplier::where('team_id', $team->id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Procurement/Index', [
            'requests' => $requests,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'metrics' => $metrics,
            'products' => $products,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a new purchase request.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('team_id', $team->id)],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('team_id', $team->id)],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        PurchaseRequest::create([
            'team_id' => $team->id,
            'request_number' => PurchaseRequest::generateRequestNumber($team->id),
            'product_id' => $validated['product_id'],
            'supplier_id' => $validated['supplier_id'],
            'requested_by_id' => $request->user()->id,
            'quantity' => $validated['quantity'],
            'estimated_cost' => ($product->cost_price ?? $product->price) * $validated['quantity'],
            'status' => 'Requested',
            'notes' => $validated['notes'] ?? null,
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Purchase request submitted successfully.');
    }

    /**
     * Update the status of a purchase request.
     */
    public function updateStatus(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team || $purchaseRequest->team_id !== $team->id, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Requested', 'Ordered', 'Delivered', 'Cancelled'])],
        ]);

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'Ordered' && ! $purchaseRequest->ordered_at) {
            $updates['ordered_at'] = now();
        }

        if ($validated['status'] === 'Delivered' && ! $purchaseRequest->delivered_at) {
            $updates['delivered_at'] = now();
        }

        $purchaseRequest->update($updates);

        return back()->with('success', "Purchase request status updated to {$validated['status']}.");
    }

    /**
     * Delete a purchase request (only if status is Requested).
     */
    public function destroy(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team || $purchaseRequest->team_id !== $team->id, 403);

        if ($purchaseRequest->status !== 'Requested') {
            return back()->withErrors(['status' => 'Only requests in "Requested" status can be deleted.']);
        }

        $purchaseRequest->delete();

        return back()->with('success', 'Purchase request deleted.');
    }
}
