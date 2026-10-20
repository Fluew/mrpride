<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import {
    Activity,
    Laptop,
    Trash2,
    ShieldAlert,
    LogOut,
} from '@lucide/vue';

interface SessionItem {
    id: string;
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    ip_address: string;
    device_name: string;
    is_current: boolean;
    last_activity: string;
}

const props = defineProps<{
    sessions: SessionItem[];
    isAdmin: boolean;
}>();

const page = usePage();

const revokeSession = (sessionId: string) => {
    if (confirm('Revoke this session? The device will be signed out immediately.')) {
        const team = (page.props as any).currentTeam?.slug;
        router.delete(`/${team}/security/sessions/${sessionId}`, {
            preserveScroll: true,
        });
    }
};

const revokeOtherSessions = () => {
    if (confirm('Terminate all active sessions on other devices? You will remain signed in on this workstation.')) {
        const team = (page.props as any).currentTeam?.slug;
        router.post(`/${team}/security/sessions/revoke-others`, {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppSidebarLayout :breadcrumbs="[{ title: 'Security', href: '#' }, { title: 'Active Sessions', href: '#' }]">
        <Head title="Active Sessions | MR-Pride" />

        <div class="workspace-page">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                        Active Sessions
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Monitor active browser sessions and revoke unauthorized or stale logins.
                    </p>
                </div>

                <button
                    type="button"
                    @click="revokeOtherSessions"
                    class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-100 shadow-xs cursor-pointer transition-colors"
                >
                    <LogOut class="size-4" />
                    Sign Out All Other Sessions
                </button>
            </div>

            <!-- Sessions Table -->
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50/80 border-b border-gray-200 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">Workstation / Device</th>
                                <th v-if="isAdmin" class="px-6 py-4">Account</th>
                                <th class="px-6 py-4">IP Address</th>
                                <th class="px-6 py-4">Last Activity</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in sessions" :key="s.id" class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                            <Laptop class="size-4" />
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 flex items-center gap-2">
                                                <span>{{ s.device_name }}</span>
                                                <span
                                                    v-if="s.is_current"
                                                    class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200"
                                                >
                                                    Current Session
                                                </span>
                                            </div>
                                            <span class="text-xs text-gray-400 font-mono">{{ s.id.substring(0, 16) }}...</span>
                                        </div>
                                    </div>
                                </td>

                                <td v-if="isAdmin" class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ s.user.name }}</div>
                                    <div class="text-xs text-gray-400">{{ s.user.email }} ({{ s.user.role }})</div>
                                </td>

                                <td class="px-6 py-4 font-mono text-xs text-gray-700">
                                    {{ s.ip_address || '—' }}
                                </td>

                                <td class="px-6 py-4 text-xs text-gray-500">
                                    {{ s.last_activity }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <button
                                        v-if="!s.is_current"
                                        type="button"
                                        @click="revokeSession(s.id)"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 cursor-pointer"
                                    >
                                        <Trash2 class="size-3.5" /> Revoke
                                    </button>
                                    <span v-else class="text-xs font-semibold text-gray-400 italic">
                                        Current Device
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="sessions.length === 0">
                                <td :colspan="isAdmin ? 5 : 4" class="px-6 py-12 text-center text-gray-400">
                                    No active sessions recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppSidebarLayout>
</template>
