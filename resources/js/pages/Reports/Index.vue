<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Package, AlertTriangle, FileText, LineChart, Download } from '@lucide/vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';

const exportReport = (reportName: string) => {
    alert(`Downloading ${reportName} (Prototype)`);
};
</script>

<template>
    <Head title="System Reports" />

    <div class="workspace-page">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">System Reports</h1>
                <p class="text-gray-500 mt-1">Generate and export system data for review.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="10"
                class="w-full md:w-auto"
                @click="exportReport('All Master Data Report.pdf')"
            >
                <Download class="w-4 h-4" />
                <span>Export All Data</span>
            </SpecularButton>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- Inventory Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                        <Package class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Inventory Status Report</h3>
                    <p class="text-gray-500 text-sm mb-6">Complete list of all electrical supplies, current stock levels, categories, and unit pricing.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('Inventory_Report.pdf')"
                    >
                        <Download class="w-4 h-4" />
                        <span>PDF</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('Inventory_Report.csv')"
                    >
                        <Download class="w-4 h-4" />
                        <span>CSV</span>
                    </SpecularButton>
                </div>
            </div>

            <!-- Low Stock Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-lg bg-red-50 text-red-600 flex items-center justify-center mb-4">
                        <AlertTriangle class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Low Stock Alert Report</h3>
                    <p class="text-gray-500 text-sm mb-6">List of items that have fallen below their minimum required threshold and require immediate reordering.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('LowStock_Report.pdf')"
                    >
                        <Download class="w-4 h-4" />
                        <span>PDF</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('LowStock_Report.csv')"
                    >
                        <Download class="w-4 h-4" />
                        <span>CSV</span>
                    </SpecularButton>
                </div>
            </div>

            <!-- Quotation Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mb-4">
                        <FileText class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Quotations & Sales Report</h3>
                    <p class="text-gray-500 text-sm mb-6">Summary of all generated quotes, their current approval status, and total potential revenue.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('Quotations_Report.pdf')"
                    >
                        <Download class="w-4 h-4" />
                        <span>PDF</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('Quotations_Report.csv')"
                    >
                        <Download class="w-4 h-4" />
                        <span>CSV</span>
                    </SpecularButton>
                </div>
            </div>

            <!-- Forecast Report -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between hover:border-gray-300 transition-colors">
                <div>
                    <div class="w-12 h-12 rounded-lg bg-[#F8B803]/10 text-[#F8B803] flex items-center justify-center mb-4">
                        <LineChart class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Demand Forecast Analysis</h3>
                    <p class="text-gray-500 text-sm mb-6">Analytical data for predicted demand over the next 3 months based on historical Simple Moving Averages.</p>
                </div>
                <div class="flex gap-3">
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('Forecast_Analysis.pdf')"
                    >
                        <Download class="w-4 h-4" />
                        <span>PDF</span>
                    </SpecularButton>
                    <SpecularButton
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        class="flex-1"
                        @click="exportReport('Forecast_Analysis.csv')"
                    >
                        <Download class="w-4 h-4" />
                        <span>CSV</span>
                    </SpecularButton>
                </div>
            </div>
        </div>
    </div>
</template>
