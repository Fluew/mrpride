<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, shallowRef } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import FadeContent from '@/components/vue-bits/FadeContent.vue';
import SpotlightCard from '@/components/vue-bits/SpotlightCard.vue';
import { dashboard } from '@/routes';
import { index as quotationsRoute } from '@/routes/admin/quotations';
import { index as inventoryRoute } from '@/routes/inventory';
import type { DashboardInvitation, Team } from '@/types';

// Lazy-load Chart.js + vue-chartjs — keeps them out of the initial bundle
const LineChart = defineAsyncComponent(async () => {
    const chartjs = await import('chart.js');
    const { Line } = await import('vue-chartjs');
    chartjs.Chart.register(
        chartjs.CategoryScale,
        chartjs.LinearScale,
        chartjs.PointElement,
        chartjs.LineElement,
        chartjs.Title,
        chartjs.Tooltip,
        chartjs.Legend,
    );

    return Line;
});

// shallowRef avoids deep Vue reactivity overhead on plain chart data
const chartData = shallowRef({
    labels: [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
    ],
    datasets: [
        {
            label: 'Historical Demand',
            backgroundColor: '#4ade80',
            borderColor: '#4ade80',
            data: [150, 170, 160, 180, 190, 200, 220, 210, 230, 250, null],
            tension: 0.3,
        },
        {
            label: 'Forecast (SMA)',
            backgroundColor: '#F8B803',
            borderColor: '#F8B803',
            borderDash: [5, 5],
            data: [
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                250,
                275,
            ],
            tension: 0.3,
        },
    ],
});

const chartOptions = shallowRef({
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 400 },
    plugins: {
        legend: { labels: { color: '#333' } },
    },
    scales: {
        x: { ticks: { color: '#666' }, grid: { color: '#eee' } },
        y: { ticks: { color: '#666' }, grid: { color: '#eee' } },
    },
});

interface ActivityItem {
    id: string;
    type: string;
    status: string;
    class: string;
}

interface DashboardStats {
    totalProducts?: number;
    lowStockCount?: number;
    todaySales?: number;
    topSupplier?: {
        name: string;
        score: string;
    };
}

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    stats?: DashboardStats;
    recentActivity?: ActivityItem[];
    isFirstLogin?: boolean;
}>();

const page = usePage();
const currentUser = computed(() => (page.props as any).auth?.user);

const firstName = computed(() => {
    if (currentUser.value?.first_name) {
        return currentUser.value.first_name;
    }
    const name = currentUser.value?.name?.trim() || '';
    if (!name) return 'User';
    return name.split(' ')[0] || 'User';
});

const isFirstTimeLogin = computed(() => {
    if (props.isFirstLogin !== undefined) {
        return Boolean(props.isFirstLogin);
    }
    return Boolean((page.props as any).auth?.is_first_login);
});

const formatCurrency = (val?: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(val ?? 0);
};

const displayActivities = computed(() => {
    if (props.recentActivity && props.recentActivity.length > 0) {
        return props.recentActivity;
    }

    return [
        {
            id: 'No recent activity',
            type: 'System',
            status: 'Idle',
            class: 'bg-gray-500/10 text-gray-500 border-gray-500/20',
        },
    ];
});

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

    <div class="workspace-page">
        <!-- Dashboard Header -->
        <FadeContent class="mb-4 flex flex-col gap-1">
            <span
                class="text-xs font-bold uppercase tracking-wider text-[#4b5320] dark:text-[#F8B803]"
            >
                WORKSPACE OVERVIEW
            </span>
            <h1
                class="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl dark:text-white"
            >
                Hello, {{ firstName }}!
            </h1>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
                <template v-if="isFirstTimeLogin">
                    Welcome to MRP ElectroDesk. Let's get started.
                </template>
                <template v-else>
                    Welcome back to MRP ElectroDesk.
                </template>
            </p>
        </FadeContent>

        <div v-if="$page.props.currentTeam" class="flex flex-wrap gap-3">
            <Link
                :href="inventoryRoute($page.props.currentTeam.slug)"
                class="brand-button"
                >Manage inventory</Link
            ><Link
                :href="quotationsRoute($page.props.currentTeam.slug)"
                class="secondary-button"
                >Review quotations</Link
            >
        </div>
        <!-- Stat Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <SpotlightCard
                class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-200 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="mb-1 text-sm font-medium text-gray-500">
                            Total Products
                        </p>
                        <h3
                            class="text-2xl font-bold text-gray-900 sm:text-3xl"
                        >
                            {{ stats?.totalProducts?.toLocaleString() ?? 0 }}
                        </h3>
                    </div>
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-100 bg-gray-50 text-[#F8B803]"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            />
                        </svg>
                    </div>
                </div>
            </SpotlightCard>

            <SpotlightCard
                class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-200 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="mb-1 text-sm font-medium text-gray-500">
                            Low Stock Items
                        </p>
                        <h3 class="text-2xl font-bold text-red-600 sm:text-3xl">
                            {{ stats?.lowStockCount?.toLocaleString() ?? 0 }}
                        </h3>
                    </div>
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-500"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                </div>
            </SpotlightCard>

            <SpotlightCard
                class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-200 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="mb-1 text-sm font-medium text-gray-500">
                            Today's Sales
                        </p>
                        <h3
                            class="text-2xl font-bold text-gray-900 sm:text-3xl"
                        >
                            {{ formatCurrency(stats?.todaySales) }}
                        </h3>
                    </div>
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-green-100 bg-green-50 text-green-600"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                </div>
            </SpotlightCard>

            <SpotlightCard
                class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-200 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="mb-1 text-sm font-medium text-gray-500">
                            Top Supplier (AHP)
                        </p>
                        <h3 class="text-lg font-bold text-[#4b5320]">
                            {{ stats?.topSupplier?.name ?? 'No Supplier' }}
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Score: {{ stats?.topSupplier?.score ?? 'N/A' }}
                        </p>
                    </div>
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#F8B803]/20 bg-[#F8B803]/10 text-[#F8B803]"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                </div>
            </SpotlightCard>
        </div>

        <div
            class="grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,1fr)]"
        >
            <!-- Chart Area — lazy-loaded via Suspense, skeleton shown while loading -->
            <div
                class="min-w-0 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
            >
                <h3 class="mb-4 text-lg font-bold text-gray-900">
                    Sales &amp; Demand Forecast (SMA)
                </h3>
                <div class="h-[280px] w-full sm:h-[300px]">
                    <Suspense>
                        <LineChart :data="chartData" :options="chartOptions" />
                        <template #fallback>
                            <div
                                class="flex h-full animate-pulse flex-col justify-end gap-3 pb-4"
                            >
                                <div class="flex h-full items-end gap-2">
                                    <div
                                        v-for="i in 11"
                                        :key="i"
                                        class="flex-1 rounded-t bg-gray-200"
                                        :style="`height:${30 + Math.random() * 60}%`"
                                    ></div>
                                </div>
                                <div class="flex gap-2">
                                    <div
                                        v-for="i in 11"
                                        :key="i"
                                        class="h-2 flex-1 rounded bg-gray-100"
                                    ></div>
                                </div>
                            </div>
                        </template>
                    </Suspense>
                </div>
            </div>

            <!-- Recent Activity Feed -->
            <div
                class="max-h-[400px] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
            >
                <h3 class="mb-4 text-lg font-bold text-gray-900">
                    Recent Activity Feed
                </h3>
                <ul class="divide-y divide-gray-100">
                    <li
                        v-for="(activity, idx) in displayActivities"
                        :key="activity.id || idx"
                        class="flex items-center justify-between gap-3 py-4"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-sm font-semibold break-words text-gray-900"
                            >
                                {{ activity.id }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ activity.type }}
                            </p>
                        </div>
                        <span
                            :class="activity.class"
                            class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium"
                            >{{ activity.status }}</span
                        >
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
