<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import {
    Users,
    Activity,
    Laptop,
    FileText,
    ShieldAlert,
    Shield,
    ShieldCheck,
    Search,
    Plus,
    Trash2,
    Pencil,
    Check,
    CheckCircle2,
    X,
    Mail,
    Calendar,
    Clock,
    KeyRound,
    RefreshCw,
    AlertTriangle,
    Power,
    UserCheck,
    UserX,
    Copy,
    ExternalLink,
    Filter,
} from '@lucide/vue';

interface UserAccount {
    id: number;
    name: string;
    email: string;
    role: string;
    raw_role: string;
    status: string;
    is_active: boolean;
    must_change_password: boolean;
    password_changed_at?: string;
    last_login_at: string;
    joined_at: string;
    trusted_devices_count: number;
    is_superadmin: boolean;
}

interface ActiveSession {
    id: string;
    masked_id: string;
    user_id: number;
    user_name: string;
    user_email: string;
    user_role: string;
    ip_address: string;
    user_agent: string;
    browser: string;
    os: string;
    is_current: boolean;
    last_activity: string;
    last_activity_exact: string;
    is_expired: boolean;
    status: string;
}

interface TrustedDeviceItem {
    id: number;
    device_uuid: string;
    device_name: string;
    ip_address: string;
    status: string;
    raw_status: string;
    is_current: boolean;
    last_used_at: string;
    created_at: string;
    enrollment_token: string | null;
    enrollment_link: string | null;
    is_enrollment_expired: boolean;
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
}

interface LogItem {
    id: number;
    category: string;
    user_id?: number;
    user_email?: string;
    target_user_id?: number;
    target_user_email?: string;
    event: string;
    action_type?: string;
    description: string;
    outcome: string;
    severity?: string;
    ip_address?: string;
    user_agent?: string;
    metadata?: any;
    created_at: string;
    user?: { id: number; name: string; email: string };
    target_user?: { id: number; name: string; email: string };
}

interface PaginatedLogs {
    data: LogItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    activeTab: 'accounts' | 'sessions' | 'devices' | 'audit' | 'security';
    isSuperAdmin: boolean;
    users?: UserAccount[];
    sessions?: ActiveSession[];
    devices?: TrustedDeviceItem[];
    auditLogs?: PaginatedLogs | null;
    securityLogs?: PaginatedLogs | null;
    adminRecoveryKey?: string | null;
    filters?: {
        search?: string;
        role?: string;
        status?: string;
        event_filter?: string;
        severity?: string;
        date_from?: string;
        date_to?: string;
    };
    roles?: Array<{ value: string; label: string }>;
    temporaryPasswordNotice?: string | null;
    enrollmentNotice?: {
        user_name: string;
        user_email: string;
        link: string;
        expires_at: string;
    } | null;
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || (page.props as any).team?.slug || '');
const currentAuthUserId = computed(() => (page.props as any).auth?.user?.id);

// Tab switching
const switchTab = (tab: string) => {
    router.get(
        `/${teamSlug.value}/users`,
        { tab },
        { preserveState: false, preserveScroll: true }
    );
};

// Filters
const searchQuery = ref(props.filters?.search || '');
const selectedRole = ref(props.filters?.role || 'All Roles');
const selectedStatus = ref(props.filters?.status || 'All Statuses');
const selectedSeverity = ref(props.filters?.severity || 'All Severities');
const selectedEvent = ref(props.filters?.event_filter || 'All Events');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

let filterTimeout: ReturnType<typeof setTimeout> | null = null;
const applyFilter = () => {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(
            `/${teamSlug.value}/users`,
            {
                tab: props.activeTab,
                search: searchQuery.value || undefined,
                role: selectedRole.value !== 'All Roles' ? selectedRole.value : undefined,
                status: selectedStatus.value !== 'All Statuses' ? selectedStatus.value : undefined,
                severity: selectedSeverity.value !== 'All Severities' ? selectedSeverity.value : undefined,
                event_filter: selectedEvent.value !== 'All Events' ? selectedEvent.value : undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
};

// Copy helper
const copiedKey = ref(false);
const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    copiedKey.value = true;
    setTimeout(() => {
        copiedKey.value = false;
    }, 2000);
};

// Modals State
const showAddModal = ref(false);
const showEditModal = ref(false);
const showStatusModal = ref(false);
const showDeleteModal = ref(false);
const showEnrollModal = ref(false);
const showRevokeSessionModal = ref(false);
const showRevokeUserSessionsModal = ref(false);
const showRevokeDeviceModal = ref(false);
const showMetadataModal = ref(false);

const selectedUser = ref<UserAccount | null>(null);
const selectedSession = ref<ActiveSession | null>(null);
const selectedDevice = ref<TrustedDeviceItem | null>(null);
const selectedMetadata = ref<any>(null);

// Forms
const addForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    role: 'staff',
});

const editForm = useForm({
    id: null as number | null,
    name: '',
    role: 'staff',
});

const enrollForm = useForm({
    user_id: '' as string | number,
    device_name: '',
    direct_approve: false,
});

// Modal Triggers
const openAddModal = () => {
    addForm.reset();
    addForm.clearErrors();
    addForm.first_name = '';
    addForm.last_name = '';
    addForm.email = '';
    addForm.role = 'staff';
    showAddModal.value = true;
};

const openEditModal = (user: UserAccount) => {
    selectedUser.value = user;
    editForm.clearErrors();
    editForm.id = user.id;
    editForm.name = user.name;
    editForm.role = user.raw_role;
    showEditModal.value = true;
};

const openStatusModal = (user: UserAccount) => {
    selectedUser.value = user;
    showStatusModal.value = true;
};

const openDeleteModal = (user: UserAccount) => {
    selectedUser.value = user;
    showDeleteModal.value = true;
};

const openEnrollModal = (user?: UserAccount) => {
    enrollForm.reset();
    enrollForm.clearErrors();
    enrollForm.direct_approve = false;
    if (user) {
        enrollForm.user_id = user.id;
        enrollForm.device_name = `${user.name}'s Device`;
    }
    showEnrollModal.value = true;
};

const openRevokeSessionModal = (session: ActiveSession) => {
    selectedSession.value = session;
    showRevokeSessionModal.value = true;
};

const openRevokeUserSessionsModal = (user: UserAccount) => {
    selectedUser.value = user;
    showRevokeUserSessionsModal.value = true;
};

const openRevokeDeviceModal = (device: TrustedDeviceItem) => {
    selectedDevice.value = device;
    showRevokeDeviceModal.value = true;
};

const openMetadataModal = (log: LogItem) => {
    selectedMetadata.value = log.metadata;
    showMetadataModal.value = true;
};

// Action Confirmation Modal State
interface ConfirmActionOptions {
    title: string;
    description: string;
    details?: Array<{ label: string; value: string | number }>;
    confirmText?: string;
    confirmVariant?: 'brand' | 'destructive' | 'secondary';
    action: () => void;
}

const showConfirmModal = ref(false);
const confirmOptions = ref<ConfirmActionOptions>({
    title: 'Confirm Action',
    description: 'Are you sure you want to proceed?',
    details: [],
    confirmText: 'Confirm & Proceed',
    confirmVariant: 'brand',
    action: () => {},
});

const openConfirmModal = (options: ConfirmActionOptions) => {
    confirmOptions.value = {
        confirmText: 'Confirm & Proceed',
        confirmVariant: 'brand',
        ...options,
    };
    showConfirmModal.value = true;
};

const handleConfirmedAction = () => {
    showConfirmModal.value = false;
    confirmOptions.value.action();
};

// Form Submissions & Prompts
const promptCreateUser = () => {
    addForm.clearErrors();
    let hasError = false;
    if (!addForm.first_name.trim()) {
        addForm.setError('first_name', 'First name is required.');
        hasError = true;
    }
    if (!addForm.last_name.trim()) {
        addForm.setError('last_name', 'Last name is required.');
        hasError = true;
    }
    if (!addForm.email.trim()) {
        addForm.setError('email', 'Email address is required.');
        hasError = true;
    }
    if (hasError) return;

    const fn = addForm.first_name.trim();
    const cleanFn = fn.charAt(0).toUpperCase() + fn.slice(1).toLowerCase().replace(/[^a-z0-9]/g, '');
    const initialPassword = `${cleanFn || '(Firstname)'}1234`;
    const formatRole = (r: string) => {
        const map: Record<string, string> = {
            admin: 'Administrator (All Modules)',
            staff_inventory: 'Staff - Inventory',
            staff_quotations: 'Staff - Quotations',
            staff_procurement: 'Staff - Procurement',
            staff_sales: 'Staff - Sales',
            staff_forecast: 'Staff - Demand Forecasting',
            staff_suppliers: 'Staff - Suppliers',
            staff_reports: 'Staff - Reports & Analytics',
            staff: 'Staff - Inventory',
            user: 'Regular Customer',
        };
        return map[r] || r;
    };
    const roleLabel = formatRole(addForm.role);

    openConfirmModal({
        title: 'Confirm New User Account',
        description: `Are you sure you want to create a new user account for "${fn} ${addForm.last_name.trim()}"?`,
        details: [
            { label: 'Full Name', value: `${fn} ${addForm.last_name.trim()}` },
            { label: 'Email Address', value: addForm.email.trim() },
            { label: 'Assigned Role', value: roleLabel },
            { label: 'First-Login Password', value: initialPassword },
        ],
        confirmText: 'Create Account',
        confirmVariant: 'brand',
        action: submitAdd,
    });
};

const submitAdd = () => {
    addForm.post(`/${teamSlug.value}/users`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const promptEditUser = () => {
    editForm.clearErrors();
    if (!editForm.name.trim()) {
        editForm.setError('name', 'Full name is required.');
        return;
    }

    const formatRole = (r: string) => {
        const map: Record<string, string> = {
            admin: 'Administrator (All Modules)',
            staff_inventory: 'Staff - Inventory',
            staff_quotations: 'Staff - Quotations',
            staff_procurement: 'Staff - Procurement',
            staff_sales: 'Staff - Sales',
            staff_forecast: 'Staff - Demand Forecasting',
            staff_suppliers: 'Staff - Suppliers',
            staff_reports: 'Staff - Reports & Analytics',
            staff: 'Staff - Inventory',
            user: 'Regular Customer',
        };
        return map[r] || r;
    };
    const roleLabel = formatRole(editForm.role);

    openConfirmModal({
        title: 'Confirm Account Changes',
        description: `Are you sure you want to save modifications to "${editForm.name}"?`,
        details: [
            { label: 'Account Name', value: editForm.name },
            { label: 'Assigned Role', value: roleLabel },
            { label: 'Session Policy', value: 'Active sessions will be refreshed upon role update' },
        ],
        confirmText: 'Save Changes',
        confirmVariant: 'brand',
        action: submitEdit,
    });
};

const submitEdit = () => {
    if (!editForm.id) return;
    editForm.put(`/${teamSlug.value}/users/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
        },
    });
};

const confirmStatusToggle = () => {
    if (!selectedUser.value) return;
    router.patch(`/${teamSlug.value}/users/${selectedUser.value.id}/status`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false;
            selectedUser.value = null;
        },
    });
};

const confirmDelete = () => {
    if (!selectedUser.value) return;
    router.delete(`/${teamSlug.value}/users/${selectedUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedUser.value = null;
        },
    });
};

const promptEnrollDevice = () => {
    enrollForm.clearErrors();
    if (!enrollForm.user_id) {
        enrollForm.setError('user_id', 'Please select a user account.');
        return;
    }

    const targetUser = props.users.find(u => u.id === Number(enrollForm.user_id));
    const deviceLabel = enrollForm.device_name.trim() || `${targetUser?.name || 'Member'}'s Device`;

    openConfirmModal({
        title: 'Confirm Device Enrollment',
        description: `Are you sure you want to enroll this device for "${targetUser?.name || 'the selected account'}"?`,
        details: [
            { label: 'Account Holder', value: targetUser?.name || 'Selected User' },
            { label: 'Device Identifier', value: deviceLabel },
            { label: 'Enrollment Mode', value: enrollForm.direct_approve ? 'Immediate Direct Approval' : 'One-Time Invitation Link (24 Hours)' },
        ],
        confirmText: 'Authorize & Enroll',
        confirmVariant: 'brand',
        action: submitEnroll,
    });
};

const submitEnroll = () => {
    enrollForm.post(`/${teamSlug.value}/users/devices/enroll`, {
        preserveScroll: true,
        onSuccess: () => {
            showEnrollModal.value = false;
            enrollForm.reset();
        },
    });
};

const confirmRevokeSession = () => {
    if (!selectedSession.value) return;
    router.delete(`/${teamSlug.value}/users/sessions/${selectedSession.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showRevokeSessionModal.value = false;
            selectedSession.value = null;
        },
    });
};

const confirmRevokeUserSessions = () => {
    if (!selectedUser.value) return;
    router.post(`/${teamSlug.value}/users/${selectedUser.value.id}/sessions/revoke`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showRevokeUserSessionsModal.value = false;
            selectedUser.value = null;
        },
    });
};

const confirmRevokeDevice = () => {
    if (!selectedDevice.value) return;
    router.post(`/${teamSlug.value}/users/devices/${selectedDevice.value.id}/revoke`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showRevokeDeviceModal.value = false;
            selectedDevice.value = null;
        },
    });
};

const promptApproveDevice = (device: TrustedDeviceItem) => {
    openConfirmModal({
        title: 'Confirm Device Approval',
        description: `Authorize and approve access for "${device.device_name}"?`,
        details: [
            { label: 'Device Name', value: device.device_name },
            { label: 'Account', value: `${device.user.name} (${device.user.email})` },
            { label: 'IP Address', value: device.ip_address || 'Not Recorded' },
            { label: 'Hardware UUID', value: device.device_uuid },
        ],
        confirmText: 'Approve Device',
        confirmVariant: 'brand',
        action: () => approveDevice(device),
    });
};

const approveDevice = (device: TrustedDeviceItem) => {
    router.post(`/${teamSlug.value}/users/devices/${device.id}/approve`, {}, {
        preserveScroll: true,
    });
};

const promptDeleteDevice = (device: TrustedDeviceItem) => {
    openConfirmModal({
        title: 'Remove Device Record',
        description: `Permanently delete the trusted device entry for "${device.device_name}"?`,
        details: [
            { label: 'Device Name', value: device.device_name },
            { label: 'Account', value: device.user.name },
            { label: 'Hardware UUID', value: device.device_uuid },
        ],
        confirmText: 'Delete Record',
        confirmVariant: 'destructive',
        action: () => deleteDevice(device),
    });
};

const deleteDevice = (device: TrustedDeviceItem) => {
    router.delete(`/${teamSlug.value}/users/devices/${device.id}`, {
        preserveScroll: true,
    });
};

const promptRegenerateRecoveryKey = () => {
    openConfirmModal({
        title: 'Regenerate Emergency Admin Recovery Key',
        description: 'Are you sure you want to generate a new emergency admin recovery key? Your current key will become invalid immediately.',
        details: [
            { label: 'Security Warning', value: 'Prior recovery keys cannot be used after regeneration' },
        ],
        confirmText: 'Regenerate Key',
        confirmVariant: 'brand',
        action: regenerateRecoveryKey,
    });
};

const regenerateRecoveryKey = () => {
    router.post(`/${teamSlug.value}/users/recovery-key/regenerate`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="User Management & Security Administration" />

    <div class="workspace-page">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">User Management</h1>
                    <span class="bg-amber-100 text-amber-800 text-[11px] font-bold px-2.5 py-0.5 rounded-full border border-amber-200 uppercase tracking-wide">
                        Administrator
                    </span>
                </div>
                <p class="text-gray-500 mt-1">
                    Centralized account governance, active sessions, trusted devices, audit trails, and security logs.
                </p>
            </div>

            <!-- Top Actions -->
            <div class="flex items-center gap-2">
                <SpecularButton
                    v-if="activeTab === 'accounts'"
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="openAddModal"
                >
                    <Plus class="w-4 h-4 mr-1.5 inline" />
                    Add Account
                </SpecularButton>

                <SpecularButton
                    v-if="activeTab === 'devices'"
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="openEnrollModal()"
                >
                    <Plus class="w-4 h-4 mr-1.5 inline" />
                    Enroll Device
                </SpecularButton>
            </div>
        </div>

        <!-- Banner / Notices -->
        <div
            v-if="temporaryPasswordNotice"
            class="mb-4 rounded-2xl border border-amber-300 bg-amber-50/90 p-4 shadow-sm flex items-start gap-3"
        >
            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#4b5320] text-[#F8B803]">
                <Shield class="size-4" />
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Temporary Credential Generated</h4>
                <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ temporaryPasswordNotice }}</p>
                <p class="text-xs text-gray-500 mt-1">Please securely convey this credential to the user. They must change it upon their initial login.</p>
            </div>
        </div>

        <div
            v-if="enrollmentNotice"
            class="mb-4 rounded-2xl border border-emerald-300 bg-emerald-50/90 p-4 shadow-sm flex items-start justify-between gap-3"
        >
            <div class="flex items-start gap-3">
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white">
                    <Laptop class="size-4" />
                </div>
                <div>
                    <h4 class="text-xs font-bold text-emerald-950 uppercase tracking-wider">Single-Use Device Enrollment Invitation</h4>
                    <p class="text-sm text-emerald-900 mt-0.5 font-medium">
                        Send this link to <strong class="text-emerald-950">{{ enrollmentNotice.user_name }}</strong> ({{ enrollmentNotice.user_email }}). Must be opened on their authorized device before {{ enrollmentNotice.expires_at }}.
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <input
                            type="text"
                            readonly
                            :value="enrollmentNotice.link"
                            class="bg-white border border-emerald-300 rounded-lg px-2.5 py-1 text-xs text-gray-700 w-96 max-w-full font-mono select-all"
                        />
                        <button
                            @click="copyToClipboard(enrollmentNotice.link)"
                            class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors"
                        >
                            <Copy class="w-3.5 h-3.5" />
                            {{ copiedKey ? 'Copied!' : 'Copy Link' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs (Admin Full Access) -->
        <div class="border-b border-gray-200 mb-5">
            <nav class="flex space-x-2 -mb-px overflow-x-auto">
                <button
                    @click="switchTab('accounts')"
                    :class="[
                        activeTab === 'accounts'
                            ? 'border-[#F8B803] text-[#92400e] bg-amber-50/50 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium',
                        'whitespace-nowrap py-3 px-4 border-b-2 text-sm flex items-center gap-2 rounded-t-xl transition-all'
                    ]"
                >
                    <Users class="w-4 h-4" />
                    User Accounts
                </button>

                <button
                    @click="switchTab('sessions')"
                    :class="[
                        activeTab === 'sessions'
                            ? 'border-[#F8B803] text-[#92400e] bg-amber-50/50 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium',
                        'whitespace-nowrap py-3 px-4 border-b-2 text-sm flex items-center gap-2 rounded-t-xl transition-all'
                    ]"
                >
                    <Activity class="w-4 h-4" />
                    Active Sessions
                </button>

                <button
                    @click="switchTab('devices')"
                    :class="[
                        activeTab === 'devices'
                            ? 'border-[#F8B803] text-[#92400e] bg-amber-50/50 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium',
                        'whitespace-nowrap py-3 px-4 border-b-2 text-sm flex items-center gap-2 rounded-t-xl transition-all'
                    ]"
                >
                    <Laptop class="w-4 h-4" />
                    Trusted Devices
                </button>

                <button
                    @click="switchTab('audit')"
                    :class="[
                        activeTab === 'audit'
                            ? 'border-[#F8B803] text-[#92400e] bg-amber-50/50 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium',
                        'whitespace-nowrap py-3 px-4 border-b-2 text-sm flex items-center gap-2 rounded-t-xl transition-all'
                    ]"
                >
                    <FileText class="w-4 h-4" />
                    Audit Trail
                </button>

                <button
                    @click="switchTab('security')"
                    :class="[
                        activeTab === 'security'
                            ? 'border-[#F8B803] text-[#92400e] bg-amber-50/50 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium',
                        'whitespace-nowrap py-3 px-4 border-b-2 text-sm flex items-center gap-2 rounded-t-xl transition-all'
                    ]"
                >
                    <ShieldAlert class="w-4 h-4" />
                    Security Logs
                </button>
            </nav>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: USER ACCOUNTS                       -->
        <!-- ========================================== -->
        <div v-if="activeTab === 'accounts'">
            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <div class="relative flex-1 sm:max-w-md">
                    <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @input="applyFilter"
                        type="text"
                        placeholder="Search users by name or email..."
                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm"
                    />
                </div>
                <select
                    v-model="selectedRole"
                    @change="applyFilter"
                    class="bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm min-w-[150px]"
                >
                    <option value="All Roles">All Roles</option>
                    <option value="superadmin">Owner / Superadmin</option>
                    <option value="admin">Administrator</option>
                    <option value="staff">Staff</option>
                    <option value="user">User</option>
                </select>
                <select
                    v-model="selectedStatus"
                    @change="applyFilter"
                    class="bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm min-w-[140px]"
                >
                    <option value="All Statuses">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <!-- Table -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                                <th class="pb-3 font-semibold">Account / User</th>
                                <th class="pb-3 font-semibold">Email</th>
                                <th class="pb-3 font-semibold">Role</th>
                                <th class="pb-3 font-semibold">Status</th>
                                <th class="pb-3 font-semibold">Last Login</th>
                                <th class="pb-3 font-semibold text-center">Devices</th>
                                <th class="pb-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <tr v-if="!users?.length">
                                <td colspan="7" class="py-10 text-center text-gray-500">
                                    No accounts found matching your query.
                                </td>
                            </tr>
                            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-4 text-gray-900 font-semibold flex items-center gap-3">
                                    <div class="size-9 rounded-full bg-[#4b5320] text-[#F8B803] flex items-center justify-center font-bold text-xs shadow-xs">
                                        {{ user.name.slice(0, 2).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span>{{ user.name }}</span>
                                            <span v-if="user.id === currentAuthUserId" class="text-[10px] bg-gray-100 text-gray-700 font-medium px-2 py-0.5 rounded-full">You</span>
                                        </div>
                                        <div v-if="user.must_change_password" class="text-[11px] text-amber-600 font-medium flex items-center gap-1 mt-0.5">
                                            <AlertTriangle class="size-3" /> Password change required
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 text-gray-600">
                                    <div class="flex items-center gap-1.5">
                                        <Mail class="size-3.5 text-gray-400" />
                                        {{ user.email }}
                                    </div>
                                </td>
                                <td class="py-4">
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-full text-xs font-semibold border inline-flex items-center gap-1',
                                            user.is_superadmin ? 'bg-purple-50 text-purple-700 border-purple-200' :
                                            user.raw_role === 'admin' ? 'bg-[#F8B803]/15 text-[#92400e] border-[#F8B803]/30' :
                                            user.raw_role === 'staff' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                                            'bg-gray-100 text-gray-600 border-gray-200'
                                        ]"
                                    >
                                        <Shield class="size-3" />
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="py-4">
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-full text-xs font-semibold border inline-flex items-center gap-1',
                                            user.is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'
                                        ]"
                                    >
                                        <span :class="user.is_active ? 'bg-green-500' : 'bg-red-500'" class="size-1.5 rounded-full"></span>
                                        {{ user.status }}
                                    </span>
                                </td>
                                <td class="py-4 text-gray-500 text-xs">
                                    <div class="flex items-center gap-1">
                                        <Clock class="size-3 text-gray-400" />
                                        {{ user.last_login_at }}
                                    </div>
                                </td>
                                <td class="py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-xs font-medium bg-gray-100 text-gray-700 px-2 py-0.5 rounded-md">
                                        <Laptop class="size-3" />
                                        {{ user.trusted_devices_count }}
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <!-- Superadmin Enroll Device shortcut -->
                                        <button
                                            v-if="isSuperAdmin && (user.raw_role === 'admin' || user.raw_role === 'staff')"
                                            @click="openEnrollModal(user)"
                                            title="Enroll Trusted Device"
                                            class="p-1.5 text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors"
                                        >
                                            <Laptop class="size-4" />
                                        </button>

                                        <!-- Superadmin Revoke Sessions shortcut -->
                                        <button
                                            v-if="isSuperAdmin && user.id !== currentAuthUserId"
                                            @click="openRevokeUserSessionsModal(user)"
                                            title="Revoke All Sessions"
                                            class="p-1.5 text-gray-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-colors"
                                        >
                                            <Power class="size-4" />
                                        </button>

                                        <!-- Edit Role -->
                                        <button
                                            v-if="!user.is_superadmin || isSuperAdmin"
                                            @click="openEditModal(user)"
                                            class="text-xs font-semibold text-gray-700 hover:text-gray-900 px-2.5 py-1 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                        >
                                            Edit
                                        </button>

                                        <!-- Toggle Status (Activate / Deactivate) -->
                                        <button
                                            v-if="isSuperAdmin && user.id !== currentAuthUserId"
                                            @click="openStatusModal(user)"
                                            :class="[
                                                'text-xs font-semibold px-2.5 py-1 border rounded-lg transition-colors',
                                                user.is_active ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                                            ]"
                                        >
                                            {{ user.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>

                                        <!-- Remove Account -->
                                        <button
                                            v-if="user.id !== currentAuthUserId && (!user.is_superadmin || isSuperAdmin)"
                                            @click="openDeleteModal(user)"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Delete Account"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: ACTIVE SESSIONS (Superadmin only)   -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'sessions' && isSuperAdmin">
            <!-- Filter -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                <div class="relative flex-1 sm:max-w-md">
                    <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @input="applyFilter"
                        type="text"
                        placeholder="Filter sessions by user or IP address..."
                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm"
                    />
                </div>
                <div class="text-xs text-gray-500">
                    Sessions are rotated automatically and expire after 120 minutes of inactivity.
                </div>
            </div>

            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                                <th class="pb-3 font-semibold">Account / User</th>
                                <th class="pb-3 font-semibold">Role</th>
                                <th class="pb-3 font-semibold">Device / Browser</th>
                                <th class="pb-3 font-semibold">IP Address</th>
                                <th class="pb-3 font-semibold">Last Activity</th>
                                <th class="pb-3 font-semibold">Status</th>
                                <th class="pb-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <tr v-if="!sessions?.length">
                                <td colspan="7" class="py-10 text-center text-gray-500">
                                    No active sessions recorded.
                                </td>
                            </tr>
                            <tr v-for="s in sessions" :key="s.id" class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-4">
                                    <div class="font-semibold text-gray-900 flex items-center gap-2">
                                        <span>{{ s.user_name }}</span>
                                        <span v-if="s.is_current" class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full border border-emerald-200">
                                            Current Session
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500">{{ s.user_email }}</div>
                                </td>
                                <td class="py-4">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-gray-100 text-gray-700">
                                        {{ s.user_role }}
                                    </span>
                                </td>
                                <td class="py-4">
                                    <div class="text-sm font-medium text-gray-800">{{ s.browser }}</div>
                                    <div class="text-xs text-gray-500">{{ s.os }}</div>
                                </td>
                                <td class="py-4 font-mono text-xs text-gray-600">
                                    {{ s.ip_address }}
                                </td>
                                <td class="py-4 text-xs text-gray-600" :title="s.last_activity_exact">
                                    <div class="flex items-center gap-1">
                                        <Clock class="size-3 text-gray-400" />
                                        {{ s.last_activity }}
                                    </div>
                                </td>
                                <td class="py-4">
                                    <span :class="s.status === 'Active' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-100 text-gray-500 border-gray-200'" class="px-2 py-0.5 rounded-full text-xs font-semibold border">
                                        {{ s.status }}
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <button
                                        v-if="!s.is_current"
                                        @click="openRevokeSessionModal(s)"
                                        class="text-xs font-semibold text-red-600 hover:text-red-800 px-3 py-1 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition-colors"
                                    >
                                        Revoke Session
                                    </button>
                                    <span v-else class="text-xs text-gray-400 italic">This Device</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: TRUSTED DEVICES (1 Account, 1 Device Policy) -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'devices'">
            <!-- Policy Banner: 1 Account, 1 Device -->
            <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50/80 p-4 shadow-xs flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-xs">
                        <ShieldCheck class="size-5" />
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-blue-950 uppercase tracking-wider">1 Account, 1 Device Policy Enforced</h4>
                        <p class="text-xs text-blue-800 mt-0.5">
                            Each administrator and staff account is strictly limited to <strong>1 authorized device</strong>. Enrolling or approving a new device automatically replaces and revokes any prior device.
                        </p>
                    </div>
                </div>
                <span class="bg-blue-100 text-blue-800 text-[11px] font-bold px-2.5 py-1 rounded-full border border-blue-300 uppercase tracking-wide shrink-0">
                    Active Policy
                </span>
            </div>

            <!-- Top Card: Emergency Recovery Key -->
            <div class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <KeyRound class="size-4 text-[#F8B803]" />
                        Emergency Admin Device Recovery Key
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        If an administrator is locked out or loses access to their trusted device, this key allows emergency verification bypass.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <code class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono font-bold text-gray-800">
                        {{ adminRecoveryKey || 'RECOVER-XXXX-XXXX-XXXX' }}
                    </code>
                    <button
                        v-if="adminRecoveryKey"
                        @click="copyToClipboard(adminRecoveryKey)"
                        class="p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                        title="Copy Key"
                    >
                        <Copy class="size-4" />
                    </button>
                    <button
                        @click="promptRegenerateRecoveryKey"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        <RefreshCw class="size-3.5" />
                        Regenerate
                    </button>
                </div>
            </div>

            <!-- Filters & Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                <div class="relative flex-1 sm:max-w-md">
                    <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @input="applyFilter"
                        type="text"
                        placeholder="Search devices by name, user, or IP..."
                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm"
                    />
                </div>
                <SpecularButton
                    variant="brand"
                    size="sm"
                    :radius="8"
                    @click="openEnrollModal()"
                >
                    <Plus class="w-4 h-4 mr-1 inline" />
                    Enroll New Device
                </SpecularButton>
            </div>

            <!-- Devices Table -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                                <th class="pb-3 font-semibold">Device Label</th>
                                <th class="pb-3 font-semibold">Account Holder</th>
                                <th class="pb-3 font-semibold">IP Address</th>
                                <th class="pb-3 font-semibold">Status</th>
                                <th class="pb-3 font-semibold">Registered</th>
                                <th class="pb-3 font-semibold">Last Used</th>
                                <th class="pb-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <tr v-if="!devices?.length">
                                <td colspan="7" class="py-10 text-center text-gray-500">
                                    No registered or enrolled devices found.
                                </td>
                            </tr>
                            <tr v-for="d in devices" :key="d.id" class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-4 font-semibold text-gray-900">
                                    <div class="flex items-center gap-2">
                                        <Laptop class="size-4 text-gray-500" />
                                        <span>{{ d.device_name }}</span>
                                        <span v-if="d.is_current" class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full border border-emerald-200">
                                            Current Device
                                        </span>
                                    </div>
                                    <div class="text-[11px] font-mono text-gray-400 mt-0.5">{{ d.device_uuid }}</div>
                                </td>
                                <td class="py-4">
                                    <div class="font-medium text-gray-900">{{ d.user.name }}</div>
                                    <div class="text-xs text-gray-500">{{ d.user.email }} ({{ d.user.role }})</div>
                                </td>
                                <td class="py-4 font-mono text-xs text-gray-600">
                                    {{ d.ip_address || '—' }}
                                </td>
                                <td class="py-4">
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-full text-xs font-semibold border inline-flex items-center gap-1',
                                            d.raw_status === 'approved' ? 'bg-green-50 text-green-700 border-green-200' :
                                            d.raw_status === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                            'bg-red-50 text-red-700 border-red-200'
                                        ]"
                                    >
                                        {{ d.status }}
                                    </span>
                                    <div v-if="d.enrollment_link && !d.is_enrollment_expired" class="mt-1">
                                        <button
                                            @click="copyToClipboard(d.enrollment_link)"
                                            class="text-[10px] text-emerald-700 hover:underline inline-flex items-center gap-1 font-semibold"
                                        >
                                            <Copy class="size-3" /> Copy Invitation Link
                                        </button>
                                    </div>
                                </td>
                                <td class="py-4 text-xs text-gray-500">{{ d.created_at }}</td>
                                <td class="py-4 text-xs text-gray-500">{{ d.last_used_at }}</td>
                                <td class="py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <!-- Approve pending -->
                                        <button
                                            v-if="d.raw_status === 'pending'"
                                            @click="promptApproveDevice(d)"
                                            class="text-xs font-semibold text-emerald-700 hover:bg-emerald-50 px-2.5 py-1 border border-emerald-200 rounded-lg transition-colors"
                                        >
                                            Approve
                                        </button>

                                        <!-- Revoke device -->
                                        <button
                                            v-if="d.raw_status !== 'revoked'"
                                            @click="openRevokeDeviceModal(d)"
                                            class="text-xs font-semibold text-red-600 hover:bg-red-50 px-2.5 py-1 border border-red-200 rounded-lg transition-colors"
                                        >
                                            Revoke
                                        </button>

                                        <!-- Delete record -->
                                        <button
                                            @click="promptDeleteDevice(d)"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Delete Device"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: AUDIT TRAIL (Admin accessible)      -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'audit'">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <div class="relative flex-1">
                    <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @input="applyFilter"
                        type="text"
                        placeholder="Search audit trail by description, admin, or target user..."
                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm"
                    />
                </div>
                <input
                    v-model="dateFrom"
                    @change="applyFilter"
                    type="date"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-700 shadow-sm"
                    title="From Date"
                />
                <input
                    v-model="dateTo"
                    @change="applyFilter"
                    type="date"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-700 shadow-sm"
                    title="To Date"
                />
            </div>

            <!-- Table -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                                <th class="pb-3 font-semibold">Timestamp</th>
                                <th class="pb-3 font-semibold">Acting Account</th>
                                <th class="pb-3 font-semibold">Action / Event</th>
                                <th class="pb-3 font-semibold">Affected Resource</th>
                                <th class="pb-3 font-semibold">Outcome</th>
                                <th class="pb-3 font-semibold">Details</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <tr v-if="!auditLogs?.data?.length">
                                <td colspan="6" class="py-10 text-center text-gray-500">
                                    No administrative audit trail records found.
                                </td>
                            </tr>
                            <tr v-for="log in auditLogs?.data" :key="log.id" class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-4 text-xs font-mono text-gray-500 whitespace-nowrap">
                                    {{ new Date(log.created_at).toLocaleString() }}
                                </td>
                                <td class="py-4 font-semibold text-gray-900 text-xs">
                                    {{ log.user_email || 'System' }}
                                </td>
                                <td class="py-4">
                                    <span class="font-mono text-xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-800 rounded-md">
                                        {{ log.action_type || log.event }}
                                    </span>
                                </td>
                                <td class="py-4 text-xs text-gray-600">
                                    {{ log.target_user_email || log.description }}
                                </td>
                                <td class="py-4">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-xs font-semibold border',
                                            log.outcome === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'
                                        ]"
                                    >
                                        {{ log.outcome }}
                                    </span>
                                </td>
                                <td class="py-4 text-xs">
                                    <button
                                        v-if="log.metadata"
                                        @click="openMetadataModal(log)"
                                        class="text-blue-600 hover:underline font-semibold"
                                    >
                                        View Data
                                    </button>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="auditLogs && auditLogs.last_page > 1" class="flex justify-between items-center mt-5 pt-4 border-t border-gray-100">
                    <span class="text-xs text-gray-500">Page {{ auditLogs.current_page }} of {{ auditLogs.last_page }}</span>
                    <div class="flex gap-2">
                        <Link
                            v-if="auditLogs.prev_page_url"
                            :href="auditLogs.prev_page_url"
                            class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="auditLogs.next_page_url"
                            :href="auditLogs.next_page_url"
                            class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 5: SECURITY LOGS (Admin accessible)    -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'security'">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <div class="relative flex-1">
                    <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @input="applyFilter"
                        type="text"
                        placeholder="Search security events, IP address, or account..."
                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm"
                    />
                </div>
                <select
                    v-model="selectedSeverity"
                    @change="applyFilter"
                    class="bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm min-w-[140px]"
                >
                    <option value="All Severities">All Severities</option>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="danger">Danger</option>
                </select>
                <input
                    v-model="dateFrom"
                    @change="applyFilter"
                    type="date"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-700 shadow-sm"
                    title="From Date"
                />
                <input
                    v-model="dateTo"
                    @change="applyFilter"
                    type="date"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-700 shadow-sm"
                    title="To Date"
                />
            </div>

            <!-- Table -->
            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                                <th class="pb-3 font-semibold">Timestamp</th>
                                <th class="pb-3 font-semibold">Severity</th>
                                <th class="pb-3 font-semibold">Event</th>
                                <th class="pb-3 font-semibold">Account</th>
                                <th class="pb-3 font-semibold">IP Address</th>
                                <th class="pb-3 font-semibold">Description</th>
                                <th class="pb-3 font-semibold">Outcome</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <tr v-if="!securityLogs?.data?.length">
                                <td colspan="7" class="py-10 text-center text-gray-500">
                                    No security incidents or access-control events recorded.
                                </td>
                            </tr>
                            <tr v-for="log in securityLogs?.data" :key="log.id" class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-4 text-xs font-mono text-gray-500 whitespace-nowrap">
                                    {{ new Date(log.created_at).toLocaleString() }}
                                </td>
                                <td class="py-4">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[11px] font-bold border uppercase',
                                            log.severity === 'danger' ? 'bg-red-50 text-red-700 border-red-200' :
                                            log.severity === 'warning' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                            'bg-blue-50 text-blue-700 border-blue-200'
                                        ]"
                                    >
                                        {{ log.severity || 'info' }}
                                    </span>
                                </td>
                                <td class="py-4 font-mono text-xs font-semibold text-gray-800">
                                    {{ log.event }}
                                </td>
                                <td class="py-4 text-xs font-medium text-gray-700">
                                    {{ log.user_email || 'Unauthenticated' }}
                                </td>
                                <td class="py-4 font-mono text-xs text-gray-500">
                                    {{ log.ip_address || '—' }}
                                </td>
                                <td class="py-4 text-xs text-gray-600 max-w-md truncate" :title="log.description">
                                    {{ log.description }}
                                </td>
                                <td class="py-4">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-xs font-semibold border',
                                            log.outcome === 'success' ? 'bg-green-50 text-green-700 border-green-200' :
                                            log.outcome === 'blocked' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                            'bg-red-50 text-red-700 border-red-200'
                                        ]"
                                    >
                                        {{ log.outcome }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="securityLogs && securityLogs.last_page > 1" class="flex justify-between items-center mt-5 pt-4 border-t border-gray-100">
                    <span class="text-xs text-gray-500">Page {{ securityLogs.current_page }} of {{ securityLogs.last_page }}</span>
                    <div class="flex gap-2">
                        <Link
                            v-if="securityLogs.prev_page_url"
                            :href="securityLogs.prev_page_url"
                            class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="securityLogs.next_page_url"
                            :href="securityLogs.next_page_url"
                            class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODALS                                     -->
    <!-- ========================================== -->

    <!-- Add Account Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900">Create User Account</h3>
                <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <form @submit.prevent="promptCreateUser" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="relative">
                        <input
                            id="add-first-name"
                            v-model="addForm.first_name"
                            type="text"
                            required
                            placeholder=" "
                            class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                            :class="addForm.errors.first_name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                        />
                        <label
                            for="add-first-name"
                            class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                            :class="addForm.first_name ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                        >
                            First Name
                        </label>
                        <span v-if="addForm.errors.first_name" class="text-xs text-red-500 mt-1 block">{{ addForm.errors.first_name }}</span>
                    </div>
                    <div class="relative">
                        <input
                            id="add-last-name"
                            v-model="addForm.last_name"
                            type="text"
                            required
                            placeholder=" "
                            class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                            :class="addForm.errors.last_name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                        />
                        <label
                            for="add-last-name"
                            class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                            :class="addForm.last_name ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                        >
                            Last Name
                        </label>
                        <span v-if="addForm.errors.last_name" class="text-xs text-red-500 mt-1 block">{{ addForm.errors.last_name }}</span>
                    </div>
                </div>

                <div class="relative">
                    <input
                        id="add-email"
                        v-model="addForm.email"
                        type="email"
                        required
                        placeholder=" "
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                        :class="addForm.errors.email ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                    />
                    <label
                        for="add-email"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                        :class="addForm.email ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                    >
                        Email Address
                    </label>
                    <span v-if="addForm.errors.email" class="text-xs text-red-500 mt-1 block">{{ addForm.errors.email }}</span>
                </div>

                <!-- Initial Password Notice: (Firstname)1234 -->
                <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs text-amber-900 flex items-start gap-2.5">
                    <KeyRound class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold text-amber-950">Initial Password on First Login</p>
                        <p class="text-amber-800 mt-0.5 text-[11px] leading-relaxed">
                            The initial login password is automatically generated as
                            <strong class="font-bold text-amber-950 font-mono underline decoration-amber-400">
                                {{ addForm.first_name ? addForm.first_name.charAt(0).toUpperCase() + addForm.first_name.slice(1).toLowerCase().replace(/[^a-z0-9]/g, '') : '(Firstname)' }}1234
                            </strong>.
                            The user will be required to change their password immediately upon their first login.
                        </p>
                    </div>
                </div>

                <div class="relative">
                    <select
                        id="add-role"
                        v-model="addForm.role"
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                    >
                        <option value="admin">Administrator (All Modules)</option>
                        <option value="staff_inventory">Staff - Inventory</option>
                        <option value="staff_quotations">Staff - Quotations</option>
                        <option value="staff_procurement">Staff - Procurement</option>
                        <option value="staff_sales">Staff - Sales</option>
                        <option value="staff_forecast">Staff - Demand Forecasting</option>
                        <option value="staff_suppliers">Staff - Suppliers</option>
                        <option value="staff_reports">Staff - Reports & Analytics</option>
                        <option value="user">Regular Customer</option>
                    </select>
                    <label
                        for="add-role"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]"
                    >
                        Assigned Role
                    </label>
                    <span v-if="addForm.errors.role" class="text-xs text-red-500 mt-1 block">{{ addForm.errors.role }}</span>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-2 border-t border-gray-100">
                    <SpecularButton type="button" variant="secondary" size="md" :radius="10" @click="showAddModal = false">
                        Cancel
                    </SpecularButton>
                    <SpecularButton type="submit" variant="brand" size="md" :radius="10" :disabled="addForm.processing">
                        {{ addForm.processing ? 'Creating...' : 'Create Account' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900">Edit Member Role & Account</h3>
                <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <form @submit.prevent="promptEditUser" class="space-y-4">
                <div class="relative">
                    <input
                        id="edit-name"
                        v-model="editForm.name"
                        type="text"
                        required
                        placeholder=" "
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                        :class="editForm.errors.name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                    />
                    <label
                        for="edit-name"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                        :class="editForm.name ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                    >
                        Full Name
                    </label>
                    <span v-if="editForm.errors.name" class="text-xs text-red-500 mt-1 block">{{ editForm.errors.name }}</span>
                </div>
                <div class="relative">
                    <select
                        id="edit-role"
                        v-model="editForm.role"
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                    >
                        <option value="admin">Administrator (All Modules)</option>
                        <option value="staff_inventory">Staff - Inventory</option>
                        <option value="staff_quotations">Staff - Quotations</option>
                        <option value="staff_procurement">Staff - Procurement</option>
                        <option value="staff_sales">Staff - Sales</option>
                        <option value="staff_forecast">Staff - Demand Forecasting</option>
                        <option value="staff_suppliers">Staff - Suppliers</option>
                        <option value="staff_reports">Staff - Reports & Analytics</option>
                        <option value="user">Regular Customer</option>
                    </select>
                    <label
                        for="edit-role"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]"
                    >
                        Role
                    </label>
                </div>
                <p class="text-[11px] text-gray-500 mt-1">
                    Changing a member's role invalidates their active sessions to enforce permissions immediately.
                </p>
                <span v-if="editForm.errors.role" class="text-xs text-red-500 mt-1 block">{{ editForm.errors.role }}</span>

                <div class="flex justify-end gap-3 mt-6 pt-2 border-t border-gray-100">
                    <SpecularButton type="button" variant="secondary" size="md" :radius="10" @click="showEditModal = false">
                        Cancel
                    </SpecularButton>
                    <SpecularButton type="submit" variant="brand" size="md" :radius="10" :disabled="editForm.processing">
                        {{ editForm.processing ? 'Saving...' : 'Update Account' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Status Confirmation Modal -->
    <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">
                {{ selectedUser?.is_active ? 'Deactivate Account' : 'Activate Account' }}
            </h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to {{ selectedUser?.is_active ? 'deactivate' : 'activate' }}
                <span class="font-semibold text-gray-900">{{ selectedUser?.name }}</span>?
                <span v-if="selectedUser?.is_active" class="block mt-2 text-amber-700 font-medium">
                    All current active sessions will be revoked immediately and they will be blocked from logging in.
                </span>
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton variant="secondary" size="md" :radius="10" @click="showStatusModal = false">
                    Cancel
                </SpecularButton>
                <SpecularButton
                    :variant="selectedUser?.is_active ? 'destructive' : 'brand'"
                    size="md"
                    :radius="10"
                    @click="confirmStatusToggle"
                >
                    {{ selectedUser?.is_active ? 'Deactivate Account' : 'Activate Account' }}
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Account</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to delete <span class="font-semibold text-gray-900">{{ selectedUser?.name }}</span>?
                All active sessions and registered trusted devices will be revoked immediately.
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton variant="secondary" size="md" :radius="10" @click="showDeleteModal = false">
                    Cancel
                </SpecularButton>
                <SpecularButton variant="destructive" size="md" :radius="10" @click="confirmDelete">
                    Delete Account
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- Enroll Device Modal -->
    <div v-if="showEnrollModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900">Initiate Device Enrollment</h3>
                <button @click="showEnrollModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <form @submit.prevent="promptEnrollDevice" class="space-y-4">
                <div class="relative">
                    <select
                        id="enroll-user"
                        v-model="enrollForm.user_id"
                        required
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                    >
                        <option value="" disabled>Choose account...</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.email }}) — {{ u.role }}
                        </option>
                    </select>
                    <label
                        for="enroll-user"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]"
                    >
                        Select Admin or Staff Account
                    </label>
                    <span v-if="enrollForm.errors.user_id" class="text-xs text-red-500 mt-1 block">{{ enrollForm.errors.user_id }}</span>
                </div>
                <div class="relative">
                    <input
                        id="enroll-device-name"
                        v-model="enrollForm.device_name"
                        type="text"
                        placeholder=" "
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                    />
                    <label
                        for="enroll-device-name"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                        :class="enrollForm.device_name ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                    >
                        Device Label / Description
                    </label>
                </div>

                <label class="flex items-start gap-2.5 text-xs text-gray-700 cursor-pointer pt-1">
                    <input v-model="enrollForm.direct_approve" type="checkbox" class="mt-0.5 rounded border-gray-300 text-[#4b5320] focus:ring-[#4b5320]" />
                    <div>
                        <span class="font-semibold text-gray-900">Directly register as approved device</span>
                        <p class="text-[11px] text-gray-500">
                            Grants immediate trusted access without requiring the user to open an invitation link.
                        </p>
                    </div>
                </label>

                <p v-if="!enrollForm.direct_approve" class="text-xs text-gray-500">
                    A single-use, 24-hour verification invitation link will be created. The user must open it on the physical device being registered.
                </p>

                <!-- 1-device policy reminder -->
                <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-900 flex items-start gap-2">
                    <ShieldCheck class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold text-blue-950">1 Account, 1 Device Policy:</span>
                        <p class="text-[11px] text-blue-800 mt-0.5">
                            Enrolling or approving a new device replaces any previously registered device for this account.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-2 border-t border-gray-100">
                    <SpecularButton type="button" variant="secondary" size="md" :radius="10" @click="showEnrollModal = false">
                        Cancel
                    </SpecularButton>
                    <SpecularButton type="submit" variant="brand" size="md" :radius="10" :disabled="enrollForm.processing">
                        {{ enrollForm.processing ? 'Saving...' : (enrollForm.direct_approve ? 'Add & Authorize Device' : 'Create Enrollment Invitation') }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Revoke Session Modal -->
    <div v-if="showRevokeSessionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Revoke Active Session</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to revoke the session for
                <span class="font-semibold text-gray-900">{{ selectedSession?.user_name }}</span>
                ({{ selectedSession?.browser }} on {{ selectedSession?.os }})? They will be logged out immediately.
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton variant="secondary" size="md" :radius="10" @click="showRevokeSessionModal = false">
                    Cancel
                </SpecularButton>
                <SpecularButton variant="destructive" size="md" :radius="10" @click="confirmRevokeSession">
                    Revoke Session
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- Revoke All User Sessions Modal -->
    <div v-if="showRevokeUserSessionsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Terminate All Sessions</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to terminate all active sessions for
                <span class="font-semibold text-gray-900">{{ selectedUser?.name }}</span>?
                They will be forced to log in again across all devices.
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton variant="secondary" size="md" :radius="10" @click="showRevokeUserSessionsModal = false">
                    Cancel
                </SpecularButton>
                <SpecularButton variant="destructive" size="md" :radius="10" @click="confirmRevokeUserSessions">
                    Revoke All Sessions
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- Revoke Device Modal -->
    <div v-if="showRevokeDeviceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Revoke Trusted Device</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to revoke access for
                <span class="font-semibold text-gray-900">{{ selectedDevice?.device_name }}</span>
                ({{ selectedDevice?.user?.name }})?
                All active sessions on this device will be terminated immediately.
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton variant="secondary" size="md" :radius="10" @click="showRevokeDeviceModal = false">
                    Cancel
                </SpecularButton>
                <SpecularButton variant="destructive" size="md" :radius="10" @click="confirmRevokeDevice">
                    Revoke Device
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- View Metadata Modal -->
    <div v-if="showMetadataModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Event Context Data</h3>
                <button @click="showMetadataModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
            <pre class="bg-gray-50 border border-gray-200 p-4 rounded-xl text-xs font-mono text-gray-800 overflow-x-auto max-h-96">{{ JSON.stringify(selectedMetadata, null, 2) }}</pre>
            <div class="flex justify-end mt-4">
                <SpecularButton variant="secondary" size="sm" :radius="8" @click="showMetadataModal = false">
                    Close
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- Universal Action Confirmation Modal -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div
                :class="[
                    'w-11 h-11 rounded-full flex items-center justify-center mb-4 border',
                    confirmOptions.confirmVariant === 'destructive'
                        ? 'bg-red-50 text-red-600 border-red-100'
                        : 'bg-amber-50 text-[#F8B803] border-amber-100'
                ]"
            >
                <AlertTriangle v-if="confirmOptions.confirmVariant === 'destructive'" class="w-6 h-6" />
                <CheckCircle2 v-else class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-1.5">{{ confirmOptions.title }}</h3>
            <p class="text-gray-600 text-sm mb-4 leading-relaxed">
                {{ confirmOptions.description }}
            </p>

            <div v-if="confirmOptions.details && confirmOptions.details.length > 0" class="bg-gray-50 border border-gray-100 rounded-xl p-3.5 mb-5 space-y-2 text-xs">
                <div v-for="(item, idx) in confirmOptions.details" :key="idx" class="flex justify-between items-center py-0.5 border-b border-gray-100 last:border-b-0">
                    <span class="text-gray-500 font-medium">{{ item.label }}</span>
                    <span class="font-semibold text-gray-900 text-right">{{ item.value }}</span>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <SpecularButton
                    type="button"
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showConfirmModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    :variant="confirmOptions.confirmVariant || 'brand'"
                    size="md"
                    :radius="10"
                    @click="handleConfirmedAction"
                    :disabled="addForm.processing || editForm.processing || enrollForm.processing"
                >
                    <Check v-if="confirmOptions.confirmVariant !== 'destructive'" class="w-4 h-4 mr-1.5 inline" />
                    <Trash2 v-else class="w-4 h-4 mr-1.5 inline" />
                    <span>{{ confirmOptions.confirmText || 'Confirm & Proceed' }}</span>
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
