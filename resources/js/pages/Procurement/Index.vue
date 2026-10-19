<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ShoppingCart, Plus, CheckCircle2, RefreshCw, Trash2, Clock, AlertCircle } from '@lucide/vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';

interface ProcurementItem {
    id: string; // PR-10045
    db_id: number;
    item: string;
    supplier: string;
    qty: number;
    date: string;
    status: string;
    notes?: string;
}

const props = defineProps<{
    requests: ProcurementItem[];
    suppliers?: Array<{ id: number; name: string }>;
    products?: Array<{ id: number; name: string; sku: string }>;
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || '');

const getStatusClass = (status: string) => {
    if (status === 'Requested') return 'bg-blue-50 text-blue-700 border-blue-200';
    if (status === 'Ordered') return 'bg-amber-50 text-amber-700 border-amber-200';
    if (status === 'Delivered') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    return 'bg-red-50 text-red-700 border-red-200';
};

const showCreateModal = ref(false);
const showDeleteModal = ref(false);
const selectedRequest = ref<ProcurementItem | null>(null);

const form = useForm({
    item: '',
    supplier: '',
    qty: 50,
    product_id: '' as any,
    supplier_id: '' as any,
    notes: '',
});

const onProductSelect = (event: Event) => {
    const prodId = (event.target as HTMLSelectElement).value;
    const prod = props.products?.find(p => p.id === Number(prodId));
    if (prod) {
        form.item = prod.name;
    }
};

const onSupplierSelect = (event: Event) => {
    const supId = (event.target as HTMLSelectElement).value;
    const sup = props.suppliers?.find(s => s.id === Number(supId));
    if (sup) {
        form.supplier = sup.name;
    }
};

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    if (props.products && props.products.length > 0) {
        form.product_id = props.products[0].id;
        form.item = props.products[0].name;
    }
    if (props.suppliers && props.suppliers.length > 0) {
        form.supplier_id = props.suppliers[0].id;
        form.supplier = props.suppliers[0].name;
    }
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

const cycleStatus = (req: ProcurementItem) => {
    const statuses = ['Requested', 'Ordered', 'Delivered', 'Cancelled'];
    const currentIndex = statuses.indexOf(req.status);
    const nextStatus = statuses[(currentIndex + 1) % statuses.length];

    router.patch(`/${teamSlug.value}/procurement/${req.db_id}/status`, {
        status: nextStatus,
    }, {
        preserveScroll: true,
    });
};

const openDeleteModal = (req: ProcurementItem) => {
    selectedRequest.value = req;
    showDeleteModal.value = true;
};

const submitDelete = () => {
    if (!selectedRequest.value) return;
    router.delete(`/${teamSlug.value}/procurement/${selectedRequest.value.db_id}`, {
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

    <div class="workspace-page p-6 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                    <ShoppingCart class="w-8 h-8 text-[#4b5320]" />
                    <span>Procurement Monitoring</span>
                </h1>
                <p class="text-gray-500 mt-1">Track purchase requests, vendor deliveries, and automated stock replenishment.</p>
            </div>
            <SpecularButton 
                variant="brand"
                size="md"
                :radius="10"
                class="flex items-center gap-2"
                @click="openCreateModal"
            >
                <Plus class="w-4 h-4" />
                <span>+ New Purchase Request</span>
            </SpecularButton>
        </div>

        <!-- Procurement Table -->
        <div class="rounded-2xl bg-white border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/70 text-gray-600 text-xs font-semibold uppercase tracking-wider">
                            <th class="py-3.5 px-6">Request ID</th>
                            <th class="py-3.5 px-6">Item / Electrical Supply</th>
                            <th class="py-3.5 px-6">Supplier Vendor</th>
                            <th class="py-3.5 px-6 text-center">Qty</th>
                            <th class="py-3.5 px-6">Date Requested</th>
                            <th class="py-3.5 px-6 text-center">Status (Click to Advance)</th>
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <tr v-if="requests.length === 0">
                            <td colspan="7" class="text-center py-10 text-gray-500">
                                No procurement requests on record.
                            </td>
                        </tr>
                        <tr 
                            v-for="req in requests" 
                            :key="req.id" 
                            class="hover:bg-gray-50/80 transition-colors"
                        >
                            <td class="py-4 px-6 text-gray-900 font-bold text-xs">{{ req.id }}</td>
                            <td class="py-4 px-6 text-gray-900 font-medium">{{ req.item }}</td>
                            <td class="py-4 px-6 text-gray-600">{{ req.supplier }}</td>
                            <td class="py-4 px-6 text-center font-bold text-gray-900">{{ req.qty }}</td>
                            <td class="py-4 px-6 text-gray-500 text-xs">{{ req.date }}</td>
                            <td class="py-4 px-6 text-center">
                                <button 
                                    @click="cycleStatus(req)"
                                    :class="getStatusClass(req.status)"
                                    class="px-3 py-1 rounded-full text-xs font-semibold border inline-flex items-center gap-1.5 hover:opacity-85 transition-opacity"
                                    title="Click to cycle status"
                                >
                                    <RefreshCw class="w-3 h-3" />
                                    <span>{{ req.status }}</span>
                                </button>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <button 
                                    @click="openDeleteModal(req)" 
                                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Delete Request"
                                >
                                    <Trash2 class="w-4 h-4 inline" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Request Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-1">New Purchase Request</h3>
            <p class="text-xs text-gray-500 mb-5">Submit a procurement order to restock electrical materials.</p>

            <form @submit.prevent="submitRequest" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Select Catalog Item</label>
                    <select 
                        v-model="form.product_id" 
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm"
                        @change="onProductSelect"
                    >
                        <option value="">Custom Item (Enter below)</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Item Name *</label>
                    <input 
                        v-model="form.item" 
                        type="text" 
                        required 
                        placeholder="e.g. THHN Stranded Wire 3.5mm²"
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm"
                    />
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Supplier Vendor</label>
                    <select 
                        v-model="form.supplier_id" 
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm mb-2"
                        @change="onSupplierSelect"
                    >
                        <option value="">Custom Vendor (Enter below)</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <input 
                        v-model="form.supplier" 
                        type="text" 
                        required 
                        placeholder="e.g. ElectroTech Industries"
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm"
                    />
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Quantity *</label>
                    <input 
                        v-model.number="form.qty" 
                        type="number" 
                        min="1" 
                        required 
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <SpecularButton
                        variant="secondary"
                        size="md"
                        :radius="10"
                        type="button"
                        @click="showCreateModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        type="submit"
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
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to delete purchase request <span class="font-semibold text-gray-900">{{ selectedRequest?.id }}</span>?
            </p>
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
                    @click="submitDelete"
                >
                    Delete Request
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
