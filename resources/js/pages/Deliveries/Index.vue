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
    Eye
} from '@lucide/vue';

interface DeliveryItem {
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
    deliveries: {
        data: DeliveryItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    filters?: {
        search?: string;
        status?: string;
        supplier_id?: string | number;
        unit?: string;
        date_from?: string;
        date_to?: string;
    };
    metrics: {
        totalDeliveries: number;
        totalQuantityDelivered: number;
        linkedPosCount: number;
        deliveredCount: number;
    };
    products: ProductItem[];
    suppliers: SupplierItem[];
    purchaseOrders: PurchaseOrderItem[];
}>();

const page = usePage();
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

// Notification State
const notificationMessage = ref('');
const notificationType = ref<'success' | 'error' | 'info'>('success');
const showNotification = (msg: string, type: 'success' | 'error' | 'info' = 'success') => {
    notificationMessage.value = msg;
    notificationType.value = type;
    setTimeout(() => {
        notificationMessage.value = '';
    }, 4500);
};

if ((page.props as any).flash?.success) {
    showNotification((page.props as any).flash.success, 'success');
}

const formatDate = (dateStr: string) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${mm}-${dd}-${yyyy}`;
};

// Search & Filter state
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || 'All');
const selectedSupplier = ref(
    props.filters?.supplier_id !== undefined && props.filters?.supplier_id !== null && String(props.filters?.supplier_id) !== ''
        ? String(props.filters?.supplier_id) 
        : 'All'
);
const selectedUnit = ref(props.filters?.unit || 'All');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
const applyFilter = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            `/${teamSlug.value}/deliveries`,
            {
                search: search.value || undefined,
                status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
                supplier_id: selectedSupplier.value !== 'All' ? selectedSupplier.value : undefined,
                unit: selectedUnit.value !== 'All' ? selectedUnit.value : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = 'All';
    selectedSupplier.value = 'All';
    selectedUnit.value = 'All';
    applyFilter();
};

const hasActiveFilters = computed(() => {
    return !!search.value || 
        selectedStatus.value !== 'All' || 
        selectedSupplier.value !== 'All' || 
        selectedUnit.value !== 'All';
});

// ==========================================
// CREATE DELIVERY MODAL LOGIC
// ==========================================
const showCreateModal = ref(false);
const selectedProduct = ref<string>('');
const isCustomDescription = computed(() => selectedProduct.value === '__others__');

const selectedPoChoice = ref<string>('');
const isCustomPo = computed(() => selectedPoChoice.value === '__others__');

const form = useForm({
    delivery_date: new Date().toISOString().substring(0, 10),
    delivery_number: '',
    purchase_order_number: '',
    purchase_order_id: null as number | null,
    quantity: 1,
    unit: 'PCS',
    description: '',
    supplier_id: null as number | null,
    status: 'Delivered',
    notes: '',
});

const onProductChange = () => {
    if (selectedProduct.value === '__others__') {
        form.description = '';
    } else {
        const prod = props.products.find(p => String(p.id) === selectedProduct.value);
        if (prod) {
            form.description = prod.name;
        }
    }
};

const onPoChange = () => {
    if (selectedPoChoice.value === '__others__') {
        form.purchase_order_id = null;
        form.purchase_order_number = '';
    } else if (selectedPoChoice.value === '') {
        form.purchase_order_id = null;
        form.purchase_order_number = '';
    } else {
        const po = props.purchaseOrders.find(p => String(p.id) === selectedPoChoice.value);
        if (po) {
            form.purchase_order_id = po.id;
            form.purchase_order_number = po.po_number;
            if (po.unit_of_measure) {
                form.unit = po.unit_of_measure;
            }
            if (po.supplier_id) {
                form.supplier_id = po.supplier_id;
            }
            if (po.description && !form.description) {
                form.description = po.description;
            }
        }
    }
};

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    form.delivery_date = new Date().toISOString().substring(0, 10);
    form.delivery_number = '';
    form.purchase_order_number = '';
    form.purchase_order_id = null;
    form.quantity = 1;
    form.unit = 'PCS';
    form.status = 'Delivered';

    if (props.products.length > 0) {
        selectedProduct.value = String(props.products[0].id);
        form.description = props.products[0].name;
    } else {
        selectedProduct.value = '__others__';
        form.description = '';
    }

    if (props.purchaseOrders.length > 0) {
        selectedPoChoice.value = String(props.purchaseOrders[0].id);
        onPoChange();
    } else {
        selectedPoChoice.value = '';
    }

    showCreateModal.value = true;
};

const submitDelivery = () => {
    form.post(`/${teamSlug.value}/deliveries`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
            showNotification('Delivery record created successfully!', 'success');
        },
    });
};

// ==========================================
// DELETE DELIVERY MODAL LOGIC
// ==========================================
const showDeleteModal = ref(false);
const selectedDelivery = ref<DeliveryItem | null>(null);
const isDeleting = ref(false);

const openDeleteModal = (item: DeliveryItem) => {
    selectedDelivery.value = item;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!selectedDelivery.value || isDeleting.value) return;
    isDeleting.value = true;
    router.delete(`/${teamSlug.value}/deliveries/${selectedDelivery.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedDelivery.value = null;
            isDeleting.value = false;
            showNotification('Delivery record deleted successfully.', 'success');
        },
        onError: () => {
            isDeleting.value = false;
            showNotification('Failed to delete delivery record. Please try again.', 'error');
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

// ==========================================
// CSV IMPORT MODAL LOGIC
// ==========================================
const showImportModal = ref(false);
const importFile = ref<File | null>(null);
const isValidatingCsv = ref(false);
const isImportingCsv = ref(false);
const csvValidationResult = ref<any>(null);
const csvError = ref('');

const openImportModal = () => {
    importFile.value = null;
    csvValidationResult.value = null;
    csvError.value = '';
    showImportModal.value = true;
};

const handleFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        importFile.value = input.files[0];
        validateCsv();
    }
};

const validateCsv = async () => {
    if (!importFile.value) return;
    isValidatingCsv.value = true;
    csvError.value = '';

    const formData = new FormData();
    formData.append('file', importFile.value);
    formData.append('execute', '0');

    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    try {
        const response = await fetch(`/${teamSlug.value}/deliveries/import-csv`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            csvValidationResult.value = data;
        } else {
            csvError.value = data.message || 'Failed to validate CSV structure.';
            csvValidationResult.value = null;
        }
    } catch (err: any) {
        csvError.value = err.message || 'Failed to process CSV file.';
        csvValidationResult.value = null;
    } finally {
        isValidatingCsv.value = false;
    }
};

const downloadSampleCsv = () => {
    const headers = 'Date,Delivery Number,Purchase Order Number,Quantity,Unit,Description\n';
    const sampleRows = [
        '10-25-2026,DEL-102526-001,4000005472,100,PCS,"0.68mm Stainless Steel Conduit Rod 2m"',
        '10-25-2026,DEL-102526-002,4000005473,40,BOX,"Circuit Breaker 2P 40A Rail Mounted"',
        '10-25-2026,DEL-102526-003,4000005474,250,MTR,"THHN Building Wire 3.5mm Stranded"'
    ].join('\n');

    const blob = new Blob([headers + sampleRows], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'mr_pride_deliveries_template.csv';
    link.click();
    URL.revokeObjectURL(link.href);
    showNotification('Sample Delivery CSV downloaded with required headers!', 'info');
};

const executeCsvImport = () => {
    if (!importFile.value || !csvValidationResult.value || csvValidationResult.value.summary?.valid_count === 0) {
        showNotification('No valid rows available to import.', 'error');
        return;
    }

    isImportingCsv.value = true;
    const formData = new FormData();
    formData.append('file', importFile.value);
    formData.append('execute', '1');

    router.post(`/${teamSlug.value}/deliveries/import-csv`, formData as any, {
        onSuccess: () => {
            showImportModal.value = false;
            isImportingCsv.value = false;
            showNotification('Delivery records imported successfully!', 'success');
        },
        onError: (err) => {
            isImportingCsv.value = false;
            const msg = Object.values(err)[0] as string;
            showNotification(msg || 'Failed to import CSV.', 'error');
        }
    });
};
</script>

<template>
    <Head title="Delivery Module" />

    <!-- Toast Notification -->
    <div 
        v-if="notificationMessage"
        class="fixed top-5 right-5 z-[100] max-w-md px-4 py-3 rounded-xl shadow-lg border text-sm flex items-center gap-3 animate-in fade-in slide-in-from-top-2 duration-200"
        :class="[
            notificationType === 'success' ? 'bg-emerald-50 text-emerald-900 border-emerald-200' :
            notificationType === 'error' ? 'bg-rose-50 text-rose-900 border-rose-200' :
            'bg-blue-50 text-blue-900 border-blue-200'
        ]"
    >
        <CheckCircle2 v-if="notificationType === 'success'" class="w-5 h-5 text-emerald-600 shrink-0" />
        <AlertCircle v-else-if="notificationType === 'error'" class="w-5 h-5 text-rose-600 shrink-0" />
        <Clock v-else class="w-5 h-5 text-blue-600 shrink-0" />
        <span class="font-medium">{{ notificationMessage }}</span>
        <button @click="notificationMessage = ''" class="ml-auto text-gray-400 hover:text-gray-700">&times;</button>
    </div>

    <div class="workspace-page p-4 sm:p-6 max-w-[1600px] mx-auto">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Delivery Management</h1>
                <p class="text-gray-500 mt-1">Track inbound deliveries, supplier shipments, and purchase order fulfillment.</p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                <button
                    type="button"
                    @click="openImportModal"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 hover:border-gray-300 shadow-xs transition-colors cursor-pointer"
                >
                    <UploadCloud class="w-4 h-4 text-emerald-600" />
                    <span>Import CSV</span>
                </button>
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="openCreateModal"
                >
                    <Plus class="w-4 h-4 mr-1.5 inline" />
                    New Delivery
                </SpecularButton>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total Deliveries</span>
                    <Truck class="w-4 h-4 text-blue-600" />
                </div>
                <div class="text-2xl font-black text-gray-900">{{ metrics.totalDeliveries || 0 }}</div>
                <div class="text-xs text-gray-500 mt-1">Recorded vendor drop-offs</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total Quantity Received</span>
                    <Package class="w-4 h-4 text-emerald-600" />
                </div>
                <div class="text-2xl font-black text-emerald-700">{{ metrics.totalQuantityDelivered?.toLocaleString() || 0 }}</div>
                <div class="text-xs text-gray-500 mt-1">Total units accepted</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Linked Purchase Orders</span>
                    <Receipt class="w-4 h-4 text-[#F8B803]" />
                </div>
                <div class="text-2xl font-black text-[#B45309]">{{ metrics.linkedPosCount || 0 }}</div>
                <div class="text-xs text-gray-500 mt-1">Unique POs fulfilled</div>
            </div>
        </div>

        <!-- Search & Filters Bar (Beside search bar) -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 mb-6 bg-white p-3 rounded-2xl border border-gray-200 shadow-xs">
            <!-- Search Bar -->
            <div class="relative flex-1 max-w-md">
                <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    @input="applyFilter"
                    type="text"
                    placeholder="Search by delivery #, PO #, description, supplier..."
                    class="w-full pl-10 pr-4 py-2 bg-gray-50/80 hover:bg-white focus:bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-xs transition-colors"
                />
            </div>

            <!-- Filters beside search bar -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Status Filter -->
                <div class="relative min-w-[140px]">
                    <select
                        v-model="selectedStatus"
                        @change="applyFilter"
                        class="w-full bg-gray-50/80 hover:bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#F8B803] focus:bg-white shadow-xs cursor-pointer transition-colors"
                    >
                        <option value="All">All Statuses</option>
                        <option value="Delivered">Delivered</option>
                        <option value="In Transit">In Transit</option>
                        <option value="Pending Inspection">Pending Inspection</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Supplier Filter -->
                <div class="relative min-w-[150px]">
                    <select
                        v-model="selectedSupplier"
                        @change="applyFilter"
                        class="w-full bg-gray-50/80 hover:bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#F8B803] focus:bg-white shadow-xs cursor-pointer transition-colors"
                    >
                        <option value="All">All Suppliers</option>
                        <option v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                    </select>
                </div>

                <!-- Unit Filter -->
                <div class="relative min-w-[110px]">
                    <select
                        v-model="selectedUnit"
                        @change="applyFilter"
                        class="w-full bg-gray-50/80 hover:bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#F8B803] focus:bg-white shadow-xs cursor-pointer transition-colors uppercase"
                    >
                        <option value="All">All Units</option>
                        <option value="PCS">PCS</option>
                        <option value="BOX">BOX</option>
                        <option value="MTR">MTR</option>
                        <option value="KG">KG</option>
                        <option value="SET">SET</option>
                        <option value="ROLL">ROLL</option>
                        <option value="UNIT">UNIT</option>
                    </select>
                </div>

                <!-- Reset Filters Button -->
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    @click="resetFilters"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 hover:text-red-600 bg-gray-100 hover:bg-red-50 border border-gray-200 hover:border-red-200 rounded-xl transition-all cursor-pointer shadow-xs"
                    title="Reset all search and filter conditions"
                >
                    <XCircle class="w-3.5 h-3.5" />
                    <span>Reset Filters</span>
                </button>
            </div>
        </div>

        <!-- Deliveries Table (Exact Headers: Date, Delivery Number, Purchase Order Number, Quantity, Unit, Description, Actions) -->
        <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-sm flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Date</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Delivery Number</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Purchase Order Number</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-center tracking-wider">Quantity</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-center tracking-wider">Unit</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Description</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-right tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!deliveries?.data?.length">
                            <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                <div class="max-w-md mx-auto space-y-2">
                                    <Truck class="w-8 h-8 mx-auto text-gray-300" />
                                    <p class="font-medium text-gray-700">No delivery records found</p>
                                    <p class="text-xs text-gray-400">Click "+ New Delivery" or use "Import CSV" to add delivery receipts.</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="d in deliveries.data" :key="d.id" class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-6 py-4 text-gray-700 font-medium whitespace-nowrap">{{ formatDate(d.delivery_date) }}</td>
                            <td class="px-6 py-4 font-mono font-bold text-emerald-700 whitespace-nowrap">{{ d.delivery_number }}</td>
                            <td class="px-6 py-4 font-mono font-semibold text-blue-700 whitespace-nowrap">
                                {{ d.purchase_order_number || d.purchase_order?.po_number || '—' }}
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-gray-900">{{ d.quantity }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 text-xs font-bold rounded-md bg-gray-100 text-gray-700 uppercase">
                                    {{ d.unit || 'PCS' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-900 font-medium max-w-md truncate">{{ d.description }}</td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    @click.stop="openDeleteModal(d)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:text-white hover:bg-rose-600 bg-rose-50 border border-rose-200 hover:border-rose-600 rounded-lg transition-all duration-150 cursor-pointer shadow-2xs hover:shadow-sm"
                                    title="Delete delivery receipt"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Delete</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="deliveries && deliveries.total > deliveries.data.length" class="p-4 flex items-center justify-between border-t border-gray-100">
                <span class="text-xs text-gray-500">
                    Showing {{ deliveries.data.length }} of {{ deliveries.total }} delivery records
                </span>
                <div class="flex gap-1">
                    <button
                        v-for="(link, i) in deliveries.links"
                        :key="i"
                        :disabled="!link.url || link.active"
                        @click="link.url && router.visit(link.url, { preserveScroll: true })"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 text-xs rounded-lg border transition-colors',
                            link.active ? 'bg-[#F8B803] text-gray-900 border-[#F8B803] font-bold' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: CREATE DELIVERY -->
    <!-- ======================================================== -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">New Delivery Record</h3>
                    <p class="text-xs text-gray-500">Record incoming shipment details from supplier</p>
                </div>
                <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-xl cursor-pointer">&times;</button>
            </div>

            <form @submit.prevent="submitDelivery" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date *</label>
                        <input
                            v-model="form.delivery_date"
                            type="date"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="form.errors.delivery_date" class="text-xs text-red-500 mt-0.5 block">{{ form.errors.delivery_date }}</span>
                    </div>

                    <!-- Delivery Number -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Delivery Number
                            <span class="text-gray-400 font-normal">(Auto if blank)</span>
                        </label>
                        <input
                            v-model="form.delivery_number"
                            type="text"
                            placeholder="e.g. DEL-102526-001"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none font-mono"
                        />
                        <span v-if="form.errors.delivery_number" class="text-xs text-red-500 mt-0.5 block">{{ form.errors.delivery_number }}</span>
                    </div>
                </div>

                <!-- Purchase Order Number Selector -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Purchase Order Number</label>
                    <select
                        v-model="selectedPoChoice"
                        @change="onPoChange"
                        class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none bg-white font-mono"
                    >
                        <option value="">-- No PO / Unlinked --</option>
                        <option v-for="po in purchaseOrders" :key="po.id" :value="String(po.id)">
                            PO #{{ po.po_number }} — {{ po.description }} (Qty: {{ po.quantity }})
                        </option>
                        <option value="__others__">-- Others (Type Custom PO #) --</option>
                    </select>

                    <div v-if="isCustomPo" class="mt-2.5 animate-in fade-in slide-in-from-top-1 duration-150">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Custom Purchase Order No.</label>
                        <input
                            v-model="form.purchase_order_number"
                            type="text"
                            placeholder="e.g. 4000005472"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none font-mono"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Quantity -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Quantity *</label>
                        <input
                            v-model.number="form.quantity"
                            type="number"
                            min="1"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="form.errors.quantity" class="text-xs text-red-500 mt-0.5 block">{{ form.errors.quantity }}</span>
                    </div>

                    <!-- Unit -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit *</label>
                        <input
                            v-model="form.unit"
                            type="text"
                            placeholder="e.g. PCS, BOX, MTR, KG"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none uppercase"
                        />
                        <span v-if="form.errors.unit" class="text-xs text-red-500 mt-0.5 block">{{ form.errors.unit }}</span>
                    </div>
                </div>

                <!-- Description / Product Selector with Others -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description / Product *</label>
                    <select
                        v-model="selectedProduct"
                        @change="onProductChange"
                        class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none bg-white"
                    >
                        <option value="" disabled>-- Select a Product --</option>
                        <option v-for="p in products" :key="p.id" :value="String(p.id)">
                            {{ p.name }} (SKU: {{ p.sku }})
                        </option>
                        <option value="__others__">-- Others (Type Custom Description) --</option>
                    </select>

                    <div v-if="isCustomDescription" class="mt-2.5 animate-in fade-in slide-in-from-top-1 duration-150">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Custom Description *</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            required
                            placeholder="Type custom delivery description..."
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        ></textarea>
                    </div>
                    <span v-if="form.errors.description" class="text-xs text-red-500 mt-0.5 block">{{ form.errors.description }}</span>
                </div>

                <!-- Supplier (Optional) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Supplier (Optional)</label>
                    <select
                        v-model="form.supplier_id"
                        class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none bg-white"
                    >
                        <option :value="null">-- None / Direct Drop-off --</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <!-- Modal Actions -->
                <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
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
                        {{ form.processing ? 'Saving...' : 'Save Delivery' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: IMPORT DELIVERIES CSV -->
    <!-- ======================================================== -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Import Deliveries CSV</h3>
                    <p class="text-xs text-gray-500">Headers: <code class="bg-gray-100 px-1 py-0.5 rounded font-bold text-gray-800">Date, Delivery Number, Purchase Order Number, Quantity, Unit, Description</code></p>
                </div>
                <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600 text-xl cursor-pointer">&times;</button>
            </div>

            <!-- Upload Area & Sample Template -->
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between p-4 bg-emerald-50/60 border border-emerald-100 rounded-xl mb-4">
                <div>
                    <h4 class="text-sm font-bold text-gray-900">Download Template</h4>
                    <p class="text-xs text-gray-600">Pre-formatted CSV with required column headers</p>
                </div>
                <button
                    type="button"
                    @click="downloadSampleCsv"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-emerald-200 text-emerald-900 hover:bg-emerald-50 shadow-xs cursor-pointer whitespace-nowrap"
                >
                    <Download class="w-3.5 h-3.5" />
                    Download Sample CSV
                </button>
            </div>

            <!-- File Input -->
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-700 mb-2">Select CSV File (.csv, .txt)</label>
                <input
                    type="file"
                    accept=".csv,.txt"
                    @change="handleFileChange"
                    class="w-full text-xs text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#F8B803] file:text-gray-900 hover:file:opacity-90 cursor-pointer border border-gray-200 rounded-xl p-2"
                />
            </div>

            <!-- Loading State -->
            <div v-if="isValidatingCsv" class="py-6 text-center text-gray-500 text-xs">
                <Clock class="w-5 h-5 mx-auto animate-spin mb-1 text-[#F8B803]" />
                Validating CSV rows...
            </div>

            <!-- Error Message -->
            <div v-if="csvError" class="p-3 mb-4 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
                <AlertCircle class="w-4 h-4 shrink-0 text-red-500 mt-0.5" />
                <span>{{ csvError }}</span>
            </div>

            <!-- Preview Results -->
            <div v-if="csvValidationResult" class="space-y-4">
                <div class="flex gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                    <div>
                        <span class="text-gray-500">Total Rows:</span>
                        <span class="ml-1 font-bold text-gray-900">{{ csvValidationResult.summary.total_rows }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Valid:</span>
                        <span class="ml-1 font-bold text-emerald-600">{{ csvValidationResult.summary.valid_count }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Invalid:</span>
                        <span class="ml-1 font-bold text-red-600">{{ csvValidationResult.summary.invalid_count }}</span>
                    </div>
                </div>

                <!-- Preview Table -->
                <div v-if="csvValidationResult.valid_rows?.length" class="max-h-60 overflow-y-auto border border-gray-200 rounded-xl">
                    <table class="w-full text-left text-xs text-gray-600">
                        <thead class="bg-gray-50 font-bold text-gray-700 border-b border-gray-200 sticky top-0">
                            <tr>
                                <th class="p-2">Date</th>
                                <th class="p-2">Delivery Number</th>
                                <th class="p-2">Purchase Order Number</th>
                                <th class="p-2 text-center">Quantity</th>
                                <th class="p-2 text-center">Unit</th>
                                <th class="p-2">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="r in csvValidationResult.valid_rows.slice(0, 10)" :key="r.row_number">
                                <td class="p-2 whitespace-nowrap">{{ r.display_date || r.date }}</td>
                                <td class="p-2 font-mono font-bold text-emerald-700 whitespace-nowrap">{{ r.delivery_number }}</td>
                                <td class="p-2 font-mono text-blue-700 whitespace-nowrap">{{ r.purchase_order_number || '—' }}</td>
                                <td class="p-2 text-center font-bold">{{ r.quantity }}</td>
                                <td class="p-2 text-center font-bold">{{ r.unit }}</td>
                                <td class="p-2 max-w-xs truncate">{{ r.description }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end gap-3 mt-6 pt-3 border-t border-gray-100">
                <SpecularButton
                    type="button"
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showImportModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    variant="brand"
                    size="md"
                    :radius="10"
                    :disabled="isImportingCsv || !csvValidationResult || csvValidationResult.summary?.valid_count === 0"
                    @click="executeCsvImport"
                >
                    {{ isImportingCsv ? 'Importing...' : `Import ${csvValidationResult?.summary?.valid_count || ''} Deliveries` }}
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: DELETE DELIVERY -->
    <!-- ======================================================== -->
    <div 
        v-if="showDeleteModal" 
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
        @click.self="!isDeleting && (showDeleteModal = false)"
    >
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-4 border border-rose-100">
                <Trash2 class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Delivery Record</h3>
            <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                Are you sure you want to delete delivery receipt 
                <span class="font-bold text-gray-900 font-mono">{{ selectedDelivery?.delivery_number }}</span>
                <span v-if="selectedDelivery?.description" class="block text-xs text-gray-500 mt-1 truncate">
                    "{{ selectedDelivery?.description }}"
                </span>
                ? This action cannot be undone.
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton
                    type="button"
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showDeleteModal = false"
                    :disabled="isDeleting"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    variant="destructive"
                    size="md"
                    :radius="10"
                    @click="confirmDelete"
                    :disabled="isDeleting"
                >
                    <Trash2 v-if="!isDeleting" class="w-4 h-4 mr-1 inline" />
                    <span>{{ isDeleting ? 'Deleting...' : 'Delete Delivery' }}</span>
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
