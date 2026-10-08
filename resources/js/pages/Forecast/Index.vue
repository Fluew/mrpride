<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

const dateRange = ref('2026');

const baseLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const chartData = computed(() => {
  // Generate different deterministic data based on the selected year
  const yearSeed = parseInt(dateRange.value) || 2026;
  
  const historical = [];
  const forecast = [];
  
  for (let i = 0; i < 12; i++) {
    const baseVal = 200 + (yearSeed - 2020) * 50;
    const variation = Math.sin(i + yearSeed) * 50;
    const val = Math.floor(baseVal + variation + Math.random() * 20);
    
    // For the current year (2026), let's say only first 10 months have historical data
    if (yearSeed === 2026 && i > 9) {
      historical.push(null);
      forecast.push(Math.floor(val * 1.1));
    } else {
      historical.push(val);
      // Historical years don't have future forecasts, except maybe SMA overlays
      forecast.push(i > 8 ? Math.floor(val * 1.1) : null);
    }
  }
  
  return {
    labels: baseLabels,
    datasets: [
      {
        label: 'Historical Demand (Units)',
        backgroundColor: '#4ade80',
        borderColor: '#4ade80',
        data: historical,
        tension: 0.3
      },
      {
        label: 'Forecast (SMA)',
        backgroundColor: '#F8B803',
        borderColor: '#F8B803',
        borderDash: [5, 5],
        data: forecast,
        tension: 0.3
      }
    ]
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      labels: { color: '#111827' }
    },
    tooltip: {
      mode: 'index' as const,
      intersect: false,
    }
  },
  scales: {
    x: {
      ticks: { color: '#6b7280' },
      grid: { color: '#e5e7eb' }
    },
    y: {
      ticks: { color: '#6b7280' },
      grid: { color: '#e5e7eb' }
    }
  }
};
</script>

<template>
    <Head title="Demand Forecasting" />

    <div class="workspace-page">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Demand Forecasting</h1>
                <p class="text-gray-500 mt-1">AI and Simple Moving Average predictive analytics for inventory.</p>
            </div>
            <button class="w-full md:w-auto px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-all duration-300 shadow-sm">
                Export Forecast Data
            </button>
        </div>
        <div class="flex gap-4 mb-4">
            <div class="flex items-center gap-2">
                <span class="text-gray-500 text-sm">Select Year:</span>
                <select v-model="dateRange" class="bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none min-w-[200px] shadow-sm">
                    <option value="2026">2026 (Current)</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                    <option value="2021">2021</option>
                    <option value="2020">2020</option>
                </select>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Main Chart -->
            <div class="lg:col-span-2 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col">
                <h3 class="text-xl font-bold text-gray-900 mb-6">{{ dateRange }} Demand Trend</h3>
                <div class="flex-1 min-h-[400px]">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Recommendations -->
            <div class="flex flex-col gap-6">
                <!-- Highlight Card -->
                <div class="rounded-2xl bg-gradient-to-br from-[#F8B803]/10 to-[#4b5320]/5 border border-[#F8B803]/20 p-6 shadow-sm">
                    <h4 class="text-[#F8B803] font-bold mb-2">Expected Peak Ahead</h4>
                    <p class="text-gray-700 text-sm mb-4">Historical data indicates a 15% surge in electrical supply demand during the upcoming holiday season (Nov-Dec).</p>
                    <h2 class="text-4xl font-black text-gray-900">+15%</h2>
                </div>

                <!-- Suggested Restocks -->
                <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Suggested Restocks</h3>
                    
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg border border-red-200 bg-red-50">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-medium text-gray-900">LED Floodlight 200W</span>
                                <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded">Urgent</span>
                            </div>
                            <p class="text-xs text-gray-600 mb-2">Current Stock: 0 | Predicted Need: 150</p>
                            <button class="w-full py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded transition-colors shadow-sm">Create Purchase Request</button>
                        </div>
                        
                        <div class="p-3 rounded-lg border border-[#F8B803]/20 bg-[#F8B803]/5">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-medium text-gray-900">Heavy Duty Copper Wire</span>
                                <span class="text-xs bg-[#F8B803]/10 text-[#F8B803] px-2 py-0.5 rounded">Soon</span>
                            </div>
                            <p class="text-xs text-gray-600 mb-2">Current Stock: 8 | Predicted Need: 45</p>
                            <button class="w-full py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded transition-colors shadow-sm">Create Purchase Request</button>
                        </div>

                        <div class="p-3 rounded-lg border border-gray-200 bg-gray-50">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-medium text-gray-900">Industrial Circuit Breaker</span>
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded">Monitor</span>
                            </div>
                            <p class="text-xs text-gray-600 mb-2">Current Stock: 45 | Predicted Need: 60</p>
                            <button class="w-full py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded transition-colors shadow-sm">Create Purchase Request</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
