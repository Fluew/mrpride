<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryAttemptLog;
use App\Models\OrderFulfillment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    /**
     * Display deliveries monitoring module and delivery staff dashboard.
     */
    public function index(Request $request): Response
    {
        $team = $this->getTeam($request);
        abort_if(! $team, 403, 'No active team selected.');

        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $isDeliveryStaff = ! $isAdmin && ($user->role === 'staff_deliveries' || in_array('deliveries', $user->assignedModules(), true));

        // Active tab: delivery staff are restricted to outbound customer deliveries.
        $activeTab = $isDeliveryStaff ? 'outbound' : $request->input('tab', 'outbound');

        // ---------------------------------------------------------
        // 1. OUTBOUND CUSTOMER DELIVERIES (Order Fulfillments)
        // ---------------------------------------------------------
        $outboundQuery = OrderFulfillment::where('team_id', $team->id)
            ->with([
                'sale:id,invoice_number,client_name,grand_total,sale_date',
                'sale.items:id,sale_id,product_name,quantity,unit_price',
                'commercialInvoice:id,invoice_number,total_cost',
                'handledBy:id,name,email,role',
                'rescheduledBy:id,name',
                'attemptLogs.staff:id,name',
            ]);

        // RBAC: Delivery staff can view ONLY deliveries assigned to them
        if ($isDeliveryStaff) {
            $outboundQuery->where('handled_by_id', $user->id);
        } elseif ($request->filled('staff_id') && $request->input('staff_id') !== 'All') {
            $outboundQuery->where('handled_by_id', (int) $request->input('staff_id'));
        }

        // Filters
        $search = $request->input('search');
        if ($search) {
            $outboundQuery->where(function ($q) use ($search) {
                $q->where('fulfillment_number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('delivery_address', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhereHas('sale', fn ($sq) => $sq->where('invoice_number', 'like', "%{$search}%")->orWhere('client_name', 'like', "%{$search}%"));
            });
        }

        $status = $request->input('status', 'All');
        if ($status && $status !== 'All') {
            if ($status === 'Pending') {
                $outboundQuery->whereIn('status', ['Pending', 'Preparing']);
            } elseif ($status === 'In-Transit' || $status === 'Out for Delivery') {
                $outboundQuery->where('status', 'Out for Delivery');
            } elseif ($status === 'Failed') {
                $outboundQuery->whereIn('status', ['Delivery Attempt Failed', 'Maximum Attempts Reached']);
            } else {
                $outboundQuery->where('status', $status);
            }
        }

        $dateFrom = $request->input('date_from');
        if ($dateFrom) {
            $outboundQuery->whereDate('delivery_schedule', '>=', $dateFrom);
        }

        $dateTo = $request->input('date_to');
        if ($dateTo) {
            $outboundQuery->whereDate('delivery_schedule', '<=', $dateTo);
        }

        $outboundDeliveries = $outboundQuery
            ->latest('id')
            ->paginate(12, ['*'], 'outbound_page')
            ->withQueryString();

        // Outbound Metrics
        $baseMetricsQuery = fn () => OrderFulfillment::where('team_id', $team->id)
            ->when($isDeliveryStaff, fn ($q) => $q->where('handled_by_id', $user->id));

        $outboundMetrics = [
            'totalAssigned' => $baseMetricsQuery()->count(),
            'pending' => $baseMetricsQuery()->whereIn('status', ['Pending', 'Preparing'])->count(),
            'inTransit' => $baseMetricsQuery()->where('status', 'Out for Delivery')->count(),
            'delivered' => $baseMetricsQuery()->where('status', 'Delivered')->count(),
            'failed' => $baseMetricsQuery()->whereIn('status', ['Delivery Attempt Failed', 'Maximum Attempts Reached'])->count(),
            'attemptsRemaining' => $baseMetricsQuery()->where('attempt_count', '<', OrderFulfillment::MAX_ATTEMPTS)->whereNotIn('status', ['Delivered', 'Cancelled'])->count(),
        ];

        // Available Delivery Staff list (for Admin assignment dropdown)
        $deliveryStaffList = [];
        if ($isAdmin) {
            $deliveryStaffList = User::where('current_team_id', $team->id)
                ->where('status', 'Active')
                ->where(function ($q) {
                    $q->where('role', 'staff_deliveries')
                        ->orWhere('role', 'like', 'staff%')
                        ->orWhereIn('role', ['admin', 'owner']);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']);
        }

        // ---------------------------------------------------------
        // 2. INBOUND SUPPLIER DELIVERIES (Receiving Log)
        // ---------------------------------------------------------
        $inboundDeliveries = null;
        $inboundMetrics = null;
        $products = [];
        $suppliers = [];
        $purchaseOrders = [];

        if (! $isDeliveryStaff) {
            $inboundQuery = Delivery::where('team_id', $team->id)
                ->with([
                    'supplier:id,name',
                    'purchaseOrder:id,po_number',
                    'receivedBy:id,name',
                ]);

            if ($search && $activeTab === 'inbound') {
                $inboundQuery->where(function ($q) use ($search) {
                    $q->where('delivery_number', 'like', "%{$search}%")
                        ->orWhere('purchase_order_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
                });
            }

            if ($dateFrom && $activeTab === 'inbound') {
                $inboundQuery->whereDate('delivery_date', '>=', $dateFrom);
            }
            if ($dateTo && $activeTab === 'inbound') {
                $inboundQuery->whereDate('delivery_date', '<=', $dateTo);
            }

            $inboundDeliveries = $inboundQuery->latest('delivery_date')->latest('id')->paginate(15, ['*'], 'inbound_page')->withQueryString();

            $baseInboundQuery = fn () => Delivery::where('team_id', $team->id);
            $inboundMetrics = [
                'totalDeliveries' => $baseInboundQuery()->count(),
                'totalQuantityDelivered' => (int) $baseInboundQuery()->sum('quantity'),
                'linkedPosCount' => $baseInboundQuery()->whereNotNull('purchase_order_number')->where('purchase_order_number', '!=', '')->distinct('purchase_order_number')->count('purchase_order_number'),
                'deliveredCount' => $baseInboundQuery()->where('status', 'Delivered')->count(),
            ];

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
        }

        return Inertia::render('Deliveries/Index', [
            'activeTab' => $activeTab,
            'isDeliveryStaff' => $isDeliveryStaff,
            'isAdmin' => $isAdmin,
            'outboundDeliveries' => $outboundDeliveries,
            'outboundMetrics' => $outboundMetrics,
            'deliveryStaffList' => $deliveryStaffList,
            'inboundDeliveries' => $inboundDeliveries,
            'inboundMetrics' => $inboundMetrics,
            'products' => $products,
            'suppliers' => $suppliers,
            'purchaseOrders' => $purchaseOrders,
            'filters' => [
                'tab' => $activeTab,
                'search' => $search,
                'status' => $status,
                'staff_id' => $request->input('staff_id', 'All'),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Start delivery attempt: Transition from Pending to Out for Delivery.
     */
    public function startDelivery(Request $request, mixed $current_team = null, mixed $fulfillment = null): RedirectResponse
    {
        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized delivery access.');

        $user = $request->user();
        if (! $user->isAdmin()) {
            abort_if($fulfillment->handled_by_id !== $user->id, 403, 'You are not assigned to this delivery.');
        }

        // Validate current status
        if (! in_array($fulfillment->status, ['Pending', 'Preparing', 'Delivery Attempt Failed'], true)) {
            return back()->with('error', "Delivery cannot be dispatched from status '{$fulfillment->status}'.");
        }

        // Validate attempts
        if ($fulfillment->attempt_count >= OrderFulfillment::MAX_ATTEMPTS) {
            return back()->with('error', 'Maximum delivery attempts reached (3/3). Administrator must reschedule.');
        }

        DB::transaction(function () use ($fulfillment, $user, $team) {
            $prevStatus = $fulfillment->status;
            $fulfillment->status = 'Out for Delivery';
            if (! $fulfillment->dispatched_at) {
                $fulfillment->dispatched_at = now();
            }
            $fulfillment->save();

            DeliveryAttemptLog::create([
                'team_id' => $team->id,
                'order_fulfillment_id' => $fulfillment->id,
                'attempt_number' => $fulfillment->attempt_count,
                'previous_status' => $prevStatus,
                'new_status' => 'Out for Delivery',
                'staff_id' => $user->id,
                'remarks' => "Driver started delivery dispatch for {$fulfillment->recipient_name}.",
                'attempt_date' => now(),
            ]);
        });

        return back()->with('success', "Delivery {$fulfillment->fulfillment_number} is now Out for Delivery.");
    }

    /**
     * Record an unsuccessful delivery attempt (Strict 3-attempt limit).
     */
    public function recordAttempt(Request $request, mixed $current_team = null, mixed $fulfillment = null): RedirectResponse
    {
        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized delivery access.');

        $user = $request->user();
        if (! $user->isAdmin()) {
            abort_if($fulfillment->handled_by_id !== $user->id, 403, 'You are not assigned to this delivery.');
        }

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                Rule::in([
                    'Customer unavailable',
                    'Incorrect or incomplete address',
                    'Customer refused delivery',
                    'Customer unreachable',
                    'Other reason',
                ]),
            ],
            'remarks' => ['required', 'string', 'min:3', 'max:1000'],
            'attempt_photo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        return DB::transaction(function () use ($fulfillment, $user, $team, $validated, $request) {
            // Lock record for update to prevent concurrent race condition attempts
            $lockedFulfillment = OrderFulfillment::where('id', $fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFulfillment->status === 'Delivered') {
                return back()->with('error', 'Cannot record attempt. Order is already successfully delivered.');
            }

            if ($lockedFulfillment->attempt_count >= OrderFulfillment::MAX_ATTEMPTS) {
                return back()->with('error', 'Strict limit reached: 3 of 3 attempts used. Delivery cannot be attempted again without administrator approval.');
            }

            $prevStatus = $lockedFulfillment->status;
            $newAttemptCount = $lockedFulfillment->attempt_count + 1;
            $lockedFulfillment->attempt_count = $newAttemptCount;

            // Handle optional photo evidence of failed attempt (e.g. closed gate/wrong address)
            $photoPath = null;
            if ($request->hasFile('attempt_photo')) {
                $photoPath = $request->file('attempt_photo')->store('proofs/deliveries/attempts', 'local');
            }

            // After 3rd failed attempt, set to final state
            if ($newAttemptCount >= OrderFulfillment::MAX_ATTEMPTS) {
                $lockedFulfillment->status = 'Maximum Attempts Reached';
            } else {
                $lockedFulfillment->status = 'Delivery Attempt Failed';
            }

            $lockedFulfillment->notes = trim(($lockedFulfillment->notes ?? '')."\nAttempt {$newAttemptCount} failed: {$validated['reason']}. ".$validated['remarks']);
            $lockedFulfillment->save();

            // Create chronological audit log
            DeliveryAttemptLog::create([
                'team_id' => $team->id,
                'order_fulfillment_id' => $lockedFulfillment->id,
                'attempt_number' => $newAttemptCount,
                'previous_status' => $prevStatus,
                'new_status' => $lockedFulfillment->status,
                'staff_id' => $user->id,
                'failure_reason' => $validated['reason'],
                'remarks' => $validated['remarks'],
                'proof_image' => $photoPath,
                'attempt_date' => now(),
            ]);

            if ($newAttemptCount >= OrderFulfillment::MAX_ATTEMPTS) {
                return back()->with('warning', "Attempt {$newAttemptCount}/3 recorded. Maximum attempts reached. Delivery set to Maximum Attempts Reached and requires administrator follow-up.");
            }

            return back()->with('success', "Attempt {$newAttemptCount}/3 recorded successfully. Remaining attempts: ".(OrderFulfillment::MAX_ATTEMPTS - $newAttemptCount));
        });
    }

    /**
     * Mark delivery as Delivered (Requires valid proof of delivery photo).
     */
    public function markDelivered(Request $request, mixed $current_team = null, mixed $fulfillment = null): RedirectResponse
    {
        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized delivery access.');

        $user = $request->user();
        if (! $user->isAdmin()) {
            abort_if($fulfillment->handled_by_id !== $user->id, 403, 'You are not assigned to this delivery.');
        }

        $validated = $request->validate([
            'proof_of_delivery' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'proof_of_delivery.required' => 'Proof of delivery photo is required to mark this order as Delivered.',
            'proof_of_delivery.image' => 'The proof of delivery must be a valid image file (JPG or PNG).',
            'proof_of_delivery.mimes' => 'Only JPEG, JPG, and PNG image formats are accepted.',
            'proof_of_delivery.max' => 'The proof of delivery image size cannot exceed 5 MB.',
        ]);

        return DB::transaction(function () use ($fulfillment, $user, $team, $validated, $request) {
            $lockedFulfillment = OrderFulfillment::where('id', $fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFulfillment->status === 'Delivered') {
                return back()->with('error', 'Delivery is already marked as Delivered.');
            }

            // Secure private storage
            $proofPath = $request->file('proof_of_delivery')->store('proofs/deliveries', 'local');

            $prevStatus = $lockedFulfillment->status;
            $lockedFulfillment->status = 'Delivered';
            $lockedFulfillment->proof_of_delivery = $proofPath;
            if (! $lockedFulfillment->dispatched_at) {
                $lockedFulfillment->dispatched_at = now();
            }
            $lockedFulfillment->delivered_at = now();

            if (! empty($validated['recipient_name'])) {
                $lockedFulfillment->recipient_name = $validated['recipient_name'];
            }

            if (! empty($validated['remarks'])) {
                $lockedFulfillment->notes = trim(($lockedFulfillment->notes ?? '')."\nDelivered: ".$validated['remarks']);
            }

            $lockedFulfillment->save();

            // Record completion in history
            DeliveryAttemptLog::create([
                'team_id' => $team->id,
                'order_fulfillment_id' => $lockedFulfillment->id,
                'attempt_number' => min(OrderFulfillment::MAX_ATTEMPTS, $lockedFulfillment->attempt_count + 1),
                'previous_status' => $prevStatus,
                'new_status' => 'Delivered',
                'staff_id' => $user->id,
                'failure_reason' => null,
                'remarks' => $validated['remarks'] ?: 'Order successfully received and verified by customer.',
                'proof_image' => $proofPath,
                'attempt_date' => now(),
            ]);

            return back()->with('success', "Delivery {$lockedFulfillment->fulfillment_number} marked as Delivered successfully with photo proof.");
        });
    }

    /**
     * Mark delivery as Return to Warehouse.
     */
    public function returnToWarehouse(Request $request, mixed $current_team = null, mixed $fulfillment = null): RedirectResponse
    {
        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized delivery access.');

        $user = $request->user();
        if (! $user->isAdmin()) {
            abort_if($fulfillment->handled_by_id !== $user->id, 403, 'You are not assigned to this delivery.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        DB::transaction(function () use ($fulfillment, $user, $team, $validated) {
            $prevStatus = $fulfillment->status;
            $fulfillment->status = 'Return to Warehouse';
            $fulfillment->notes = trim(($fulfillment->notes ?? '')."\nReturn to Warehouse: ".$validated['remarks']);
            $fulfillment->save();

            DeliveryAttemptLog::create([
                'team_id' => $team->id,
                'order_fulfillment_id' => $fulfillment->id,
                'attempt_number' => $fulfillment->attempt_count,
                'previous_status' => $prevStatus,
                'new_status' => 'Return to Warehouse',
                'staff_id' => $user->id,
                'remarks' => $validated['remarks'],
                'attempt_date' => now(),
            ]);
        });

        return back()->with('success', "Delivery {$fulfillment->fulfillment_number} has been marked for Return to Warehouse.");
    }

    /**
     * Assign or reassign delivery staff (Administrator / Operations Staff only).
     */
    public function assignStaff(Request $request, mixed $current_team = null, mixed $fulfillment = null): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, 'Only administrators or authorized operations staff can assign deliveries.');

        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized delivery access.');

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $assignedUser = User::findOrFail($validated['staff_id']);
        $fulfillment->handled_by_id = $assignedUser->id;
        $fulfillment->save();

        DeliveryAttemptLog::create([
            'team_id' => $team->id,
            'order_fulfillment_id' => $fulfillment->id,
            'attempt_number' => $fulfillment->attempt_count,
            'previous_status' => $fulfillment->status,
            'new_status' => $fulfillment->status,
            'staff_id' => $user->id,
            'remarks' => "Administrator assigned delivery to {$assignedUser->name}.",
            'attempt_date' => now(),
        ]);

        return back()->with('success', "Delivery {$fulfillment->fulfillment_number} assigned to {$assignedUser->name}.");
    }

    /**
     * Reschedule delivery / reset attempts after 3-attempt limit (Admin/Ops only).
     */
    public function reschedule(Request $request, mixed $current_team = null, mixed $fulfillment = null): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, 'Only administrators can authorize rescheduling.');

        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized delivery access.');

        $validated = $request->validate([
            'delivery_schedule' => ['required', 'date'],
            'staff_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reset_attempts' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($fulfillment, $user, $team, $validated) {
            $prevStatus = $fulfillment->status;
            $fulfillment->delivery_schedule = $validated['delivery_schedule'];
            $fulfillment->rescheduled_at = now();
            $fulfillment->rescheduled_by_id = $user->id;
            $fulfillment->status = 'Pending';

            if (! empty($validated['staff_id'])) {
                $fulfillment->handled_by_id = $validated['staff_id'];
            }

            // Admin decision: Reset attempt counter or grant fresh attempts
            if (! empty($validated['reset_attempts'])) {
                $fulfillment->attempt_count = 0;
            }

            if (! empty($validated['notes'])) {
                $fulfillment->notes = trim(($fulfillment->notes ?? '')."\nRescheduled: ".$validated['notes']);
            }

            $fulfillment->save();

            DeliveryAttemptLog::create([
                'team_id' => $team->id,
                'order_fulfillment_id' => $fulfillment->id,
                'attempt_number' => $fulfillment->attempt_count,
                'previous_status' => $prevStatus,
                'new_status' => 'Pending',
                'staff_id' => $user->id,
                'remarks' => 'Administrator authorized rescheduling to '.Carbon::parse($validated['delivery_schedule'])->format('M d, Y').'. '.($validated['notes'] ?? ''),
                'attempt_date' => now(),
            ]);
        });

        return back()->with('success', "Delivery {$fulfillment->fulfillment_number} rescheduled successfully.");
    }

    /**
     * Stream fulfillment proof of delivery image securely.
     */
    public function fulfillmentProof(Request $request, mixed $current_team = null, mixed $fulfillment = null): StreamedResponse
    {
        $fulfillment = $this->resolveFulfillment($fulfillment, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $fulfillment->team_id !== $team->id, 403, 'Unauthorized delivery access.');

        $user = $request->user();
        if (! $user->isAdmin()) {
            abort_if($fulfillment->handled_by_id !== $user->id, 403, 'You are not authorized to view this delivery proof.');
        }

        abort_if(! $fulfillment->proof_of_delivery, 404, 'No proof of delivery uploaded.');

        if (! Storage::disk('local')->exists($fulfillment->proof_of_delivery)) {
            abort(404, 'Proof of delivery file missing.');
        }

        return Storage::disk('local')->response($fulfillment->proof_of_delivery, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Stream attempt photo proof securely.
     */
    public function attemptProof(Request $request, mixed $current_team = null, mixed $attemptLog = null): StreamedResponse
    {
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team, 403);

        $logId = is_numeric($attemptLog) ? (int) $attemptLog : ($attemptLog?->id ?? (int) $request->route('attemptLog'));
        $log = DeliveryAttemptLog::where('team_id', $team->id)->where('id', $logId)->firstOrFail();

        $user = $request->user();
        if (! $user->isAdmin()) {
            abort_if($log->staff_id !== $user->id && $log->orderFulfillment?->handled_by_id !== $user->id, 403);
        }

        abort_if(! $log->proof_image || ! Storage::disk('local')->exists($log->proof_image), 404, 'Attempt photo missing.');

        return Storage::disk('local')->response($log->proof_image, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // -------------------------------------------------------------
    // INBOUND SUPPLIER DELIVERY METHODS (Preserved for compatibility)
    // -------------------------------------------------------------

    /**
     * Store a new inbound supplier delivery record.
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
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'] ?? 'pcs',
            'description' => $validated['description'],
            'supplier_id' => $supplierId,
            'received_by_id' => $request->user()?->id,
            'status' => $validated['status'] ?? 'Delivered',
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', "Delivery {$deliveryNumber} recorded successfully.");
    }

    /**
     * Delete an inbound delivery record.
     */
    public function destroy(Request $request, mixed $current_team = null, mixed $delivery = null): RedirectResponse
    {
        $delivery = $this->resolveDelivery($delivery, $current_team, $request);
        $team = $this->getTeam($request, $current_team);
        abort_if(! $team || $delivery->team_id !== $team->id, 403);

        $delivery->delete();

        return back()->with('success', 'Delivery record deleted successfully.');
    }

    /**
     * Process CSV Import for Deliveries.
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

            $rawDate = isset($row[$dateIdx]) ? trim($row[$dateIdx]) : '';
            $parsedDate = null;
            $displayDate = $rawDate;

            if ($rawDate === '') {
                $errors[] = 'Date is required.';
            } else {
                if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $rawDate, $m)) {
                    $parsedDate = sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
                } elseif (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $rawDate, $m)) {
                    $parsedDate = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                } else {
                    try {
                        $parsedDate = Carbon::parse($rawDate)->toDateString();
                    } catch (\Exception $e) {
                        $errors[] = "Invalid date format '{$rawDate}'.";
                    }
                }
            }

            $delNumber = $delNumIdx !== -1 && isset($row[$delNumIdx]) ? trim($row[$delNumIdx]) : '';
            $poNumber = $poNumIdx !== -1 && isset($row[$poNumIdx]) ? trim($row[$poNumIdx]) : '';

            $rawQty = isset($row[$qtyIdx]) ? trim(str_replace(',', '', $row[$qtyIdx])) : '';
            $qty = 0;
            if ($rawQty === '' || ! is_numeric($rawQty) || (int) $rawQty <= 0) {
                $errors[] = 'Quantity must be a positive integer.';
            } else {
                $qty = (int) $rawQty;
            }

            $unit = $unitIdx !== -1 && isset($row[$unitIdx]) && trim($row[$unitIdx]) !== '' ? trim($row[$unitIdx]) : 'pcs';
            $desc = isset($row[$descIdx]) ? trim($row[$descIdx]) : '';
            if ($desc === '') {
                $errors[] = 'Description is required.';
            }

            if (! empty($errors)) {
                $invalidRows[] = [
                    'row' => $rowNumber,
                    'errors' => $errors,
                    'data' => [
                        'date' => $displayDate,
                        'delivery_number' => $delNumber,
                        'po_number' => $poNumber,
                        'quantity' => $rawQty,
                        'unit' => $unit,
                        'description' => $desc,
                    ],
                ];
            } else {
                $validRows[] = [
                    'row' => $rowNumber,
                    'delivery_date' => $parsedDate,
                    'delivery_number' => $delNumber,
                    'purchase_order_number' => $poNumber,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'description' => $desc,
                ];
            }
        }

        if ($request->boolean('execute')) {
            if (empty($validRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid rows found to import.',
                ], 422);
            }

            DB::transaction(function () use ($validRows, $team, $request) {
                foreach ($validRows as $item) {
                    $delNum = ! empty($item['delivery_number'])
                        ? $item['delivery_number']
                        : Delivery::generateDeliveryNumber($team->id, $item['delivery_date']);

                    $poId = null;
                    $supplierId = null;
                    if (! empty($item['purchase_order_number'])) {
                        $foundPo = PurchaseOrder::where('team_id', $team->id)
                            ->where('po_number', $item['purchase_order_number'])
                            ->first();
                        if ($foundPo) {
                            $poId = $foundPo->id;
                            $supplierId = $foundPo->supplier_id;
                        }
                    }

                    Delivery::create([
                        'team_id' => $team->id,
                        'delivery_date' => $item['delivery_date'],
                        'delivery_number' => $delNum,
                        'purchase_order_number' => $item['purchase_order_number'] ?: null,
                        'purchase_order_id' => $poId,
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'description' => $item['description'],
                        'supplier_id' => $supplierId,
                        'received_by_id' => $request->user()?->id,
                        'status' => 'Delivered',
                        'notes' => 'Imported via CSV',
                    ]);
                }
            });

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully imported '.count($validRows).' delivery record(s).',
                    'count' => count($validRows),
                    'invalid_count' => count($invalidRows),
                ]);
            }

            return back()->with('success', 'Successfully imported '.count($validRows).' delivery record(s).');
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
            if (is_string($cand) && ! empty($cand)) {
                $found = OrderFulfillment::where('fulfillment_number', $cand)->first();
                if ($found) {
                    return $found;
                }
            }
        }

        abort(404, 'Order fulfillment record not found.');
    }
}
