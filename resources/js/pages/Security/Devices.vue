<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import {
    Laptop,
    ShieldCheck,
    ShieldAlert,
    Trash2,
    CheckCircle2,
    XCircle,
    KeyRound,
    Copy,
    Check,
} from '@lucide/vue';

interface DeviceItem {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    device_name: string;
    ip_address: string | null;
    status: 'approved' | 'pending' | 'revoked';
    is_current: boolean;
    last_used_at: string;
    created_at: string;
}

const props = defineProps<{
    devices: DeviceItem[];
    isAdmin: boolean;
    currentDeviceUuid?: string;
    adminRecoveryKey?: string | null;
}>();

const page = usePage();
const copiedKey = ref(false);

const copyRecoveryKey = () => {
    if (props.adminRecoveryKey) {
        navigator.clipboard.writeText(props.adminRecoveryKey);
        copiedKey.value = true;
        setTimeout(() => (copiedKey.value = false), 2500);
    }
};

const approveDevice = (device: DeviceItem) => {
    const team = (page.props as any).currentTeam?.slug;
    router.post(`/${team}/security/devices/${device.id}/approve`, {}, {
        preserveScroll: true,
    });
};

const revokeDevice = (device: DeviceItem) => {
    if (confirm(`Revoke access for device "${device.device_name}"? Active sessions on this device will be immediately terminated.`)) {
        const team = (page.props as any).currentTeam?.slug;
        router.post(`/${team}/security/devices/${device.id}/revoke`, {}, {
            preserveScroll: true,
        });
    }
};

const deleteDevice = (device: DeviceItem) => {
    if (confirm(`Remove device "${device.device_name}" from trusted device registry?`)) {
        const team = (page.props as any).currentTeam?.slug;
        router.delete(`/${team}/security/devices/${device.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppSidebarLayout :breadcrumbs="[{ title: 'Security', href: '#' }, { title: 'Trusted Devices', href: '#' }]">
        <Head title="Trusted Devices | MR-Pride" />

        <div class="workspace-page">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                        Trusted Devices
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage recognized hardware and workstations authorized for administrative access.
                    </p>
                </div>
            </div>

            <!-- Emergency Admin Recovery Key Card (Admin Only) -->
            <div
                v-if="isAdmin && adminRecoveryKey"
                class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50/90 via-amber-50/50 to-white p-5 shadow-xs"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#4b5320] text-[#F8B803] shadow-xs">
                            <KeyRound class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                Emergency Admin Recovery Key
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 uppercase">
                                    Single-Use
                                </span>
                            </h3>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed max-w-2xl">
                                If you lose access to all your trusted devices or email, use this cryptographic key on the device verification screen to restore access.
                            </p>
                            <p class="mt-2 font-mono text-sm font-bold text-gray-900 tracking-wider bg-white px-3 py-1.5 rounded-lg border border-amber-200 inline-block">
                                {{ adminRecoveryKey }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="copyRecoveryKey"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-xs cursor-pointer self-start sm:self-center"
                    >
                        <Check v-if="copiedKey" class="size-4 text-green-600" />
                        <Copy v-else class="size-4 text-gray-500" />
                        <span>{{ copiedKey ? 'Copied Key!' : 'Copy Key' }}</span>
                    </button>
                </div>
            </div>

            <!-- Devices Table -->
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50/80 border-b border-gray-200 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">Device & Workstation</th>
                                <th v-if="isAdmin" class="px-6 py-4">Account Owner</th>
                                <th class="px-6 py-4">IP Address</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Last Active</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="d in devices" :key="d.id" class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                            <Laptop class="size-4" />
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 flex items-center gap-2">
                                                <span>{{ d.device_name }}</span>
                                                <span
                                                    v-if="d.is_current"
                                                    class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200"
                                                >
                                                    Current Device
                                                </span>
                                            </div>
                                            <span class="text-xs text-gray-400">Registered {{ d.created_at }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td v-if="isAdmin" class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ d.user.name }}</div>
                                    <div class="text-xs text-gray-400">{{ d.user.email }} ({{ d.user.role }})</div>
                                </td>

                                <td class="px-6 py-4 font-mono text-xs text-gray-700">
                                    {{ d.ip_address || '—' }}
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        v-if="d.status === 'approved'"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 border border-green-200"
                                    >
                                        <CheckCircle2 class="size-3.5" /> Approved
                                    </span>
                                    <span
                                        v-else-if="d.status === 'pending'"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 border border-amber-200"
                                    >
                                        <ShieldAlert class="size-3.5" /> Pending Verification
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 border border-red-200"
                                    >
                                        <XCircle class="size-3.5" /> Revoked
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-xs text-gray-500">
                                    {{ d.last_used_at }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="isAdmin && d.status !== 'approved'"
                                            type="button"
                                            @click="approveDevice(d)"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 cursor-pointer"
                                        >
                                            <ShieldCheck class="size-3.5" /> Approve
                                        </button>

                                        <button
                                            v-if="d.status === 'approved'"
                                            type="button"
                                            @click="revokeDevice(d)"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 cursor-pointer"
                                        >
                                            Revoke
                                        </button>

                                        <button
                                            type="button"
                                            @click="deleteDevice(d)"
                                            class="inline-flex items-center justify-center size-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
                                            title="Delete device"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="devices.length === 0">
                                <td :colspan="isAdmin ? 6 : 5" class="px-6 py-12 text-center text-gray-400">
                                    No registered trusted devices found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppSidebarLayout>
</template>
