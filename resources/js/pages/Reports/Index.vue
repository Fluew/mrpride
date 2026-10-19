<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { 
    Package, 
    AlertTriangle, 
    FileText, 
    LineChart, 
    Download, 
    Printer, 
    TrendingUp, 
    Truck, 
    DollarSign,
    CheckCircle2
} from '@lucide/vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';

interface ReportMetrics {
    total_products?: number;
    low_stock_count?: number;
    inventory_value?: number;
    total_quotations?: number;
    approved_quotations?: number;
    quotations_value?: number;
    total_sales?: number;
    total_revenue?: number;
    total_suppliers?: number;
}

const props = defineProps<{
    metrics?: ReportMetrics;
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || '');

const formatCurrency = (val: number | undefined) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(val || 0);
};

// Real Working CSV Export Trigger
const downloadCsv = (endpoint: string) => {
    const url = `/${teamSlug.value}/reports/export/${endpoint}`;
    window.location.href = url;
};

// Real Working Printable View
const printReport = (reportType: string) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const dateStr = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    const teamName = (page.props as any).currentTeam?.name || 'MR-Pride Electrical Supplies';

    const content = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>${reportType} - ${teamName}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px; color: #111; }
                .header { border-bottom: 2px solid #4b5320; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; }
                h1 { margin: 0; color: #4b5320; font-size: 24px; }
                .meta { color: #666; font-size: 13px; margin-top: 5px; }
                .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
                .kpi-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; background: #fafafa; }
                .kpi-label { font-size: 11px; text-transform: uppercase; color: #666; letter-spacing: 0.05em; font-weight: 600; }
                .kpi-value { font-size: 20px; font-weight: 700; color: #111; margin-top: 5px; }
                .notice { background: #fdf8e8; border-left: 4px solid #F8B803; padding: 12px 15px; font-size: 13px; color: #78350f; border-radius: 4px; margin-bottom: 25px; }
                .footer { margin-top: 50px; border-top: 1px solid #e5e7eb; padding-top: 15px; font-size: 12px; color: #888; display: flex; justify-content: space-between; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            <div class="header">
                <div>
                    <h1>MR-PRIDE OPERATIONS REPORT</h1>
                    <div class="meta">${reportType} • Generated for ${teamName}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: bold; font-size: 14px;">CONFIDENTIAL</div>
                    <div class="meta">${dateStr}</div>
                </div>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Active Inventory Items</div>
                    <div class="kpi-value">${props.metrics?.total_products || 0} Products</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Inventory Valuation</div>
                    <div class="kpi-value">${formatCurrency(props.metrics?.inventory_value)}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Low Stock Alerts</div>
                    <div class="kpi-value" style="color: ${props.metrics?.low_stock_count ? '#dc2626' : '#16a34a'};">
                        ${props.metrics?.low_stock_count || 0} Items
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Quotations</div>
                    <div class="kpi-value">${props.metrics?.total_quotations || 0} Proposals</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Approved Quotations Value</div>
                    <div class="kpi-value">${formatCurrency(props.metrics?.quotations_value)}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Sales Transactions</div>
                    <div class="kpi-value">${props.metrics?.total_sales || 0} Orders</div>
                </div>
            </div>

            <div class="notice">
                <strong>Certified System Extract:</strong> This document represents live operational figures from the MR-Pride MySQL database as of ${dateStr}. For full itemized records, refer to the corresponding CSV exports.
            </div>

            <div class="footer">
                <span>MR-Pride Electrical Supplies ERP System</span>
                <span>Page 1 of 1</span>
            </div>
            <script>
                window.onload = function() { window.print(); }
            <\/script>
        </body>
        </html>
    `;

    printWindow.document.write(content);
    printWindow.document.close();
};
</script>

<template>
    <Head title="System Reports" />

    <div class="workspace-page p-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                    <LineChart class="w-8 h-8 text-[#4b5320]" />
                    <span>System Reports & Analytics</span>
                </h1>
                <p class="text-gray-500 mt-1">Live operational data, inventory valuations, quotations, and official exports.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="10"
                class="w-full md:w-auto flex items-center gap-2"
                @click="downloadCsv('master')"
            >
                <Download class="w-4 h-4" />
                <span>Export Master Operations Dump (CSV)</span>
            </SpecularButton>
        </div>

        <!-- Live Database Metrics Ribbon -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Inventory Value</span>
                    <Package class="w-4 h-4 text-blue-600" />
                </div>
                <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(metrics?.inventory_value) }}</div>
                <p class="text-xs text-gray-500 mt-1">{{ metrics?.total_products || 0 }} total products cataloged</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Reorder Alerts</span>
                    <AlertTriangle class="w-4 h-4 text-amber-500" />
                </div>
                <div class="text-2xl font-bold" :class="metrics?.low_stock_count ? 'text-amber-600' : 'text-gray-900'">
                    {{ metrics?.low_stock_count || 0 }} Items
                </div>
                <p class="text-xs text-gray-500 mt-1">Below minimum stock threshold</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Quotations Pipeline</span>
                    <FileText class="w-4 h-4 text-emerald-600" />
                </div>
                <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(metrics?.quotations_value) }}</div>
                <p class="text-xs text-gray-500 mt-1">{{ metrics?.approved_quotations || 0 }} approved of {{ metrics?.total_quotations || 0 }} total</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Registered Suppliers</span>
                    <Truck class="w-4 h-4 text-purple-600" />
                </div>
                <div class="text-2xl font-bold text-gray-900">{{ metrics?.total_suppliers || 0 }} Vendors</div>
                <p class="text-xs text-gray-500 mt-1">AHP-evaluated vendor directory</p>
            </div>
        </div>

        <!-- Reports Grid -->
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Inventory Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                        <Package class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Inventory Status Report</h3>
                    <p class="text-gray-500 text-sm mb-6">Complete list of all electrical supplies, SKUs, current stock quantities, cost valuation, and supplier sources.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="printReport('Inventory Status Report')"
                    >
                        <Printer class="w-4 h-4" />
                        <span>Print Preview</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="downloadCsv('inventory')"
                    >
                        <Download class="w-4 h-4" />
                        <span>Download CSV</span>
                    </SpecularButton>
                </div>
            </div>

            <!-- Quotations Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4">
                        <FileText class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Quotations Summary Report</h3>
                    <p class="text-gray-500 text-sm mb-6">Itemized proposal records, customer details, quote validity dates, itemized costs, and approval statuses.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="printReport('Quotations Summary Report')"
                    >
                        <Printer class="w-4 h-4" />
                        <span>Print Preview</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="downloadCsv('quotations')"
                    >
                        <Download class="w-4 h-4" />
                        <span>Download CSV</span>
                    </SpecularButton>
                </div>
            </div>

            <!-- Sales Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-[#F8B803] flex items-center justify-center mb-4">
                        <TrendingUp class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Sales & Revenue Report</h3>
                    <p class="text-gray-500 text-sm mb-6">Full transaction history from POS direct walk-ins and quotation conversions, including item totals and payment methods.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="printReport('Sales & Revenue Report')"
                    >
                        <Printer class="w-4 h-4" />
                        <span>Print Preview</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="downloadCsv('sales')"
                    >
                        <Download class="w-4 h-4" />
                        <span>Download CSV</span>
                    </SpecularButton>
                </div>
            </div>

            <!-- Suppliers Directory Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-4">
                        <Truck class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Suppliers & AHP Evaluation Report</h3>
                    <p class="text-gray-500 text-sm mb-6">Complete directory of all registered electrical vendors, contact persons, phone numbers, and calculated AHP multi-criteria scores.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="printReport('Suppliers & AHP Directory Report')"
                    >
                        <Printer class="w-4 h-4" />
                        <span>Print Preview</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="sm"
                        :radius="8"
                        class="flex-1 flex items-center justify-center gap-1.5"
                        @click="downloadCsv('suppliers')"
                    >
                        <Download class="w-4 h-4" />
                        <span>Download CSV</span>
                    </SpecularButton>
                </div>
            </div>
        </div>
    </div>
</template>
