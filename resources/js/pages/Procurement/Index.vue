<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import { 
    Search, 
    Plus, 
    Truck, 
    Clock, 
    CheckCircle2, 
    XCircle, 
    Package, 
    Trash2, 
    Calendar, 
    Building2 
} from '@lucide/vue';

interface PurchaseRequestItem {
    id: number;
    request_number: string;
    product_id: number;
    supplier_id: number;
    quantity: number;
    estimated_cost: number;
    status: 'Requested' | 'Ordered' | 'Delivered' | 'Cancelled';
    notes?: string | null;
    requested_at: string;
    product?: { id: number; name: string; sku: string };
    supplier?: { id: number; name: string };
    requested_by?: { id: number; name: string };
}

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    cost_price: number;
    price: number;
    stock: number;
    reorder_level: number;
}

interface SupplierItem {
    id: number;
    name: string;
}

const props = defineProps<{
    requests: {
        data: PurchaseRequestItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    filters?: {
        search?: string;
        status?: string;
    };
    metrics: {
        totalRequests: number;
        requestedCount: number;
        orderedCount: number;
        deliveredCount: number;
    };
    products: ProductItem[];
    suppliers: SupplierItem[];
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || (page.props as any).team?.slug || '');

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};

// Search & Filter
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || 'All');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
const applyFilter = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            `/${teamSlug.value}/procurement`,
            {
                search: search.value || undefined,
                status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
};

// Modal & Form
const showCreateModal = ref(false);
const showDeleteModal = ref(false);
const selectedRequest = ref<PurchaseRequestItem | null>(null);

const form = useForm({
    product_id: props.products[0]?.id || null,
    supplier_id: props.suppliers[0]?.id || null,
    quantity: 10,
    notes: '',
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    form.product_id = props.products[0]?.id || null;
    form.supplier_id = props.suppliers[0]?.id || null;
    form.quantity = 10;
    showCreateModal.value = true;
};

const submitRequest = () => {
    form.post(`/${teamSlug.value}/procurement`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const updateStatus = (req: PurchaseRequestItem, newStatus: string) => {
    router.patch(
        `/${teamSlug.value}/procurement/${req.id}/status`,
        { status: newStatus },
        { preserveScroll: true }
    );
};

const openDeleteModal = (req: PurchaseRequestItem) => {
    selectedRequest.value = req;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!selectedRequest.value) return;
    router.delete(`/${teamSlug.value}/procurement/${selectedRequest.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedRequest.value = null;
        },
    });
};
</script>

<template>
    <Head title="Procurement Monitoring" />

    <div class="workspace-page">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Procurement Monitoring</h1>
                <p class="text-gray-500 mt-1">Track purchase requests, supplier fulfillments, and inbound restocking.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="10"
                @click="openCreateModal"
                class="w-full md:w-auto"
            >
                <Plus class="w-4 h-4 mr-1.5 inline" />
                New Purchase Request
            </SpecularButton>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total Requests</span>
                    <Package class="w-4 h-4 text-gray-400" />
                </div>
                <div class="text-2xl font-black text-gray-900">{{ metrics.totalRequests }}</div>
                <div class="text-xs text-gray-500 mt-1">All lifecycle stages</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Requested</span>
                    <Clock class="w-4 h-4 text-blue-500" />
                </div>
                <div class="text-2xl font-black text-blue-600">{{ metrics.requestedCount }}</div>
                <div class="text-xs text-gray-500 mt-1">Awaiting vendor PO</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Ordered</span>
                    <Truck class="w-4 h-4 text-[#F8B803]" />
                </div>
                <div class="text-2xl font-black text-[#F8B803]">{{ metrics.orderedCount }}</div>
                <div class="text-xs text-gray-500 mt-1">In transit / processing</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Delivered</span>
                    <CheckCircle2 class="w-4 h-4 text-emerald-500" />
                </div>
                <div class="text-2xl font-black text-emerald-600">{{ metrics.deliveredCount }}</div>
                <div class="text-xs text-gray-500 mt-1">Completed deliveries</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-col sm:flex-row gap-4 mb-4">
            <div class="relative flex-1 sm:max-w-md">
                <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    @input="applyFilter"
                    type="text"
                    placeholder="Search by request #, product or supplier..."
                    class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm"
                />
            </div>
            <select
                v-model="selectedStatus"
                @change="applyFilter"
                class="bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm min-w-[150px]"
            >
                <option value="All">All Statuses</option>
                <option value="Requested">Requested</option>
                <option value="Ordered">Ordered</option>
                <option value="Delivered">Delivered</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>

        <!-- Procurement Table -->
        <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-sm flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-medium">Request ID</th>
                            <th class="px-6 py-4 font-medium">Item Name</th>
                            <th class="px-6 py-4 font-medium">Supplier</th>
                            <th class="px-6 py-4 font-medium text-center">Qty</th>
                            <th class="px-6 py-4 font-medium">Est. Cost</th>
                            <th class="px-6 py-4 font-medium">Date Requested</th>
                            <th class="px-6 py-4 font-medium text-center">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!requests.data.length">
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                No purchase requests found. Click "+ New Purchase Request" to initiate restock orders.
                            </td>
                        </tr>
                        <tr v-for="req in requests.data" :key="req.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-mono font-bold text-[#F8B803]">{{ req.request_number }}</td>
                            <td class="px-6 py-4 font-semibold text-gray-900">{{ req.product?.name || '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ req.supplier?.name || '—' }}</td>
                            <td class="px-6 py-4 text-center font-bold text-gray-900">{{ req.quantity }}</td>
                            <td class="px-6 py-4 font-medium text-gray-700">{{ formatCurrency(Number(req.estimated_cost)) }}</td>
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ new Date(req.requested_at).toLocaleDateString() }}</td>
                            <td class="px-6 py-4 text-center">
                                <span :class="[
                                    'px-2.5 py-1 rounded-full text-xs font-semibold border inline-flex items-center gap-1',
                                    req.status === 'Requested' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                                    req.status === 'Ordered' ? 'bg-[#F8B803]/10 text-[#92400e] border-[#F8B803]/20' :
                                    req.status === 'Delivered' ? 'bg-green-50 text-green-700 border-green-200' :
                                    'bg-red-50 text-red-700 border-red-200'
                                ]">
                                    {{ req.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <select
                                        :value="req.status"
                                        @change="updateStatus(req, ($event.target as HTMLSelectElement).value)"
                                        class="bg-white border border-gray-200 rounded-lg px-2.5 py-1 text-xs font-medium text-gray-700 focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] shadow-xs cursor-pointer"
                                    >
                                        <option value="Requested">Requested</option>
                                        <option value="Ordered">Ordered</option>
                                        <option value="Delivered">Delivered</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                    <button
                                        v-if="req.status === 'Requested'"
                                        type="button"
                                        @click="openDeleteModal(req)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 border border-red-200 rounded-lg transition-colors cursor-pointer"
                                        title="Delete purchase request"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="requests.total > requests.data.length" class="p-4 flex items-center justify-between border-t border-gray-100">
                <span class="text-xs text-gray-500">
                    Showing {{ requests.data.length }} of {{ requests.total }} requests
                </span>
                <div class="flex gap-1">
                    <button
                        v-for="(link, i) in requests.links"
                        :key="i"
                        :disabled="!link.url || link.active"
                        @click="link.url && router.visit(link.url, { preserveScroll: true })"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 text-xs rounded-lg border transition-colors',
                            link.active ? 'bg-[#F8B803] text-white border-[#F8B803] font-bold' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Create Request Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900">New Purchase Request</h3>
                <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <form @submit.prevent="submitRequest" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-xs font-semibold mb-1">Item to Order *</label>
                    <select v-model="form.product_id" required class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm">
                        <option v-for="p in products" :key="p.id" :value="p.id">
                            {{ p.name }} (Current: {{ p.stock }})
                        </option>
                    </select>
                    <span v-if="form.errors.product_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.product_id }}</span>
                </div>
                <div>
                    <label class="block text-gray-700 text-xs font-semibold mb-1">Supplier *</label>
                    <select v-model="form.supplier_id" required class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm">
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <span v-if="form.errors.supplier_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.supplier_id }}</span>
                </div>
                <div>
                    <label class="block text-gray-700 text-xs font-semibold mb-1">Order Quantity *</label>
                    <input v-model.number="form.quantity" type="number" min="1" required class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                    <span v-if="form.errors.quantity" class="text-xs text-red-500 mt-1 block">{{ form.errors.quantity }}</span>
                </div>
                <div>
                    <label class="block text-gray-700 text-xs font-semibold mb-1">Notes / Instructions</label>
                    <textarea v-model="form.notes" rows="2" class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" placeholder="PO reference or delivery instructions..."></textarea>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-2 border-t border-gray-100">
                    <SpecularButton
                        type="button"
                        variant="secondary"
                        size="md"
                        :radius="10"
                        @click="showCreateModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        type="submit"
                        variant="brand"
                        size="md"
                        :radius="10"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Submitting...' : 'Submit Request' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Purchase Request</h3>
            <p class="text-gray-600 text-sm mb-6">Are you sure you want to cancel and delete request <span class="font-semibold text-gray-900">{{ selectedRequest?.request_number }}</span>?</p>
            <div class="flex justify-end gap-3">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showDeleteModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    variant="destructive"
                    size="md"
                    :radius="10"
                    @click="confirmDelete"
                >
                    Delete Request
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
