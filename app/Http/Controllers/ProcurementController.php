<?php

namespace App\Http\Controllers;

use App\Models\ProcurementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    /**
     * Display a listing of procurement requests.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        // Seed initial records if empty
        if (ProcurementRequest::where('team_id', $team->id)->count() === 0) {
            $this->seedInitialRequests($team->id);
        }

        $requests = ProcurementRequest::where('team_id', $team->id)
            ->latest('date_requested')
            ->get()
            ->map(fn (ProcurementRequest $pr) => [
                'id' => $pr->request_id,
                'db_id' => $pr->id,
                'item' => $pr->item_name,
                'supplier' => $pr->supplier_name,
                'qty' => $pr->quantity,
                'date' => $pr->date_requested ? $pr->date_requested->format('M d, Y') : $pr->created_at->format('M d, Y'),
                'status' => $pr->status,
                'notes' => $pr->notes ?? '',
            ]);

        $suppliers = Supplier::where('team_id', $team->id)->get(['id', 'name']);
        $products = Product::where('team_id', $team->id)->where('is_active', true)->get(['id', 'name', 'sku']);

        return Inertia::render('Procurement/Index', [
            'requests' => $requests,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created procurement request.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'supplier' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1'],
            'product_id' => ['nullable', 'exists:products,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $requestId = ProcurementRequest::generateRequestId($team->id);

        ProcurementRequest::create([
            'team_id' => $team->id,
            'request_id' => $requestId,
            'product_id' => $validated['product_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'item_name' => $validated['item'],
            'supplier_name' => $validated['supplier'],
            'quantity' => $validated['qty'],
            'status' => 'Requested',
            'date_requested' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', "Purchase request {$requestId} created successfully.");
    }

    /**
     * Cycle / update procurement request status.
     */
    public function updateStatus(Request $request, ProcurementRequest $procurement): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team || $procurement->team_id !== $team->id) {
            abort(403, 'Request not found in current workspace.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Requested,Ordered,Delivered,Cancelled'],
        ]);

        $oldStatus = $procurement->status;
        $newStatus = $validated['status'];

        DB::transaction(function () use ($procurement, $oldStatus, $newStatus, $team, $request) {
            $procurement->update(['status' => $newStatus]);

            // If newly delivered and linked to a product, automatically stock-in!
            if ($newStatus === 'Delivered' && $oldStatus !== 'Delivered' && $procurement->product_id) {
                $product = Product::find($procurement->product_id);
                if ($product) {
                    $prevStock = $product->stock;
                    $product->increment('stock', $procurement->quantity);

                    StockMovement::create([
                        'team_id' => $team->id,
                        'product_id' => $product->id,
                        'user_id' => $request->user()->id,
                        'supplier_id' => $procurement->supplier_id,
                        'type' => 'stock_in',
                        'quantity' => $procurement->quantity,
                        'previous_stock' => $prevStock,
                        'new_stock' => $prevStock + $procurement->quantity,
                        'reference_number' => $procurement->request_id,
                        'reason' => 'Purchase Request Delivered',
                        'movement_date' => now(),
                    ]);
                }
            }
        });

        return back()->with('success', "Request {$procurement->request_id} updated to {$newStatus}.");
    }

    /**
     * Delete procurement request.
     */
    public function destroy(Request $request, ProcurementRequest $procurement): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team || $procurement->team_id !== $team->id) {
            abort(403, 'Request not found in current workspace.');
        }

        $procurement->delete();

        return back()->with('success', 'Purchase request deleted successfully.');
    }

    private function seedInitialRequests(int $teamId): void
    {
        $samples = [
            ['Heavy Duty Copper Wire (100m)', 'Global Wire Systems', 50, 'Requested'],
            ['LED Floodlight 200W', 'Lumen Lighting Co.', 200, 'Ordered'],
            ['Magnetic Contactor 220V', 'ElectroTech Industries', 150, 'Delivered'],
            ['Industrial Circuit Breaker', 'ElectroTech Industries', 20, 'Cancelled'],
        ];

        foreach ($samples as $i => [$item, $supplier, $qty, $status]) {
            ProcurementRequest::create([
                'team_id' => $teamId,
                'request_id' => sprintf('PR-%05d', 10045 - $i),
                'item_name' => $item,
                'supplier_name' => $supplier,
                'quantity' => $qty,
                'status' => $status,
                'date_requested' => now()->subDays($i * 4 + 2),
            ]);
        }
    }
}
