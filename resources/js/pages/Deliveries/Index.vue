<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useCanDelete } from '@/composables/useCanDelete';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import { 
    Search, 
    Plus, 
    Truck, 
    Clock, 
    CheckCircle2, 
    Check,
    XCircle, 
    Package, 
    Trash2, 
    Calendar, 
    Building2,
    FileText,
    Receipt,
    ShoppingCart,
    UploadCloud,
    Download,
    AlertCircle,
    Eye,
    Phone,
    MapPin,
    User as UserIcon,
    AlertTriangle,
    RotateCcw,
    Camera,
    Image as ImageIcon,
    History,
    UserCheck,
    Navigation,
    ShieldAlert,
    ExternalLink
} from '@lucide/vue';

interface AttemptLogItem {
    id: number;
    attempt_number: number;
    previous_status: string;
    new_status: string;
    staff_id: number;
    staff?: { id: number; name: string };
    failure_reason?: string | null;
    remarks?: string | null;
    proof_image?: string | null;
    proof_url?: string | null;
    attempt_date: string;
}

interface OutboundDeliveryItem {
    id: number;
    fulfillment_number: string;
    fulfillment_type: string;
    recipient_name: string;
    contact_number: string;
    delivery_address?: string | null;
    delivery_schedule?: string | null;
    instructions?: string | null;
    status: string;
    attempt_count: number;
    remaining_attempts: number;
    attempts_display: string;
    proof_of_delivery?: string | null;
    proof_url?: string | null;
    handled_by_id?: number | null;
    handled_by?: { id: number; name: string; email: string; role: string } | null;
    dispatched_at?: string | null;
    delivered_at?: string | null;
    rescheduled_at?: string | null;
    rescheduled_by?: { id: number; name: string } | null;
    notes?: string | null;
    sale?: {
        id: number;
        invoice_number: string;
        client_name: string;
        grand_total: number;
        sale_date: string;
        items?: Array<{
            id: number;
            product_name: string;
            quantity: number;
            unit_price: number;
        }>;
    } | null;
    commercial_invoice?: {
        id: number;
        invoice_number: string;
        total_cost: number;
    } | null;
    attempt_logs?: AttemptLogItem[];
}

interface InboundDeliveryItem {
    id: number;
    delivery_date: string;
    delivery_number: string;
    purchase_order_number?: string | null;
    purchase_order_id?: number | null;
    quantity: number;
    unit: string;
    description: string;
    supplier_id?: number | null;
    received_by_id?: number | null;
    status: string;
    notes?: string | null;
    supplier?: { id: number; name: string };
    purchase_order?: { id: number; po_number: string };
    received_by?: { id: number; name: string };
}

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    cost_price: number;
    stock: number;
}

interface SupplierItem {
    id: number;
    name: string;
}

interface PurchaseOrderItem {
    id: number;
    po_number: string;
    item_sku?: string | null;
    description: string;
    unit_of_measure?: string | null;
    quantity: number;
    supplier_id?: number | null;
}

const props = defineProps<{
    activeTab: 'outbound' | 'inbound';
    isDeliveryStaff: boolean;
    isAdmin: boolean;
    outboundDeliveries?: {
        data: OutboundDeliveryItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    outboundMetrics?: {
        totalAssigned: number;
        pending: number;
        inTransit: number;
        delivered: number;
        failed: number;
        attemptsRemaining: number;
    };
    deliveryStaffList?: Array<{ id: number; name: string; email: string; role: string }>;
    inboundDeliveries?: {
        data: InboundDeliveryItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    } | null;
    inboundMetrics?: {
        totalDeliveries: number;
        totalQuantityDelivered: number;
        linkedPosCount: number;
        deliveredCount: number;
    } | null;
    products?: ProductItem[];
    suppliers?: SupplierItem[];
    purchaseOrders?: PurchaseOrderItem[];
    filters?: {
        tab?: string;
        search?: string;
        status?: string;
        staff_id?: string | number;
        date_from?: string;
        date_to?: string;
    };
}>();

const page = usePage();
const canDelete = useCanDelete();
const teamSlug = computed(() => {
    const pageTeam = (page.props as any).currentTeam?.slug || (page.props as any).team?.slug;
    if (pageTeam) return pageTeam;
    if (typeof window !== 'undefined') {
        const pathSegments = window.location.pathname.split('/').filter(Boolean);
        if (pathSegments.length > 0 && pathSegments[0] !== 'deliveries') {
            return pathSegments[0];
        }
    }
    return 'mr-pride';
});

// Notifications
const notificationMessage = ref('');
const notificationType = ref<'success' | 'error' | 'warning' | 'info'>('success');
const showNotification = (msg: string, type: 'success' | 'error' | 'warning' | 'info' = 'success') => {
    notificationMessage.value = msg;
    notificationType.value = type;
    setTimeout(() => {
        notificationMessage.value = '';
    }, 4500);
};

if ((page.props as any).flash?.success) {
    showNotification((page.props as any).flash.success, 'success');
}
if ((page.props as any).flash?.error) {
    showNotification((page.props as any).flash.error, 'error');
}
if ((page.props as any).flash?.warning) {
    showNotification((page.props as any).flash.warning, 'warning');
}

const formatDate = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatDateTime = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
};

// Current Tab State
const currentTab = ref<'outbound' | 'inbound'>(props.activeTab || 'outbound');
const switchTab = (tab: 'outbound' | 'inbound') => {
    currentTab.value = tab;
    router.get(
        `/${teamSlug.value}/deliveries`,
        { tab, search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

// Search & Filter State
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || 'All');
const selectedStaff = ref(props.filters?.staff_id !== undefined ? String(props.filters.staff_id) : 'All');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

let filterTimeout: ReturnType<typeof setTimeout> | null = null;
const applyFilter = () => {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(
            `/${teamSlug.value}/deliveries`,
            {
                tab: currentTab.value,
                search: search.value || undefined,
                status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
                staff_id: selectedStaff.value !== 'All' ? selectedStaff.value : undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = 'All';
    selectedStaff.value = 'All';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilter();
};

const hasActiveFilters = computed(() => {
    return !!search.value || selectedStatus.value !== 'All' || selectedStaff.value !== 'All' || !!dateFrom.value || !!dateTo.value;
});

// Helper: Status badge styling
const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'Delivered':
            return 'bg-emerald-50 text-emerald-600 border-emerald-200';
        case 'Out for Delivery':
        case 'Dispatched':
            return 'bg-blue-50 text-blue-600 border-blue-200';
        case 'Pending':
        case 'Preparing':
            return 'bg-amber-50 text-amber-600 border-amber-200';
        case 'Delivery Attempt Failed':
            return 'bg-orange-500/10 text-orange-400 border-orange-500/30';
        case 'Maximum Attempts Reached':
            return 'bg-rose-50 text-rose-600 border-rose-200 font-semibold';
        case 'Return to Warehouse':
            return 'bg-purple-500/10 text-purple-400 border-purple-500/30';
        default:
            return 'bg-gray-50 text-muted-foreground border-gray-200';
    }
};

const getAttemptBadgeClass = (count: number) => {
    if (count === 0) return 'bg-emerald-50 text-emerald-600 border-emerald-200';
    if (count === 1) return 'bg-blue-50 text-blue-600 border-blue-200';
    if (count === 2) return 'bg-amber-50 text-amber-600 border-amber-200 font-semibold';
    return 'bg-rose-50 text-rose-600 border-rose-200 font-semibold';
};

// ==========================================
// OUTBOUND DELIVERY ACTION MODALS
// ==========================================
const selectedFulfillment = ref<OutboundDeliveryItem | null>(null);

// 1. Details Modal
const showDetailsModal = ref(false);
const openDetails = (item: OutboundDeliveryItem) => {
    selectedFulfillment.value = item;
    showDetailsModal.value = true;
};

// 2. Start Delivery Modal
const showStartModal = ref(false);
const openStartDelivery = (item: OutboundDeliveryItem) => {
    selectedFulfillment.value = item;
    showStartModal.value = true;
};
const startDeliveryForm = useForm({});
const confirmStartDelivery = () => {
    if (!selectedFulfillment.value) return;
    startDeliveryForm.post(`/${teamSlug.value}/deliveries/fulfillments/${selectedFulfillment.value.id}/start`, {
        preserveScroll: true,
        onSuccess: () => {
            showStartModal.value = false;
            showNotification(`Delivery ${selectedFulfillment.value?.fulfillment_number} is now Out for Delivery!`, 'success');
        },
        onError: (err: any) => {
            showNotification(err.error || 'Failed to start delivery.', 'error');
        }
    });
};

// 3. Record Failed Attempt Modal
const showAttemptModal = ref(false);
const openRecordAttempt = (item: OutboundDeliveryItem) => {
    selectedFulfillment.value = item;
    attemptForm.reset();
    attemptPhotoPreview.value = null;
    showAttemptModal.value = true;
};
const attemptForm = useForm({
    reason: 'Customer unavailable',
    remarks: '',
    attempt_photo: null as File | null,
});
const attemptPhotoPreview = ref<string | null>(null);
const handleAttemptPhotoChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        attemptForm.attempt_photo = file;
        attemptPhotoPreview.value = URL.createObjectURL(file);
    }
};
const submitFailedAttempt = () => {
    if (!selectedFulfillment.value) return;
    attemptForm.post(`/${teamSlug.value}/deliveries/fulfillments/${selectedFulfillment.value.id}/attempt`, {
        preserveScroll: true,
        onSuccess: () => {
            showAttemptModal.value = false;
            showNotification('Delivery attempt recorded successfully.', 'warning');
        },
        onError: (err: any) => {
            showNotification(err.reason || err.remarks || 'Failed to record attempt.', 'error');
        }
    });
};

// 4. Mark Delivered Modal (Proof Photo Upload)
const showDeliveredModal = ref(false);
const openMarkDelivered = (item: OutboundDeliveryItem) => {
    selectedFulfillment.value = item;
    deliveredForm.reset();
    deliveredForm.recipient_name = item.recipient_name;
    deliveryPhotoPreview.value = null;
    showDeliveredModal.value = true;
};
const deliveredForm = useForm({
    recipient_name: '',
    remarks: '',
    proof_of_delivery: null as File | null,
});
const deliveryPhotoPreview = ref<string | null>(null);
const handleProofPhotoChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        deliveredForm.proof_of_delivery = file;
        deliveryPhotoPreview.value = URL.createObjectURL(file);
    }
};
const submitDelivered = () => {
    if (!selectedFulfillment.value) return;
    deliveredForm.post(`/${teamSlug.value}/deliveries/fulfillments/${selectedFulfillment.value.id}/deliver`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeliveredModal.value = false;
            showNotification(`Delivery ${selectedFulfillment.value?.fulfillment_number} marked as Delivered!`, 'success');
        },
        onError: (err: any) => {
            showNotification(err.proof_of_delivery || 'Failed to complete delivery.', 'error');
        }
    });
};

// 5. Assign Staff Modal (Admin only)
const showAssignModal = ref(false);
const openAssignStaff = (item: OutboundDeliveryItem) => {
    selectedFulfillment.value = item;
    assignForm.staff_id = item.handled_by_id ? String(item.handled_by_id) : '';
    showAssignModal.value = true;
};
const assignForm = useForm({
    staff_id: '',
});
const submitAssignStaff = () => {
    if (!selectedFulfillment.value) return;
    assignForm.post(`/${teamSlug.value}/deliveries/fulfillments/${selectedFulfillment.value.id}/assign`, {
        preserveScroll: true,
        onSuccess: () => {
            showAssignModal.value = false;
            showNotification('Delivery assigned successfully.', 'success');
        }
    });
};

// 6. Reschedule Modal (Admin only)
const showRescheduleModal = ref(false);
const openReschedule = (item: OutboundDeliveryItem) => {
    selectedFulfillment.value = item;
    rescheduleForm.reset();
    rescheduleForm.delivery_schedule = new Date(Date.now() + 86400000).toISOString().slice(0, 16);
    rescheduleForm.reset_attempts = true;
    showRescheduleModal.value = true;
};
const rescheduleForm = useForm({
    delivery_schedule: '',
    staff_id: '',
    reset_attempts: true,
    notes: '',
});
const submitReschedule = () => {
    if (!selectedFulfillment.value) return;
    rescheduleForm.post(`/${teamSlug.value}/deliveries/fulfillments/${selectedFulfillment.value.id}/reschedule`, {
        preserveScroll: true,
        onSuccess: () => {
            showRescheduleModal.value = false;
            showNotification('Delivery successfully rescheduled.', 'success');
        }
    });
};

// 7. Proof Viewer Modal
const showProofViewerModal = ref(false);
const viewingProofUrl = ref<string | null>(null);
const viewingProofTitle = ref('');
const openProofViewer = (url: string, title: string) => {
    viewingProofUrl.value = url;
    viewingProofTitle.value = title;
    showProofViewerModal.value = true;
};

// ==========================================
// INBOUND SUPPLIER RECEIVING LOGIC (Preserved)
// ==========================================
const showCreateInboundModal = ref(false);
const inboundForm = useForm({
    delivery_date: new Date().toISOString().slice(0, 10),
    delivery_number: '',
    purchase_order_number: '',
    quantity: 1,
    unit: 'pcs',
    description: '',
    supplier_id: '',
    status: 'Delivered',
    notes: '',
});
const submitInboundDelivery = () => {
    inboundForm.post(`/${teamSlug.value}/deliveries`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateInboundModal.value = false;
            inboundForm.reset();
            showNotification('Inbound delivery record saved.', 'success');
        }
    });
};

const showDeleteConfirm = ref(false);
const deleteTargetId = ref<number | null>(null);
const confirmDeleteInbound = (id: number) => {
    deleteTargetId.value = id;
    showDeleteConfirm.value = true;
};
const executeDeleteInbound = () => {
    if (!deleteTargetId.value) return;
    router.delete(`/${teamSlug.value}/deliveries/${deleteTargetId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            deleteTargetId.value = null;
            showNotification('Delivery record deleted.', 'success');
        }
    });
};
</script>

<template>
    <Head title="Deliveries — MR-PRIDE" />

    <div class="space-y-6">
        <!-- Toast Notification -->
        <transition enter-active-class="transform ease-out duration-300 transition" enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2" enter-to-class="translate-y-0 opacity-100 sm:translate-x-0" leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="notificationMessage" class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-xl" :class="{
                'bg-emerald-950/90 border-emerald-200 text-emerald-700': notificationType === 'success',
                'bg-rose-950/90 border-rose-200 text-rose-700': notificationType === 'error',
                'bg-amber-950/90 border-amber-200 text-amber-700': notificationType === 'warning',
                'bg-blue-950/90 border-blue-200 text-blue-700': notificationType === 'info'
            }">
                <CheckCircle2 v-if="notificationType === 'success'" class="size-5 shrink-0 text-emerald-600" />
                <AlertCircle v-else-if="notificationType === 'error'" class="size-5 shrink-0 text-rose-600" />
                <AlertTriangle v-else-if="notificationType === 'warning'" class="size-5 shrink-0 text-amber-600" />
                <span class="text-sm font-medium">{{ notificationMessage }}</span>
            </div>
        </transition>

        <!-- Top Header & Role Indicator -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="page-title">
                    {{ isDeliveryStaff ? 'Delivery Staff Dashboard' : 'Deliveries & Logistics' }}
                </h1>
                <p class="page-subtitle">
                    {{ isDeliveryStaff
                        ? 'Your assigned deliveries, attempts and handovers.'
                        : 'Customer deliveries, driver assignments and supplier PO receiving.' }}
                </p>
            </div>

            <!-- Tab Switcher (Visible to Admin & Non-Driver Staff) -->
            <div v-if="!isDeliveryStaff" class="flex items-center gap-1 rounded-xl border border-gray-200 bg-white p-1">
                <button 
                    @click="switchTab('outbound')"
                    class="flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors"
                    :class="currentTab === 'outbound' ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-900'"
                >
                    <Truck class="size-4" />
                    Customer Deliveries
                    <span class="text-xs text-gray-400">{{ outboundMetrics?.totalAssigned ?? 0 }}</span>
                </button>
                <button 
                    @click="switchTab('inbound')"
                    class="flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors"
                    :class="currentTab === 'inbound' ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-900'"
                >
                    <Package class="size-4" />
                    Inbound PO Receiving
                    <span class="text-xs text-gray-400">{{ inboundMetrics?.totalDeliveries ?? 0 }}</span>
                </button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TAB 1: OUTBOUND CUSTOMER DELIVERIES (Driver Dashboard)    -->
        <!-- ========================================================= -->
        <template v-if="currentTab === 'outbound'">
            <!-- Summary Metric Cards (6 core metrics requested). Colour only marks counts that need attention. -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    v-for="card in [
                        { label: isDeliveryStaff ? 'Assigned to you' : 'Total assigned', value: outboundMetrics?.totalAssigned ?? 0, alert: '' },
                        { label: 'Pending', value: outboundMetrics?.pending ?? 0, alert: 'text-amber-600' },
                        { label: 'In transit', value: outboundMetrics?.inTransit ?? 0, alert: '' },
                        { label: 'Delivered', value: outboundMetrics?.delivered ?? 0, alert: '' },
                        { label: 'Failed / max attempts', value: outboundMetrics?.failed ?? 0, alert: 'text-rose-600' },
                        { label: 'Attempts left', value: outboundMetrics?.attemptsRemaining ?? 0, alert: '' },
                    ]"
                    :key="card.label"
                    class="rounded-xl border border-gray-200 bg-white px-4 py-3"
                >
                    <div class="text-xs text-gray-500">{{ card.label }}</div>
                    <div :class="card.value > 0 && card.alert ? card.alert : 'text-gray-900'" class="mt-1 text-2xl font-semibold">{{ card.value }}</div>
                </div>
            </div>

            <!-- Search & Filters Toolbar -->
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <Search class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            @input="applyFilter"
                            type="text"
                            placeholder="Search by recipient, address, phone, invoice, or fulfillment #..."
                            class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                        />
                    </div>

                    <!-- Status Filter -->
                    <select
                        v-model="selectedStatus"
                        @change="applyFilter"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    >
                        <option value="All">All Statuses</option>
                        <option value="Pending">Pending Dispatch</option>
                        <option value="Out for Delivery">Out for Delivery</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Delivery Attempt Failed">Delivery Attempt Failed</option>
                        <option value="Maximum Attempts Reached">Maximum Attempts Reached</option>
                        <option value="Return to Warehouse">Return to Warehouse</option>
                    </select>

                    <!-- Staff Filter (Admin only) -->
                    <select
                        v-if="isAdmin"
                        v-model="selectedStaff"
                        @change="applyFilter"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    >
                        <option value="All">All Delivery Drivers</option>
                        <option v-for="staff in deliveryStaffList" :key="staff.id" :value="String(staff.id)">
                            {{ staff.name }} ({{ staff.role }})
                        </option>
                    </select>

                    <!-- Clear Filters -->
                    <button
                        v-if="hasActiveFilters"
                        @click="resetFilters"
                        class="flex items-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-muted-foreground hover:bg-gray-100 hover:text-foreground"
                    >
                        <RotateCcw class="size-3.5" />
                        Reset
                    </button>
                </div>
            </div>

            <!-- Outbound Delivery Cards / Table (Mobile Optimized) -->
            <div class="space-y-4">
                <!-- Empty State -->
                <div v-if="!outboundDeliveries?.data || outboundDeliveries.data.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-card/40 p-12 text-center">
                    <Truck class="size-12 text-muted-foreground/40" />
                    <h3 class="mt-4 text-base font-semibold text-foreground">No Delivery Tasks Found</h3>
                    <p class="mt-1 text-sm text-muted-foreground max-w-md">
                        {{ isDeliveryStaff 
                            ? 'You currently have no delivery tasks assigned. Check back later or contact your operations administrator.' 
                            : 'No customer order fulfillments match your selected filters.' }}
                    </p>
                </div>

                <!-- Deliveries List -->
                <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div
                        v-for="item in outboundDeliveries.data"
                        :key="item.id"
                        class="relative flex flex-col justify-between overflow-hidden rounded-xl border border-gray-200 bg-white p-5 transition-colors duration-200 hover:border-gray-200"
                    >
                        <!-- Top Row: Reference, Status & Attempt Badge -->
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-semibold tracking-wider text-foreground">
                                            {{ item.fulfillment_number }}
                                        </span>
                                        <span v-if="item.sale" class="text-xs text-muted-foreground">
                                            • {{ item.sale.invoice_number }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium" :class="getStatusBadgeClass(item.status)">
                                            {{ item.status }}
                                        </span>
                                        <!-- Attempt Counter Badge (0/3, 1/3, 2/3, 3/3) -->
                                        <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs" :class="getAttemptBadgeClass(item.attempt_count)">
                                            <RotateCcw class="size-3" />
                                            Attempt: {{ item.attempts_display }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Driver Assignment Badge -->
                                <div class="text-right">
                                    <span v-if="item.handled_by" class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                        <UserIcon class="size-3" />
                                        {{ item.handled_by.name }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-600">
                                        Unassigned
                                    </span>
                                </div>
                            </div>

                            <!-- Customer Information Block (Read-only as required) -->
                            <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3.5 space-y-2">
                                <!-- Customer Name -->
                                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <UserCheck class="size-4 text-brand shrink-0" />
                                    <span>{{ item.recipient_name }}</span>
                                    <span v-if="item.sale?.client_name && item.sale.client_name !== item.recipient_name" class="text-xs text-muted-foreground font-normal">
                                        ({{ item.sale.client_name }})
                                    </span>
                                </div>

                                <!-- Address -->
                                <div class="flex items-start gap-2 text-xs text-muted-foreground">
                                    <MapPin class="size-4 text-rose-600 shrink-0 mt-0.5" />
                                    <span class="leading-relaxed">{{ item.delivery_address || 'No address specified' }}</span>
                                </div>

                                <!-- Contact Phone (Click to call for mobile driver convenience) -->
                                <div class="flex items-center justify-between text-xs pt-1 border-t border-gray-200">
                                    <a :href="'tel:' + item.contact_number" class="flex items-center gap-1.5 font-medium text-brand hover:underline">
                                        <Phone class="size-3.5" />
                                        <span>{{ item.contact_number }}</span>
                                    </a>
                                    <span v-if="item.delivery_schedule" class="text-muted-foreground flex items-center gap-1">
                                        <Calendar class="size-3" />
                                        {{ formatDate(item.delivery_schedule) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Remarks / Notes Preview if any -->
                            <div v-if="item.notes" class="mt-2 text-[11px] text-muted-foreground/80 line-clamp-2 italic px-1">
                                "{{ item.notes }}"
                            </div>
                        </div>

                        <!-- Action Buttons Bar -->
                        <div class="mt-5 pt-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
                            <!-- Left: Details and Proof -->
                            <div class="flex items-center gap-2">
                                <button
                                    @click="openDetails(item)"
                                    class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:bg-gray-100 hover:text-foreground"
                                >
                                    <Eye class="size-3.5" />
                                    Details & History
                                </button>

                                <button
                                    v-if="item.proof_url"
                                    @click="openProofViewer(item.proof_url, `Proof of Delivery: ${item.fulfillment_number}`)"
                                    class="flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-600 transition-colors hover:bg-emerald-500/20"
                                >
                                    <ImageIcon class="size-3.5" />
                                    Proof Photo
                                </button>
                            </div>

                            <!-- Right: Status-Specific Driver & Admin Actions -->
                            <div class="flex items-center gap-2">
                                <!-- 1. When Pending -> Start Delivery -->
                                <button
                                    v-if="item.status === 'Pending' || item.status === 'Preparing'"
                                    @click="openStartDelivery(item)"
                                    class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#3b4219]"
                                >
                                    <Navigation class="size-3.5" />
                                    Start Delivery
                                </button>

                                <!-- 2. When Out for Delivery or Failed (<3 attempts) -> Mark Delivered & Record Attempt -->
                                <template v-if="item.status === 'Out for Delivery' || (item.status === 'Delivery Attempt Failed' && item.attempt_count < 3)">
                                    <!-- Retry Delivery if failed -->
                                    <button
                                        v-if="item.status === 'Delivery Attempt Failed'"
                                        @click="openStartDelivery(item)"
                                        class="flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-500/20"
                                    >
                                        <RotateCcw class="size-3.5" />
                                        Retry Dispatch
                                    </button>

                                    <!-- Record Failed Attempt -->
                                    <button
                                        @click="openRecordAttempt(item)"
                                        class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-600 hover:bg-amber-500/20"
                                    >
                                        <AlertTriangle class="size-3.5" />
                                        Failed Attempt
                                    </button>

                                    <!-- Mark Delivered (With Photo) -->
                                    <button
                                        @click="openMarkDelivered(item)"
                                        class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#3b4219]"
                                    >
                                        <Camera class="size-3.5" />
                                        Mark Delivered
                                    </button>
                                </template>

                                <!-- 3. When 3/3 Max Attempts Reached -> Blocked & Requires Admin -->
                                <div v-if="item.attempt_count >= 3 && item.status !== 'Delivered'" class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-600">
                                        <ShieldAlert class="size-3.5" />
                                        3/3 Limit Reached
                                    </span>

                                    <!-- Admin can Reschedule -->
                                    <button
                                        v-if="isAdmin"
                                        @click="openReschedule(item)"
                                        class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand/90"
                                    >
                                        <RotateCcw class="size-3.5" />
                                        Reschedule
                                    </button>
                                </div>

                                <!-- Admin: Assign Driver if unassigned -->
                                <button
                                    v-if="isAdmin && !item.handled_by_id && item.status !== 'Delivered'"
                                    @click="openAssignStaff(item)"
                                    class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-foreground hover:bg-white/20"
                                >
                                    <UserCheck class="size-3.5" />
                                    Assign Driver
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="outboundDeliveries?.links && outboundDeliveries.links.length > 3" class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
                    <div class="text-xs text-muted-foreground">
                        Showing {{ (outboundDeliveries.current_page - 1) * 12 + 1 }} to {{ Math.min(outboundDeliveries.current_page * 12, outboundDeliveries.total) }} of {{ outboundDeliveries.total }} deliveries
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="(link, i) in outboundDeliveries.links"
                            :key="i"
                            @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
                            :disabled="!link.url || link.active"
                            v-html="link.label"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="{
                                'bg-brand text-white font-semibold': link.active,
                                'border border-gray-200 text-muted-foreground hover:text-foreground hover:bg-gray-50': !link.active && link.url,
                                'opacity-40 cursor-not-allowed': !link.url
                            }"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- ========================================================= -->
        <!-- TAB 2: INBOUND SUPPLIER RECEIVING (Preserved)             -->
        <!-- ========================================================= -->
        <template v-else-if="!isDeliveryStaff && currentTab === 'inbound'">
            <!-- Inbound Action Bar -->
            <div class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">Receiving log for supplier purchase order shipments into the main warehouse.</p>
                <div class="flex items-center gap-2">
                    <button
                        @click="showCreateInboundModal = true"
                        class="flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand/90"
                    >
                        <Plus class="size-4" />
                        Record Inbound PO Delivery
                    </button>
                </div>
            </div>

            <!-- Inbound Deliveries Table -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Delivery Date</th>
                                <th class="px-4 py-3">Delivery #</th>
                                <th class="px-4 py-3">PO #</th>
                                <th class="px-4 py-3">Supplier</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="!inboundDeliveries?.data || inboundDeliveries.data.length === 0">
                                <td colspan="8" class="p-8 text-center text-sm text-muted-foreground">No inbound supplier delivery records found.</td>
                            </tr>
                            <tr v-for="item in inboundDeliveries?.data" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-xs">{{ formatDate(item.delivery_date) }}</td>
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-foreground">{{ item.delivery_number }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-brand">{{ item.purchase_order_number || '—' }}</td>
                                <td class="px-4 py-3 text-xs">{{ item.supplier?.name || '—' }}</td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ item.description }}</td>
                                <td class="px-4 py-3 text-right text-xs font-semibold">{{ item.quantity }} {{ item.unit }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <span class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="canDelete"
                                        @click="confirmDeleteInbound(item.id)"
                                        class="rounded-lg p-1 text-muted-foreground hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 1: START DELIVERY CONFIRMATION                      -->
    <!-- ========================================================= -->
    <div v-if="showStartModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-card p-6 shadow-xl">
            <div class="flex items-center gap-3 text-blue-600">
                <Navigation class="size-6 shrink-0" />
                <h3 class="text-lg font-semibold text-foreground">Start Delivery Dispatch</h3>
            </div>
            <p class="mt-3 text-sm text-muted-foreground leading-relaxed">
                Confirm that you are departing the warehouse to deliver order <strong class="text-foreground">{{ selectedFulfillment?.fulfillment_number }}</strong> to <strong class="text-foreground">{{ selectedFulfillment?.recipient_name }}</strong>.
            </p>
            <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs text-muted-foreground">
                <div class="font-medium text-foreground">Address:</div>
                <div>{{ selectedFulfillment?.delivery_address }}</div>
            </div>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    @click="showStartModal = false"
                    type="button"
                    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-gray-100"
                >
                    Cancel
                </button>
                <button
                    @click="confirmStartDelivery"
                    :disabled="startDeliveryForm.processing"
                    class="flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#3b4219] disabled:opacity-50"
                >
                    <Navigation class="size-4" />
                    Confirm Out for Delivery
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 2: RECORD FAILED ATTEMPT (Strict 3-Limit Enforced)  -->
    <!-- ========================================================= -->
    <div v-if="showAttemptModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-lg rounded-xl border border-amber-200 bg-card p-6 shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                <div class="flex items-center gap-2.5 text-amber-600">
                    <AlertTriangle class="size-5 shrink-0" />
                    <h3 class="text-lg font-semibold text-foreground">Record Unsuccessful Attempt</h3>
                </div>
                <span class="rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                    Recording Attempt {{ (selectedFulfillment?.attempt_count ?? 0) + 1 }} of 3
                </span>
            </div>

            <!-- Warning notice if this is attempt 3 -->
            <div v-if="(selectedFulfillment?.attempt_count ?? 0) >= 2" class="mt-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                <ShieldAlert class="size-4 shrink-0 mt-0.5 text-rose-600" />
                <div>
                    <strong>CRITICAL NOTICE (Final Attempt 3 of 3):</strong>
                    This will reach the strict 3-attempt limit. The delivery will automatically lock to <em>Maximum Attempts Reached</em> and will require administrator follow-up.
                </div>
            </div>

            <form @submit.prevent="submitFailedAttempt" class="mt-4 space-y-4">
                <!-- Reason Dropdown -->
                <div>
                    <label class="block text-xs font-medium text-foreground">Failure Reason</label>
                    <select
                        v-model="attemptForm.reason"
                        required
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    >
                        <option value="Customer unavailable">Customer unavailable</option>
                        <option value="Incorrect or incomplete address">Incorrect or incomplete address</option>
                        <option value="Customer refused delivery">Customer refused delivery</option>
                        <option value="Customer unreachable">Customer unreachable (unresponsive on phone)</option>
                        <option value="Other reason">Other reason (explain in remarks)</option>
                    </select>
                </div>

                <!-- Remarks Textarea -->
                <div>
                    <label class="block text-xs font-medium text-foreground">Detailed Remarks / Attempt Notes</label>
                    <textarea
                        v-model="attemptForm.remarks"
                        required
                        rows="3"
                        placeholder="Provide details about what happened during this attempt..."
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white p-3 text-sm text-foreground placeholder:text-muted-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    />
                </div>

                <!-- Optional Photo Evidence -->
                <div>
                    <label class="block text-xs font-medium text-foreground">Optional Photo Evidence (Closed gate, site signage, etc.)</label>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/jpg"
                        @change="handleAttemptPhotoChange"
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white p-2 text-xs text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white"
                    />
                    <div v-if="attemptPhotoPreview" class="mt-2">
                        <img :src="attemptPhotoPreview" class="h-28 w-auto rounded-lg object-cover border border-gray-200" />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-gray-200">
                    <button
                        @click="showAttemptModal = false"
                        type="button"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="attemptForm.processing"
                        class="flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-500 disabled:opacity-50"
                    >
                        <AlertTriangle class="size-4" />
                        Record Attempt {{ (selectedFulfillment?.attempt_count ?? 0) + 1 }}/3
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 3: MARK DELIVERED WITH PHOTO PROOF                  -->
    <!-- ========================================================= -->
    <div v-if="showDeliveredModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-lg rounded-xl border border-emerald-200 bg-card p-6 shadow-xl">
            <div class="flex items-center gap-2.5 border-b border-gray-200 pb-3 text-emerald-600">
                <Camera class="size-5 shrink-0" />
                <h3 class="text-lg font-semibold text-foreground">Complete Delivery & Upload Proof Photo</h3>
            </div>

            <form @submit.prevent="submitDelivered" class="mt-4 space-y-4">
                <!-- Proof Photo Upload (Required) -->
                <div>
                    <label class="block text-xs font-semibold text-foreground">
                        Photo Proof of Delivery <span class="text-rose-600">* (JPG or PNG required)</span>
                    </label>
                    <p class="text-[11px] text-muted-foreground mt-0.5">Capture signed invoice, delivery receipt, or received package with customer.</p>
                    <div class="mt-2 flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-emerald-200 bg-white p-4 text-center hover:border-emerald-500/50">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/jpg"
                            required
                            @change="handleProofPhotoChange"
                            class="w-full cursor-pointer text-xs text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white"
                        />
                        <div v-if="deliveryPhotoPreview" class="mt-3 relative w-full">
                            <img :src="deliveryPhotoPreview" class="mx-auto max-h-48 rounded-lg object-contain border border-gray-200 shadow-md" />
                            <span class="mt-1 block text-center text-[10px] text-emerald-600">Preview ready for upload</span>
                        </div>
                    </div>
                </div>

                <!-- Recipient Confirmed Name -->
                <div>
                    <label class="block text-xs font-medium text-foreground">Received By (Name / Title)</label>
                    <input
                        v-model="deliveredForm.recipient_name"
                        type="text"
                        placeholder="Person who physically received the package"
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    />
                </div>

                <!-- Handover Remarks -->
                <div>
                    <label class="block text-xs font-medium text-foreground">Delivery Remarks</label>
                    <textarea
                        v-model="deliveredForm.remarks"
                        rows="2"
                        placeholder="Package condition, gate pass details, or recipient remarks..."
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white p-3 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    />
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-gray-200">
                    <button
                        @click="showDeliveredModal = false"
                        type="button"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="deliveredForm.processing || !deliveredForm.proof_of_delivery"
                        class="flex items-center gap-1.5 rounded-xl bg-brand px-5 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#3b4219] disabled:opacity-50"
                    >
                        <Check class="size-4" />
                        Confirm & Mark Delivered
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 4: DETAILS & AUDIT ATTEMPT HISTORY DRAWER           -->
    <!-- ========================================================= -->
    <div v-if="showDetailsModal && selectedFulfillment" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl border border-gray-200 bg-card p-6 shadow-xl">
            <!-- Header -->
            <div class="flex items-start justify-between border-b border-gray-200 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-lg font-semibold text-foreground">{{ selectedFulfillment.fulfillment_number }}</span>
                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium" :class="getStatusBadgeClass(selectedFulfillment.status)">
                            {{ selectedFulfillment.status }}
                        </span>
                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs" :class="getAttemptBadgeClass(selectedFulfillment.attempt_count)">
                            {{ selectedFulfillment.attempts_display }}
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Order Fulfillment Details & Delivery Attempt History
                    </div>
                </div>
                <button @click="showDetailsModal = false" class="rounded-lg p-1.5 text-muted-foreground hover:bg-gray-100 hover:text-foreground">
                    <XCircle class="size-5" />
                </button>
            </div>

            <!-- Customer & Delivery Specs Grid -->
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Customer Details -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2">
                    <div class="text-xs font-semibold text-muted-foreground">Customer Information</div>
                    <div class="text-sm font-semibold text-foreground">{{ selectedFulfillment.recipient_name }}</div>
                    <div class="text-xs text-muted-foreground flex items-center gap-1.5">
                        <Phone class="size-3.5 text-brand" />
                        <a :href="'tel:' + selectedFulfillment.contact_number" class="hover:underline">{{ selectedFulfillment.contact_number }}</a>
                    </div>
                    <div class="text-xs text-muted-foreground flex items-start gap-1.5 pt-1">
                        <MapPin class="size-3.5 text-rose-600 shrink-0 mt-0.5" />
                        <span>{{ selectedFulfillment.delivery_address || 'No address specified' }}</span>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2">
                    <div class="text-xs font-semibold text-muted-foreground">Order & Logistics</div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Sales Invoice:</span>
                        <span class="font-mono font-semibold text-foreground">{{ selectedFulfillment.sale?.invoice_number || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Assigned Driver:</span>
                        <span class="font-semibold text-foreground">{{ selectedFulfillment.handled_by?.name || 'Unassigned' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Scheduled Date:</span>
                        <span>{{ formatDate(selectedFulfillment.delivery_schedule) }}</span>
                    </div>
                    <div v-if="selectedFulfillment.delivered_at" class="flex items-center justify-between text-xs text-emerald-600">
                        <span>Delivered At:</span>
                        <span class="font-medium">{{ formatDateTime(selectedFulfillment.delivered_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Order Items breakdown -->
            <div v-if="selectedFulfillment.sale?.items && selectedFulfillment.sale.items.length > 0" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs font-semibold text-muted-foreground mb-2">Package Contents</div>
                <div class="space-y-1.5">
                    <div v-for="item in selectedFulfillment.sale.items" :key="item.id" class="flex items-center justify-between text-xs py-1 border-b border-gray-200 last:border-0">
                        <span class="text-foreground">{{ item.product_name }}</span>
                        <span class="font-mono font-semibold text-muted-foreground">{{ item.quantity }} pcs</span>
                    </div>
                </div>
            </div>

            <!-- Chronological Attempt History (Requirement 7) -->
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-muted-foreground flex items-center gap-1.5">
                        Delivery Attempt & Audit History
                    </h4>
                    <span class="text-[11px] text-muted-foreground">{{ selectedFulfillment.attempt_logs?.length ?? 0 }} log entries</span>
                </div>

                <div class="mt-3 space-y-3">
                    <div v-if="!selectedFulfillment.attempt_logs || selectedFulfillment.attempt_logs.length === 0" class="rounded-xl border border-dashed border-gray-200 p-4 text-center text-xs text-muted-foreground">
                        No delivery attempt entries logged yet.
                    </div>

                    <div
                        v-for="log in selectedFulfillment.attempt_logs"
                        :key="log.id"
                        class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs space-y-1.5"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-foreground">
                                    {{ log.attempt_number > 0 ? `Attempt #${log.attempt_number}` : 'Activity Log' }}
                                </span>
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-medium" :class="getStatusBadgeClass(log.new_status)">
                                    {{ log.new_status }}
                                </span>
                            </div>
                            <span class="text-[11px] text-muted-foreground">{{ formatDateTime(log.attempt_date) }}</span>
                        </div>

                        <div v-if="log.failure_reason" class="text-rose-600 font-medium">
                            Reason: {{ log.failure_reason }}
                        </div>

                        <div class="text-muted-foreground leading-relaxed">
                            {{ log.remarks }}
                        </div>

                        <div class="flex items-center justify-between pt-1 text-[11px] text-muted-foreground/70">
                            <span>Logged by: {{ log.staff?.name || 'Staff' }}</span>
                            <button
                                v-if="log.proof_url"
                                @click="openProofViewer(log.proof_url, `Attempt #${log.attempt_number} Photo`)"
                                class="text-brand hover:underline flex items-center gap-1 font-medium"
                            >
                                <ImageIcon class="size-3" />
                                View Photo
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
                <button
                    @click="showDetailsModal = false"
                    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-gray-100"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 5: ASSIGN DRIVER (Admin only)                       -->
    <!-- ========================================================= -->
    <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-card p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-foreground">Assign Delivery Driver</h3>
            <p class="mt-1 text-xs text-muted-foreground">Select a registered staff member to handle delivery {{ selectedFulfillment?.fulfillment_number }}.</p>

            <form @submit.prevent="submitAssignStaff" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-foreground">Delivery Staff Member</label>
                    <select
                        v-model="assignForm.staff_id"
                        required
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    >
                        <option value="" disabled>Select Driver</option>
                        <option v-for="staff in deliveryStaffList" :key="staff.id" :value="String(staff.id)">
                            {{ staff.name }} ({{ staff.role }})
                        </option>
                    </select>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        @click="showAssignModal = false"
                        type="button"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="assignForm.processing || !assignForm.staff_id"
                        class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand/90 disabled:opacity-50"
                    >
                        Assign Driver
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 6: RESCHEDULE DELIVERY (Admin only)                 -->
    <!-- ========================================================= -->
    <div v-if="showRescheduleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-card p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-foreground">Reschedule Delivery & Reset Limit</h3>
            <p class="mt-1 text-xs text-muted-foreground">Authorize a new delivery window and grant fresh attempt slots.</p>

            <form @submit.prevent="submitReschedule" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-foreground">New Delivery Date & Time</label>
                    <input
                        v-model="rescheduleForm.delivery_schedule"
                        type="datetime-local"
                        required
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="resetAttempts"
                        v-model="rescheduleForm.reset_attempts"
                        type="checkbox"
                        class="size-4 rounded border-gray-200 bg-background text-brand focus:ring-brand/20"
                    />
                    <label for="resetAttempts" class="text-xs font-medium text-foreground cursor-pointer">
                        Reset Attempt Counter back to 0 of 3 attempts
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-medium text-foreground">Authorization Notes</label>
                    <textarea
                        v-model="rescheduleForm.notes"
                        rows="2"
                        placeholder="Reason for reschedule authorization (e.g. customer requested postponement)..."
                        class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white p-3 text-sm text-foreground focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/20"
                    />
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        @click="showRescheduleModal = false"
                        type="button"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="rescheduleForm.processing"
                        class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand/90 disabled:opacity-50"
                    >
                        Authorize Reschedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 7: PHOTO PROOF VIEWER                               -->
    <!-- ========================================================= -->
    <div v-if="showProofViewerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-card p-6 shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                <h3 class="text-sm font-semibold text-foreground">{{ viewingProofTitle }}</h3>
                <button @click="showProofViewerModal = false" class="text-muted-foreground hover:text-foreground">
                    <XCircle class="size-5" />
                </button>
            </div>
            <div class="mt-4 flex items-center justify-center bg-black/40 rounded-xl p-2">
                <img :src="viewingProofUrl || ''" class="max-h-[70vh] max-w-full rounded-lg object-contain shadow-lg" />
            </div>
            <div class="mt-4 flex justify-end">
                <button
                    @click="showProofViewerModal = false"
                    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-gray-100"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 8: CREATE INBOUND PO RECORD (Preserved)             -->
    <!-- ========================================================= -->
    <div v-if="showCreateInboundModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-card p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-foreground">Record Inbound Supplier Delivery</h3>
            <form @submit.prevent="submitInboundDelivery" class="mt-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-foreground">Date</label>
                    <input v-model="inboundForm.delivery_date" type="date" required class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-foreground">Purchase Order Number</label>
                    <input v-model="inboundForm.purchase_order_number" type="text" placeholder="e.g. PO-2026-001" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-foreground">Quantity</label>
                        <input v-model.number="inboundForm.quantity" type="number" min="1" required class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-foreground">Unit</label>
                        <input v-model="inboundForm.unit" type="text" placeholder="pcs, rolls..." class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-foreground">Description</label>
                    <input v-model="inboundForm.description" type="text" required placeholder="Item description" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-foreground" />
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button @click="showCreateInboundModal = false" type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-xs text-muted-foreground">Cancel</button>
                    <button type="submit" :disabled="inboundForm.processing" class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white">Save Delivery</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 9: DELETE INBOUND CONFIRMATION                      -->
    <!-- ========================================================= -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-sm rounded-xl border border-rose-200 bg-card p-6 shadow-xl">
            <h3 class="text-base font-semibold text-foreground">Delete Delivery Record?</h3>
            <p class="mt-2 text-xs text-muted-foreground">Are you sure you want to delete this delivery record? This action cannot be undone.</p>
            <div class="mt-5 flex justify-end gap-2">
                <button @click="showDeleteConfirm = false" class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs text-muted-foreground">Cancel</button>
                <button @click="executeDeleteInbound" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500">Delete</button>
            </div>
        </div>
    </div>
</template>
