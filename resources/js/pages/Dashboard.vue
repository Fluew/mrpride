<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
);

const chartData = {
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
  datasets: [
    {
      label: 'Historical Demand',
      backgroundColor: '#4ade80',
      borderColor: '#4ade80',
      data: [150, 170, 160, 180, 190, 200, 220, 210, 230, 250, null],
      tension: 0.3
    },
    {
      label: 'Forecast (SMA)',
      backgroundColor: '#F8B803',
      borderColor: '#F8B803',
      borderDash: [5, 5],
      data: [null, null, null, null, null, null, null, null, null, 250, 275],
      tension: 0.3
    }
  ]
};

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      labels: { color: '#333' }
    }
  },
  scales: {
    x: {
      ticks: { color: '#666' },
      grid: { color: '#eee' }
    },
    y: {
      ticks: { color: '#666' },
      grid: { color: '#eee' }
    }
  }
};

const recentActivity = [
    { id: 'PRJ-0092', type: 'Quotation', status: 'Approved', class: 'bg-green-500/10 text-green-500 border-green-500/20' },
    { id: 'INV-2023-004', type: 'Sale', status: 'Pending', class: 'bg-[#F8B803]/10 text-[#F8B803] border-[#F8B803]/20' },
    { id: 'PR-2023-11', type: 'Procurement', status: 'In Review', class: 'bg-blue-500/10 text-blue-500 border-blue-500/20' },
    { id: 'PRJ-0089', type: 'Quotation', status: 'Draft', class: 'bg-gray-500/10 text-gray-400 border-gray-500/20' },
    { id: 'INV-2023-003', type: 'Sale', status: 'Completed', class: 'bg-green-500/10 text-green-500 border-green-500/20' },
];

defineProps<{
    pendingInvitations?: DashboardInvitation[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto rounded-xl p-6">
        <!-- Dashboard Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">MR-Pride Electrical Supplies</h1>
                <p class="text-gray-500 mt-1">An AHP-Based Inventory Management & Decision Support System.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                <input type="text" placeholder="Search system..." class="bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none min-w-[250px] w-full shadow-sm" />
                <select class="bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none w-full shadow-sm">
                    <option>All Modules</option>
                    <option>Inventory</option>
                    <option>Quotations</option>
                    <option>Procurement</option>
                </select>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="grid auto-rows-min gap-6 md:grid-cols-4">
            <div class="relative overflow-hidden rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <p class="text-gray-500 text-sm font-medium mb-1">Total Products</p>
                        <h3 class="text-3xl font-bold text-gray-900">1,245</h3>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center text-[#F8B803]">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    </div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <p class="text-gray-500 text-sm font-medium mb-1">Low Stock Items</p>
                        <h3 class="text-3xl font-bold text-red-600">14</h3>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-red-50 border border-red-100 flex items-center justify-center text-red-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <p class="text-gray-500 text-sm font-medium mb-1">Today's Sales</p>
                        <h3 class="text-3xl font-bold text-gray-900">₱45,200</h3>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-green-50 border border-green-100 flex items-center justify-center text-green-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <p class="text-gray-500 text-sm font-medium mb-1">Top Supplier (AHP)</p>
                        <h3 class="text-xl font-bold text-[#F8B803]">ElectroTech Ind.</h3>
                        <p class="text-xs text-gray-500 mt-1">Score: 0.842</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-[#F8B803]/10 border border-[#F8B803]/20 flex items-center justify-center text-[#F8B803]">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Chart Area -->
            <div class="lg:col-span-2 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Sales & Demand Forecast (SMA)</h3>
                <div class="h-[300px] w-full">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Main Content Area (Data Table/Activity Feed) -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm h-[400px] overflow-y-auto">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Recent Activity Feed</h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 text-sm">
                                <th class="pb-3 font-medium">Reference ID</th>
                                <th class="pb-3 font-medium">Type</th>
                                <th class="pb-3 font-medium text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <tr v-for="activity in recentActivity" :key="activity.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                                <td class="py-4 text-gray-900 font-medium">{{ activity.id }}</td>
                                <td class="py-4 text-gray-600">{{ activity.type }}</td>
                                <td class="py-4 text-right">
                                    <span :class="activity.class" class="px-2.5 py-1 rounded-full text-xs font-medium border">{{ activity.status }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
