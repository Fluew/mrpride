<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Wrench, Clock, ShieldCheck } from '@lucide/vue';

defineProps<{
    title?: string;
    message?: string;
    reason?: string;
    expectedReturnTime?: string | null;
    enabledAt?: string | null;
}>();
</script>

<template>
    <Head title="System Under Maintenance - MRP ElectroDesk" />

    <div class="min-h-screen bg-gradient-to-b from-gray-50 via-gray-100/60 to-gray-50 flex items-center justify-center p-4 sm:p-6 font-sans">
        <div class="w-full max-w-xl">
            <!-- Main Maintenance Card -->
            <div class="bg-white border border-gray-200/90 rounded-3xl p-6 sm:p-10 shadow-xl shadow-gray-200/50 text-center relative overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-[#4b5320] via-[#F8B803] to-[#4b5320]"></div>

                <!-- Wrench/Shield Icon -->
                <div class="mx-auto size-20 rounded-2xl bg-amber-50 border border-amber-200/80 flex items-center justify-center text-amber-600 mb-6 shadow-xs">
                    <Wrench class="size-9 animate-pulse" />
                </div>

                <!-- Required Titles -->
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                    {{ title || 'System Under Maintenance' }}
                </h1>
                <p class="text-sm sm:text-base text-gray-600 mt-3 font-medium leading-relaxed max-w-md mx-auto">
                    {{ message || 'MRP ElectroDesk is temporarily unavailable while we perform system maintenance.' }}
                </p>

                <!-- Expected Return Time Badge -->
                <div v-if="expectedReturnTime" class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-full text-xs font-semibold text-amber-900 shadow-2xs">
                    <Clock class="size-4 text-amber-600 shrink-0" />
                    <span>Expected Return: <strong class="text-amber-950 font-bold">{{ expectedReturnTime }}</strong></span>
                </div>

                <!-- Controlled Maintenance Procedure Active -->
                <div class="mt-6 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-gray-50 border border-gray-200/80 text-gray-700 font-semibold text-xs shadow-2xs">
                    <ShieldCheck class="size-3.5 text-[#4b5320]" />
                    <span>Controlled Maintenance Procedure Active</span>
                </div>
            </div>

            <!-- Footer Branding -->
            <p class="text-center text-xs text-gray-400 mt-6 font-medium">
                MRP ElectroDesk
            </p>
        </div>
    </div>
</template>
