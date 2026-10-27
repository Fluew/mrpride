<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import {
    Wrench,
    ShieldCheck,
    CheckCircle2,
    XCircle,
    Clock,
    RefreshCw,
    AlertTriangle,
} from '@lucide/vue';

interface VerificationTest {
    id: string;
    title: string;
    status: 'passed' | 'failed';
    latency_ms?: number | null;
    message: string;
}

interface VerificationResult {
    all_passed: boolean;
    verified_at: string;
    tests: VerificationTest[];
}

const props = defineProps<{
    status: {
        enabled: boolean;
        reason?: string;
        expected_return_time?: string | null;
        enabled_at?: string | null;
        enabled_by?: string | null;
    };
    isSuperAdmin: boolean;
}>();

const enableForm = useForm({
    reason: props.status.reason || 'Database updates, system upgrades, deployment, or scheduled server maintenance.',
    duration_minutes: 30,
    expected_return_time: '',
});

const isVerifying = ref(false);
const verificationResult = ref<VerificationResult | null>(null);
const verificationError = ref<string | null>(null);

const runVerification = async () => {
    isVerifying.value = true;
    verificationError.value = null;
    try {
        const response = await fetch('/settings/maintenance/verify', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) {
            throw new Error(`Server returned HTTP ${response.status}`);
        }
        const data = await response.json();
        verificationResult.value = data;
    } catch (e: any) {
        console.error('Verification check failed', e);
        verificationError.value = e?.message || 'Unable to connect to verification service.';
    } finally {
        isVerifying.value = false;
    }
};

const submitEnable = () => {
    enableForm.post('/settings/maintenance/enable', {
        preserveScroll: true,
    });
};

const submitDisable = () => {
    router.post('/settings/maintenance/disable', {}, {
        preserveScroll: true,
        onSuccess: () => {
            verificationResult.value = null;
        },
    });
};
</script>

<template>
    <Head title="Admin Maintenance" />

    <div class="px-4 py-8 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-6">
        <!-- Maintenance Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="size-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold shadow-xs">
                        <Wrench class="size-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Admin Maintenance</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300">
                                Administrator Control
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Manage platform maintenance mode, transaction protections, and pre-flight health checks.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    href="/settings/profile"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-lg shadow-2xs hover:bg-gray-50 hover:text-gray-900 transition-colors"
                >
                    Account Settings &rarr;
                </Link>
            </div>
        </div>

        <!-- 1. Admin Maintenance Card -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-[#4b5320]/10 text-[#4b5320] flex items-center justify-center">
                        <Wrench class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Admin Maintenance</h2>
                        <p class="text-xs text-gray-500">Enable or update maintenance mode to pause non-admin user operations and protect ongoing transactions.</p>
                    </div>
                </div>

                <!-- Status Badge & Quick Reopen -->
                <div class="flex items-center gap-2.5">
                    <span
                        :class="[
                            'px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider inline-flex items-center gap-1.5',
                            status.enabled
                                ? 'bg-amber-100 text-amber-900 border border-amber-300'
                                : 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                        ]"
                    >
                        <span :class="['size-1.5 rounded-full', status.enabled ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500']"></span>
                        {{ status.enabled ? 'Maintenance Active' : 'Normal Operation' }}
                    </span>

                    <SpecularButton
                        v-if="status.enabled"
                        variant="secondary"
                        size="sm"
                        :radius="8"
                        @click="submitDisable"
                    >
                        <CheckCircle2 class="size-3.5 mr-1 inline text-emerald-600" />
                        Reopen System
                    </SpecularButton>
                </div>
            </div>

            <!-- Current Maintenance Info Notice if active -->
            <div
                v-if="status.enabled"
                class="p-4 rounded-xl bg-amber-50/90 border border-amber-200 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
            >
                <div class="space-y-1">
                    <div class="font-bold">System is currently closed to staff and visitors.</div>
                    <div class="text-amber-800">
                        Reason: <strong>{{ status.reason }}</strong>
                        <span v-if="status.expected_return_time"> • Expected Return: <strong>{{ status.expected_return_time }}</strong></span>
                    </div>
                </div>
                <div class="shrink-0 flex items-center gap-1.5 text-[11px] text-amber-700 bg-amber-100/70 px-2.5 py-1 rounded-lg">
                    <Clock class="size-3.5" />
                    <span>Active since {{ status.enabled_at ? new Date(status.enabled_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'recently' }}</span>
                </div>
            </div>

            <!-- Enable / Update Form -->
            <form @submit.prevent="submitEnable" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700">Maintenance Reason / Notice</label>
                    <input
                        v-model="enableForm.reason"
                        type="text"
                        required
                        placeholder="e.g. Database updates, system upgrades, deployment, or scheduled server maintenance."
                        class="block w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700">Estimated Duration</label>
                        <select
                            v-model="enableForm.duration_minutes"
                            class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-[#4b5320] focus:outline-none"
                        >
                            <option :value="15">15 Minutes</option>
                            <option :value="30">30 Minutes (Recommended)</option>
                            <option :value="60">1 Hour</option>
                            <option :value="120">2 Hours</option>
                            <option :value="240">4 Hours</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700">Expected Return Time (Optional)</label>
                        <input
                            v-model="enableForm.expected_return_time"
                            type="text"
                            placeholder="e.g. 02:30 PM (approx. 20 mins)"
                            class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-[#4b5320] focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Transaction Safeguard Indicator -->
                <div class="rounded-xl bg-gray-50 border border-gray-200/80 p-3.5 flex items-center justify-between text-xs text-gray-600">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="size-4 text-[#4b5320] shrink-0" />
                        <span><strong>Transaction Safeguards Active:</strong> In-progress sales POS, inventory deductions, and procurement records are protected from partial persistence.</span>
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <SpecularButton
                        type="submit"
                        :variant="status.enabled ? 'secondary' : 'brand'"
                        size="md"
                        :radius="10"
                        :disabled="enableForm.processing"
                    >
                        <Wrench class="size-4 mr-1.5 inline" />
                        {{ status.enabled ? 'Update Maintenance Details' : 'Enable Maintenance Mode' }}
                    </SpecularButton>
                </div>
            </form>
        </div>

        <!-- 2. Verify Before Reopening Card -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                        <ShieldCheck class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Verify Before Reopening</h2>
                        <p class="text-xs text-gray-500">Check database connectivity, login, device verification, sales POS, and stock deductions before reopening the platform.</p>
                    </div>
                </div>

                <SpecularButton
                    variant="brand"
                    size="sm"
                    :radius="8"
                    @click="runVerification"
                    :disabled="isVerifying"
                >
                    <RefreshCw :class="['size-3.5 mr-1 inline', { 'animate-spin': isVerifying }]" />
                    <span>{{ isVerifying ? 'Testing Functions...' : 'Run System Health Verification' }}</span>
                </SpecularButton>
            </div>

            <!-- Error State if request fails -->
            <div v-if="verificationError" class="p-3.5 rounded-xl border bg-red-50 border-red-200 text-red-900 text-xs flex items-center gap-2">
                <AlertTriangle class="size-4 text-red-600 shrink-0" />
                <span>Verification check failed: {{ verificationError }}</span>
            </div>

            <!-- Verification Results Panel -->
            <div v-if="verificationResult" class="space-y-3 pt-1">
                <div
                    :class="[
                        'p-3.5 rounded-xl border flex items-center justify-between text-xs font-semibold',
                        verificationResult.all_passed
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
                            : 'bg-red-50 border-red-200 text-red-900'
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2 v-if="verificationResult.all_passed" class="size-4.5 text-emerald-600" />
                        <AlertTriangle v-else class="size-4.5 text-red-600" />
                        <span>{{ verificationResult.all_passed ? 'All Critical Systems Operational & Verified' : 'One or More Functions Failed Verification' }}</span>
                    </div>
                    <span class="text-[11px] text-gray-500 font-normal">Verified: {{ verificationResult.verified_at }}</span>
                </div>

                <!-- Individual Tests Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div
                        v-for="test in verificationResult.tests"
                        :key="test.id"
                        class="rounded-xl border border-gray-200 bg-white p-3 text-xs space-y-1 shadow-2xs"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-900">{{ test.title }}</span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase inline-flex items-center gap-1',
                                    test.status === 'passed' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                                ]"
                            >
                                <CheckCircle2 v-if="test.status === 'passed'" class="size-3" />
                                <XCircle v-else class="size-3" />
                                {{ test.status }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-600 leading-normal">{{ test.message }}</p>
                    </div>
                </div>

                <!-- Reopen Action after verification passes -->
                <div v-if="status.enabled && verificationResult.all_passed" class="pt-4 flex justify-end">
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        @click="submitDisable"
                    >
                        <CheckCircle2 class="size-4 mr-1.5 inline" />
                        Verify & Reopen MRP ElectroDesk
                    </SpecularButton>
                </div>
            </div>

            <div v-else class="rounded-xl border border-dashed border-gray-200 p-5 text-center text-xs text-gray-500">
                Click <strong>"Run System Health Verification"</strong> above to test all critical components prior to reopening the platform.
            </div>
        </div>
    </div>
</template>
