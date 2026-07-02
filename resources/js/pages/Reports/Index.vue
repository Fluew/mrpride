<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Package, AlertTriangle, FileText, LineChart, Download } from '@lucide/vue';

const exportReport = (reportName: string) => {
    alert(`Downloading ${reportName} (Prototype)`);
};
</script>

<template>
    <Head title="System Reports" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">System Reports</h1>
                <p class="text-gray-500 mt-1">Generate and export system data for review.</p>
            </div>
            <button @click="exportReport('All Master Data Report.pdf')" class="w-full md:w-auto px-5 py-2.5 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all duration-300 flex justify-center items-center gap-2">
                <Download class="w-4 h-4" /> Export All Data
            </button>
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
                    <button @click="exportReport('Inventory_Report.pdf')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> PDF
                    </button>
                    <button @click="exportReport('Inventory_Report.csv')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> CSV
                    </button>
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
                    <button @click="exportReport('LowStock_Report.pdf')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> PDF
                    </button>
                    <button @click="exportReport('LowStock_Report.csv')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> CSV
                    </button>
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
                    <button @click="exportReport('Quotations_Report.pdf')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> PDF
                    </button>
                    <button @click="exportReport('Quotations_Report.csv')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> CSV
                    </button>
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
                    <button @click="exportReport('Forecast_Analysis.pdf')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> PDF
                    </button>
                    <button @click="exportReport('Forecast_Analysis.csv')" class="flex-1 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg border border-gray-200 transition-colors text-sm font-medium flex justify-center items-center gap-2 shadow-sm">
                        <Download class="w-4 h-4" /> CSV
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
