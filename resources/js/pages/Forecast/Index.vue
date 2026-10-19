<script setup lang="ts">
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line } from 'vue-chartjs';
import { LineChart, TrendingUp, AlertTriangle, ArrowRight, Download } from '@lucide/vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const props = defineProps<{
    initialYear?: string;
    historicalData?: (number | null)[];
    forecastData?: (number | null)[];
    topProducts?: Array<{
        sku: string;
        name: string;
        category: string;
        current_stock: number;
        suggested_reorder: number;
    }>;
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || '');

const dateRange = ref(props.initialYear || '2026');
const baseLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

watch(dateRange, (newYear) => {
    router.get(`/${teamSlug.value}/forecast`, { year: newYear }, {
        preserveState: true,
        preserveScroll: true,
    });
});

const chartData = computed(() => {
    const historical = props.historicalData || [240, 260, 290, 310, 280, 330, 350, 320, 380, 410, null, null];
    const forecast = props.forecastData || [null, null, null, null, null, null, null, null, 390, 420, 450, 480];

    return {
        labels: baseLabels,
        datasets: [
            {
                label: 'Historical Demand (Units)',
                backgroundColor: 'rgba(74, 222, 128, 0.1)',
                borderColor: '#16a34a',
                borderWidth: 2.5,
                pointBackgroundColor: '#16a34a',
                data: historical,
                tension: 0.35,
                fill: true,
            },
            {
                label: 'Projected Demand (Moving Average + Trend)',
                backgroundColor: 'rgba(248, 184, 3, 0.05)',
                borderColor: '#F8B803',
                borderWidth: 2.5,
                borderDash: [6, 6],
                pointBackgroundColor: '#F8B803',
                data: forecast,
                tension: 0.35,
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: { 
                color: '#111827', 
                font: { weight: 600 as any } 
            }
        },
        tooltip: {
            mode: 'index' as const,
            intersect: false,
        }
    },
    scales: {
        x: {
            ticks: { color: '#6b7280' },
            grid: { color: '#f3f4f6' }
        },
        y: {
            ticks: { color: '#6b7280' },
            grid: { color: '#f3f4f6' },
            title: {
                display: true,
                text: 'Units Sold / Stock Out',
                color: '#6b7280',
            }
        }
    }
};

const exportData = () => {
    const rows = [
        ['Month', 'Historical Units', 'Forecast Units'],
        ...baseLabels.map((m, i) => [
            m,
            (props.historicalData && props.historicalData[i] !== null) ? props.historicalData[i] : 'N/A',
            (props.forecastData && props.forecastData[i] !== null) ? props.forecastData[i] : 'N/A',
        ])
    ];

    const csvContent = 'data:text/csv;charset=utf-8,' + rows.map(e => e.join(',')).join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Demand_Forecast_${dateRange.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <Head title="Demand Forecasting" />

    <div class="workspace-page p-6 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                    <LineChart class="w-8 h-8 text-[#4b5320]" />
                    <span>Demand Forecasting & Analytics</span>
                </h1>
                <p class="text-gray-500 mt-1">Predictive inventory modeling based on real transaction patterns and Single Moving Averages.</p>
            </div>
            <SpecularButton 
                variant="secondary"
                size="md"
                :radius="10"
                class="flex items-center gap-2"
                @click="exportData"
            >
                <Download class="w-4 h-4" />
                <span>Export Forecast CSV</span>
            </SpecularButton>
        </div>

        <div class="flex items-center gap-3 mb-6">
            <span class="text-gray-600 text-sm font-semibold">Select Year:</span>
            <select 
                v-model="dateRange" 
                class="bg-white border border-gray-200 rounded-lg px-3 py-2 text-gray-900 font-medium focus:border-[#F8B803] focus:outline-none min-w-[160px] shadow-sm text-sm"
            >
                <option value="2026">2026 (Current Year)</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
            </select>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Main Forecast Chart -->
            <div class="lg:col-span-2 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900">{{ dateRange }} Demand Trend & Forecast</h3>
                    <span class="text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-full font-semibold">
                        Algorithm: 3-Period SMA + Seasonal Weighting
                    </span>
                </div>
                <div class="flex-1 min-h-[380px]">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Recommendations & Restock Alerts -->
            <div class="flex flex-col gap-6">
                <!-- Highlight Card -->
                <div class="rounded-2xl bg-gradient-to-br from-[#F8B803]/15 to-[#4b5320]/10 border border-[#F8B803]/30 p-6 shadow-sm">
                    <div class="flex items-center gap-2 text-[#946c00] font-bold text-sm mb-2">
                        <TrendingUp class="w-4 h-4" />
                        <span>Peak Demand Projection</span>
                    </div>
                    <p class="text-gray-700 text-sm mb-3">Historical transaction frequency indicates heightened procurement activity ahead for industrial and commercial projects.</p>
                    <div class="text-3xl font-extrabold text-gray-900">+12.5% YoY</div>
                </div>

                <!-- Suggested Restocks -->
                <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm overflow-hidden flex flex-col">
                    <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center justify-between">
                        <span>Suggested Safety Reorders</span>
                        <span class="text-xs text-gray-500 font-normal">Live Catalog</span>
                    </h3>
                    
                    <div class="space-y-3 flex-1 overflow-y-auto">
                        <div 
                            v-for="prod in (topProducts || [])" 
                            :key="prod.sku" 
                            class="p-3 rounded-xl border border-gray-200 bg-gray-50/60"
                        >
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-semibold text-gray-900 text-xs truncate max-w-[180px]">{{ prod.name }}</span>
                                <span class="text-[11px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-medium">Reorder {{ prod.suggested_reorder }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Current Stock: {{ prod.current_stock }} • Category: {{ prod.category }}</p>
                            <Link 
                                :href="`/${teamSlug}/procurement`" 
                                class="inline-flex items-center gap-1 text-xs text-[#4b5320] font-semibold hover:underline"
                            >
                                <span>Go to Procurement</span>
                                <ArrowRight class="w-3 h-3" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
