<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display the System Reports dashboard with real computed metrics.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $totalProducts = Product::where('team_id', $team->id)->where('is_active', true)->count();
        $lowStockCount = Product::where('team_id', $team->id)->where('is_active', true)->whereColumn('stock', '<=', 'min_stock')->count();
        $inventoryValue = Product::where('team_id', $team->id)->where('is_active', true)->selectRaw('SUM(stock * price) as total_val')->value('total_val') ?? 0;

        $totalQuotations = Quotation::where('team_id', $team->id)->count();
        $approvedQuotations = Quotation::where('team_id', $team->id)->where('status', 'Approved')->count();
        $quotationsValue = Quotation::where('team_id', $team->id)->sum('grand_total');

        $totalSales = Sale::where('team_id', $team->id)->count();
        $totalRevenue = Sale::where('team_id', $team->id)->sum('grand_total');

        $totalSuppliers = Supplier::where('team_id', $team->id)->count();

        return Inertia::render('Reports/Index', [
            'metrics' => [
                'total_products' => $totalProducts,
                'low_stock_count' => $lowStockCount,
                'inventory_value' => (float) $inventoryValue,
                'total_quotations' => $totalQuotations,
                'approved_quotations' => $approvedQuotations,
                'quotations_value' => (float) $quotationsValue,
                'total_sales' => $totalSales,
                'total_revenue' => (float) $totalRevenue,
                'total_suppliers' => $totalSuppliers,
            ],
        ]);
    }

    /**
     * Export Inventory Status Report as CSV.
     */
    public function exportInventory(Request $request): StreamedResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403);

        $filename = 'Inventory_Status_Report_' . date('Y-m-d') . '.csv';
        $products = Product::where('team_id', $team->id)->with('supplier:id,name')->get();

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Product Name', 'Category', 'Current Stock', 'Min Stock', 'Cost Price (PHP)', 'Selling Price (PHP)', 'Total Value (PHP)', 'Supplier', 'Status']);

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->sku,
                    $p->name,
                    $p->category ?? 'General',
                    $p->stock,
                    $p->min_stock,
                    number_format((float) ($p->cost_price ?? $p->price), 2, '.', ''),
                    number_format((float) $p->price, 2, '.', ''),
                    number_format((float) ($p->stock * $p->price), 2, '.', ''),
                    $p->supplier?->name ?? 'N/A',
                    $p->stock <= $p->min_stock ? ($p->stock == 0 ? 'Out of Stock' : 'Low Stock') : 'In Stock',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export Quotations Summary Report as CSV.
     */
    public function exportQuotations(Request $request): StreamedResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403);

        $filename = 'Quotations_Summary_Report_' . date('Y-m-d') . '.csv';
        $quotations = Quotation::where('team_id', $team->id)->orderByDesc('quotation_date')->get();

        return response()->streamDownload(function () use ($quotations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Quotation #', 'Client Company', 'Contact Person', 'Email', 'Phone', 'Date', 'Valid Until', 'Subtotal (PHP)', 'Discount (PHP)', 'Tax (PHP)', 'Grand Total (PHP)', 'Status']);

            foreach ($quotations as $q) {
                fputcsv($handle, [
                    $q->quotation_number,
                    $q->client_name,
                    $q->contact_person ?? 'N/A',
                    $q->email ?? 'N/A',
                    $q->phone ?? 'N/A',
                    $q->quotation_date ? $q->quotation_date->format('Y-m-d') : 'N/A',
                    $q->valid_until ? $q->valid_until->format('Y-m-d') : 'N/A',
                    number_format((float) $q->subtotal, 2, '.', ''),
                    number_format((float) $q->discount, 2, '.', ''),
                    number_format((float) $q->tax_amount, 2, '.', ''),
                    number_format((float) $q->grand_total, 2, '.', ''),
                    $q->status instanceof \BackedEnum ? $q->status->value : (string) $q->status,
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export Sales & Revenue Report as CSV.
     */
    public function exportSales(Request $request): StreamedResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403);

        $filename = 'Sales_Revenue_Report_' . date('Y-m-d') . '.csv';
        $sales = Sale::where('team_id', $team->id)->with('items')->orderByDesc('sale_date')->get();

        return response()->streamDownload(function () use ($sales) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice #', 'Sale Date', 'Customer Name', 'Contact Person', 'Items Sold', 'Subtotal (PHP)', 'Discount (PHP)', 'Grand Total (PHP)', 'Payment Method', 'Status']);

            foreach ($sales as $s) {
                fputcsv($handle, [
                    $s->invoice_number,
                    $s->sale_date ? $s->sale_date->format('Y-m-d H:i') : $s->created_at->format('Y-m-d H:i'),
                    $s->client_name,
                    $s->contact_person ?? 'Walk-in',
                    $s->items->sum('quantity'),
                    number_format((float) $s->subtotal, 2, '.', ''),
                    number_format((float) $s->discount, 2, '.', ''),
                    number_format((float) $s->grand_total, 2, '.', ''),
                    $s->payment_method ?? 'Cash',
                    $s->status ?? 'Completed',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export Suppliers Directory & AHP Report as CSV.
     */
    public function exportSuppliers(Request $request): StreamedResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403);

        $filename = 'Suppliers_Directory_Report_' . date('Y-m-d') . '.csv';
        $suppliers = Supplier::where('team_id', $team->id)->orderBy('name')->get();

        return response()->streamDownload(function () use ($suppliers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Supplier Name', 'Contact Person', 'Phone', 'Email', 'Address', 'Status', 'Price Score', 'Quality Score', 'Delivery Score', 'Service Score', 'AHP Composite Score']);

            foreach ($suppliers as $s) {
                $metrics = $s->metrics ?? [];
                fputcsv($handle, [
                    $s->name,
                    $s->contact_person ?? 'N/A',
                    $s->phone ?? 'N/A',
                    $s->email ?? 'N/A',
                    $s->address ?? 'N/A',
                    $s->status ?? 'Active',
                    $metrics['price'] ?? 7,
                    $metrics['quality'] ?? 7,
                    $metrics['delivery'] ?? 7,
                    $metrics['service'] ?? 7,
                    number_format((float) ($s->ahp_score ?? 0.5), 3, '.', ''),
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export All Master Data (All Operational Tables in one consolidated bundle).
     */
    public function exportMaster(Request $request): StreamedResponse
    {
        $team = $request->user()->currentTeam;
        abort_if(! $team, 403);

        $filename = 'Master_Operations_Report_' . date('Y-m-d') . '.csv';
        $products = Product::where('team_id', $team->id)->get();
        $quotations = Quotation::where('team_id', $team->id)->get();
        $sales = Sale::where('team_id', $team->id)->get();
        $suppliers = Supplier::where('team_id', $team->id)->get();

        return response()->streamDownload(function () use ($products, $quotations, $sales, $suppliers) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['=== MR-PRIDE MASTER OPERATIONS DATA DUMP ===']);
            fputcsv($handle, ['Export Timestamp: ' . date('Y-m-d H:i:s')]);
            fputcsv($handle, []);

            fputcsv($handle, ['[INVENTORY PRODUCTS]']);
            fputcsv($handle, ['SKU', 'Name', 'Category', 'Stock', 'Cost Price', 'Selling Price']);
            foreach ($products as $p) {
                fputcsv($handle, [$p->sku, $p->name, $p->category, $p->stock, $p->cost_price ?? $p->price, $p->price]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['[QUOTATIONS]']);
            fputcsv($handle, ['Quotation #', 'Client', 'Total Amount', 'Status']);
            foreach ($quotations as $q) {
                fputcsv($handle, [$q->quotation_number, $q->client_name, $q->grand_total, $q->status instanceof \BackedEnum ? $q->status->value : (string) $q->status]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['[SALES TRANSACTIONS]']);
            fputcsv($handle, ['Invoice #', 'Customer', 'Grand Total', 'Status']);
            foreach ($sales as $s) {
                fputcsv($handle, [$s->invoice_number, $s->client_name, $s->grand_total, $s->status]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['[SUPPLIERS]']);
            fputcsv($handle, ['Name', 'Contact', 'Phone', 'AHP Score']);
            foreach ($suppliers as $sp) {
                fputcsv($handle, [$sp->name, $sp->contact_person, $sp->phone, $sp->ahp_score]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
