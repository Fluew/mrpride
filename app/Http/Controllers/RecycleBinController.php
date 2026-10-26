<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CommercialInvoice;
use App\Models\Delivery;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Team;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RecycleBinController extends Controller
{
    /**
     * Map of supported archivable types to their Eloquent model classes and labels.
     *
     * @var array<string, array{class: class-string, label: string, color: string}>
     */
    protected const TYPE_REGISTRY = [
        'product' => [
            'class' => Product::class,
            'label' => 'Product',
            'badge' => 'Emerald',
        ],
        'supplier' => [
            'class' => Supplier::class,
            'label' => 'Supplier',
            'badge' => 'Amber',
        ],
        'client' => [
            'class' => Client::class,
            'label' => 'Client / Customer',
            'badge' => 'Blue',
        ],
        'quotation' => [
            'class' => Quotation::class,
            'label' => 'Quotation',
            'badge' => 'Indigo',
        ],
        'purchase_order' => [
            'class' => PurchaseOrder::class,
            'label' => 'Purchase Order',
            'badge' => 'Cyan',
        ],
        'purchase_request' => [
            'class' => PurchaseRequest::class,
            'label' => 'Purchase Request',
            'badge' => 'Sky',
        ],
        'commercial_invoice' => [
            'class' => CommercialInvoice::class,
            'label' => 'Commercial Invoice',
            'badge' => 'Violet',
        ],
        'delivery' => [
            'class' => Delivery::class,
            'label' => 'Delivery',
            'badge' => 'Purple',
        ],
        'inquiry' => [
            'class' => Inquiry::class,
            'label' => 'Message / Inquiry',
            'badge' => 'Teal',
        ],
        'sale' => [
            'class' => Sale::class,
            'label' => 'Sale Transaction',
            'badge' => 'Rose',
        ],
    ];

    /**
     * Display a listing of recoverable deleted records.
     */
    public function index(Request $request, mixed $current_team = null): Response
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Unauthorized access. Only administrators can access the Recycle Bin.');

        $team = $this->resolveTeam($current_team, $request);

        $selectedType = $request->input('type', 'all');
        $search = trim((string) $request->input('search', ''));
        $page = (int) $request->input('page', 1);
        $perPage = 15;

        // Collect all trashed records for this team
        $allRecords = collect();
        $counts = [
            'all' => 0,
            'product' => 0,
            'supplier' => 0,
            'client' => 0,
            'quotation' => 0,
            'purchase_order' => 0,
            'purchase_request' => 0,
            'commercial_invoice' => 0,
            'delivery' => 0,
            'inquiry' => 0,
            'sale' => 0,
        ];

        foreach (self::TYPE_REGISTRY as $typeKey => $config) {
            $modelClass = $config['class'];
            $items = $modelClass::onlyTrashed()
                ->where('team_id', $team->id)
                ->with(['deletedBy'])
                ->get();

            $counts[$typeKey] = $items->count();
            $counts['all'] += $items->count();

            // Only map items if viewing 'all' or this specific type
            if ($selectedType === 'all' || $selectedType === $typeKey) {
                foreach ($items as $item) {
                    $allRecords->push($this->formatTrashedRecord($item, $typeKey, $config));
                }
            }
        }

        // Apply search filter if provided
        if ($search !== '') {
            $searchLower = strtolower($search);
            $allRecords = $allRecords->filter(function ($rec) use ($searchLower) {
                return str_contains(strtolower($rec['title']), $searchLower)
                    || str_contains(strtolower($rec['identifier'] ?? ''), $searchLower)
                    || str_contains(strtolower($rec['summary'] ?? ''), $searchLower)
                    || str_contains(strtolower($rec['deleted_by']['name'] ?? ''), $searchLower)
                    || str_contains(strtolower($rec['deleted_by']['email'] ?? ''), $searchLower);
            });
        }

        // Sort newest deleted first
        $sortedRecords = $allRecords->sortByDesc(fn ($rec) => $rec['deleted_at_raw'])->values();

        // Manual pagination
        $total = $sortedRecords->count();
        $sliced = $sortedRecords->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Stats summary
        $stats = [
            'total' => $counts['all'],
            'deleted_today' => $allRecords->filter(fn ($r) => substr((string) ($r['deleted_at_raw'] ?? ''), 0, 10) === now()->toDateString())->count(),
            'types_with_records' => collect($counts)->filter(fn ($val, $k) => $k !== 'all' && $val > 0)->count(),
        ];

        return Inertia::render('RecycleBin/Index', [
            'records' => $paginator,
            'counts' => $counts,
            'stats' => $stats,
            'filters' => [
                'type' => $selectedType,
                'search' => $search,
            ],
            'availableTypes' => collect(self::TYPE_REGISTRY)->map(fn ($cfg, $k) => [
                'key' => $k,
                'label' => $cfg['label'],
                'badge' => $cfg['badge'],
            ])->values(),
        ]);
    }

    /**
     * Restore an archived record to active use.
     */
    public function restore(Request $request, mixed $current_team = null, string $type = '', int|string $id = 0): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Unauthorized access.');

        $team = $this->resolveTeam($current_team, $request);
        $model = $this->findTrashedRecord($team, $type, $id);

        $title = $this->getRecordTitle($model, $type);
        $typeLabel = self::TYPE_REGISTRY[$type]['label'] ?? ucfirst($type);

        // Restore record
        $model->restore();

        // If product, re-enable active flag
        if ($model instanceof Product) {
            $model->is_active = true;
            $model->saveQuietly();
        }

        AuditLogger::audit(
            'recycle_bin.restore',
            "Restored {$typeLabel} '{$title}' (#{$id}) to active use.",
            $user,
            null,
            ['type' => $type, 'id' => $id, 'title' => $title],
            'success',
            $request
        );

        return back()->with('success', "{$typeLabel} '{$title}' has been returned to active use.");
    }

    /**
     * Permanently delete a record from the database (restricted action).
     */
    public function forceDelete(Request $request, mixed $current_team = null, string $type = '', int|string $id = 0): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Unauthorized access.');

        $team = $this->resolveTeam($current_team, $request);
        $model = $this->findTrashedRecord($team, $type, $id);

        $title = $this->getRecordTitle($model, $type);
        $typeLabel = self::TYPE_REGISTRY[$type]['label'] ?? ucfirst($type);

        // Permanently erase record
        $model->forceDelete();

        AuditLogger::audit(
            'recycle_bin.permanent_delete',
            "Permanently deleted {$typeLabel} '{$title}' (#{$id}) from the system database.",
            $user,
            null,
            ['type' => $type, 'id' => $id, 'title' => $title],
            'success',
            $request
        );

        return back()->with('success', "{$typeLabel} '{$title}' has been permanently deleted.");
    }

    /**
     * Bulk restore multiple archived records.
     */
    public function bulkRestore(Request $request, mixed $current_team = null): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Unauthorized access.');

        $team = $this->resolveTeam($current_team, $request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string'],
            'items.*.id' => ['required'],
        ]);

        $restoredCount = 0;

        foreach ($validated['items'] as $itemData) {
            try {
                $type = $itemData['type'];
                $id = $itemData['id'];
                $model = $this->findTrashedRecord($team, $type, $id);
                $model->restore();

                if ($model instanceof Product) {
                    $model->is_active = true;
                    $model->saveQuietly();
                }

                $restoredCount++;
            } catch (\Throwable) {
                continue;
            }
        }

        AuditLogger::audit(
            'recycle_bin.bulk_restore',
            "Bulk restored {$restoredCount} archived records back to active use.",
            $user,
            null,
            ['restored_count' => $restoredCount],
            'success',
            $request
        );

        return back()->with('success', "Successfully restored {$restoredCount} records to active use.");
    }

    /**
     * Bulk permanently delete multiple archived records.
     */
    public function bulkForceDelete(Request $request, mixed $current_team = null): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Unauthorized access.');

        $team = $this->resolveTeam($current_team, $request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string'],
            'items.*.id' => ['required'],
        ]);

        $deletedCount = 0;

        foreach ($validated['items'] as $itemData) {
            try {
                $type = $itemData['type'];
                $id = $itemData['id'];
                $model = $this->findTrashedRecord($team, $type, $id);
                $model->forceDelete();
                $deletedCount++;
            } catch (\Throwable) {
                continue;
            }
        }

        AuditLogger::audit(
            'recycle_bin.bulk_permanent_delete',
            "Bulk permanently purged {$deletedCount} records from the system.",
            $user,
            null,
            ['deleted_count' => $deletedCount],
            'success',
            $request
        );

        return back()->with('success', "Successfully purged {$deletedCount} records permanently.");
    }

    /**
     * Empty the entire Recycle Bin for this team.
     */
    public function emptyBin(Request $request, mixed $current_team = null): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403, 'Unauthorized access.');

        $team = $this->resolveTeam($current_team, $request);
        $totalPurged = 0;

        foreach (self::TYPE_REGISTRY as $config) {
            $modelClass = $config['class'];
            $trashed = $modelClass::onlyTrashed()->where('team_id', $team->id)->get();
            foreach ($trashed as $item) {
                $item->forceDelete();
                $totalPurged++;
            }
        }

        AuditLogger::audit(
            'recycle_bin.empty',
            "Emptied entire recycle bin. Total records permanently purged: {$totalPurged}.",
            $user,
            null,
            ['total_purged' => $totalPurged],
            'success',
            $request
        );

        return back()->with('success', "Recycle Bin emptied successfully ({$totalPurged} records permanently removed).");
    }

    /**
     * Find a trashed model record by type and ID.
     */
    protected function findTrashedRecord(Team $team, string $type, int|string $id): mixed
    {
        if (! isset(self::TYPE_REGISTRY[$type])) {
            abort(404, "Invalid record type: {$type}");
        }

        $modelClass = self::TYPE_REGISTRY[$type]['class'];

        return $modelClass::onlyTrashed()
            ->where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Get a descriptive title for a model record.
     */
    protected function getRecordTitle(mixed $model, string $type): string
    {
        return match ($type) {
            'product' => (string) ($model->name ?? "Product #{$model->id}"),
            'supplier' => (string) ($model->name ?? "Supplier #{$model->id}"),
            'client' => (string) ($model->company_name ?? "Client #{$model->id}"),
            'quotation' => (string) ($model->quotation_number ?? "Quotation #{$model->id}"),
            'purchase_order' => (string) ($model->po_number ?? "PO #{$model->id}"),
            'purchase_request' => (string) ($model->request_number ?? "PR #{$model->id}"),
            'commercial_invoice' => (string) ($model->invoice_number ?? "Invoice #{$model->id}"),
            'delivery' => (string) ($model->delivery_number ?? "Delivery #{$model->id}"),
            'inquiry' => (string) ($model->subject ?? ($model->name ? "Inquiry from {$model->name}" : "Inquiry #{$model->id}")),
            'sale' => (string) ($model->invoice_number ?? "Sale #{$model->id}"),
            default => "#{$model->id}",
        };
    }

    /**
     * Format a trashed Eloquent model into a standardized array for the frontend.
     *
     * @return array<string, mixed>
     */
    protected function formatTrashedRecord(mixed $item, string $typeKey, array $config): array
    {
        $deletedBy = $item->deletedBy;
        $title = $this->getRecordTitle($item, $typeKey);

        $identifier = match ($typeKey) {
            'product' => "SKU: {$item->sku}",
            'supplier' => $item->contact_person ? "Contact: {$item->contact_person}" : ($item->phone ?: 'No contact specified'),
            'client' => $item->contact_person ? "Contact: {$item->contact_person}" : ($item->email ?: 'Client record'),
            'quotation' => $item->client_name ? "Client: {$item->client_name}" : 'Quotation document',
            'purchase_order' => $item->item_sku ? "Item: {$item->item_sku}" : 'Purchase order',
            'purchase_request' => $item->status ? "Status: {$item->status}" : 'Purchase request',
            'commercial_invoice' => "Date: " . ($item->invoice_date?->format('M d, Y') ?? 'N/A'),
            'delivery' => "PO: " . ($item->purchase_order_number ?: 'N/A'),
            'inquiry' => "From: {$item->name} ({$item->email})",
            'sale' => "Client: " . ($item->client_name ?: 'Walk-in / Client'),
            default => "ID: {$item->id}",
        };

        $summary = match ($typeKey) {
            'product' => sprintf('₱%s • %d %s', number_format((float) $item->price, 2), (int) $item->stock, $item->unit ?: 'pcs'),
            'supplier' => $item->email ?: ($item->phone ?: 'Active supplier profile'),
            'client' => $item->phone ?: ($item->email ?: 'Client profile'),
            'quotation' => sprintf('₱%s • %s', number_format((float) $item->grand_total, 2), $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status),
            'purchase_order' => sprintf('₱%s • %d %s', number_format((float) $item->total_cost, 2), (int) $item->quantity, $item->unit_of_measure ?: 'units'),
            'purchase_request' => sprintf('Qty: %d • ₱%s', (int) $item->quantity, number_format((float) $item->estimated_cost, 2)),
            'commercial_invoice' => sprintf('Total: ₱%s • Qty: %d', number_format((float) $item->total_cost, 2), (int) $item->quantity),
            'delivery' => sprintf('Qty: %d %s • %s', (int) $item->quantity, $item->unit ?: 'units', $item->delivery_date?->format('M d, Y') ?? 'N/A'),
            'inquiry' => $item->message ? \Illuminate\Support\Str::limit($item->message, 80) : 'Customer contact inquiry',
            'sale' => sprintf('₱%s • %s', number_format((float) $item->grand_total, 2), $item->payment_method ?: 'Paid'),
            default => '',
        };

        // Extract key attribute pairs for the "View Details" modal
        $rawAttributes = $item->getAttributes();
        $details = [];

        foreach ($rawAttributes as $k => $v) {
            if (in_array($k, ['deleted_at', 'deleted_by_id', 'team_id', 'password', 'remember_token'])) {
                continue;
            }
            $cleanKey = ucwords(str_replace('_', ' ', $k));
            $formattedVal = is_null($v) ? '—' : (is_bool($v) ? ($v ? 'Yes' : 'No') : (is_array($v) ? json_encode($v) : (string) $v));
            $details[] = [
                'label' => $cleanKey,
                'value' => $formattedVal,
            ];
        }

        return [
            'id' => $item->id,
            'type' => $typeKey,
            'type_label' => $config['label'],
            'badge' => $config['badge'],
            'title' => $title,
            'identifier' => $identifier,
            'summary' => $summary,
            'deleted_at_raw' => $item->deleted_at?->toIso8601String(),
            'deleted_at' => $item->deleted_at?->format('M d, Y h:i A') ?? 'N/A',
            'deleted_at_human' => $item->deleted_at?->diffForHumans() ?? 'Recently',
            'created_at' => $item->created_at?->format('M d, Y') ?? 'N/A',
            'deleted_by' => $deletedBy ? [
                'id' => $deletedBy->id,
                'name' => $deletedBy->name,
                'email' => $deletedBy->email,
                'role' => method_exists($deletedBy, 'roleLabel') ? $deletedBy->roleLabel() : ucfirst($deletedBy->role ?? 'User'),
            ] : null,
            'details' => $details,
        ];
    }

    /**
     * Resolve the active team model context.
     */
    protected function resolveTeam(mixed $current_team, Request $request): Team
    {
        $team = $request->user()?->currentTeam;
        if ($team) {
            return $team;
        }

        if (is_string($current_team) && $current_team !== '') {
            return Team::where('slug', $current_team)->firstOrFail();
        }

        abort(403, 'No active team context available.');
    }
}
