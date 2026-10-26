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

interface CommercialInvoiceItem {
    id: number;
    invoice_date: string;
    invoice_number: string;
    quantity: number;
    description: string;
    unit_cost: number;
    total_cost: number;
    supplier_id?: number | null;
    status: string;
    notes?: string | null;
    supplier?: { id: number; name: string };
}

interface PurchaseOrderItem {
    id: number;
    po_date: string;
    po_number: string;
    quantity: number;
    item_sku?: string | null;
    description: string;
    unit_of_measure?: string | null;
    unit_cost: number;
    total_cost: number;
    supplier_id?: number | null;
    status: string;
    notes?: string | null;
    supplier?: { id: number; name: string };
}

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
    commercialInvoices?: {
        data: CommercialInvoiceItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    purchaseOrders?: {
        data: PurchaseOrderItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    requests: {
        data: PurchaseRequestItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    activeTab?: string;
    filters?: {
        search?: string;
        status?: string;
        supplier_id?: string | number;
        unit_of_measure?: string;
        date_from?: string;
        date_to?: string;
        tab?: string;
    };
    metrics: {
        totalRequests: number;
        requestedCount: number;
        orderedCount: number;
        deliveredCount: number;
        totalCommercialInvoices?: number;
        totalCommercialInvoiceAmount?: number;
        totalPurchaseOrders?: number;
        totalPurchaseOrderAmount?: number;
    };
    products: ProductItem[];
    suppliers: SupplierItem[];
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || (page.props as any).team?.slug || '');

// Notification state
const notificationMessage = ref('');
const notificationType = ref<'success' | 'error' | 'info'>('success');
const showNotification = (msg: string, type: 'success' | 'error' | 'info' = 'success') => {
    notificationMessage.value = msg;
    notificationType.value = type;
    setTimeout(() => {
        notificationMessage.value = '';
    }, 4500);
};

// Check session flash
if ((page.props as any).flash?.success) {
    showNotification((page.props as any).flash.success, 'success');
}

const formatCurrency = (amount: number | string) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(amount) || 0);
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '—';
    // Format as MONTH-DAY-YEAR e.g. 10-25-2026
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${mm}-${dd}-${yyyy}`;
};

// Tabs: 'commercial_invoices' | 'purchase_orders' | 'requests'
const activeTab = ref(props.activeTab || props.filters?.tab || 'commercial_invoices');

// Search & Filters state
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || 'All');
const selectedSupplier = ref(
    props.filters?.supplier_id !== undefined && props.filters?.supplier_id !== null && String(props.filters?.supplier_id) !== ''
        ? String(props.filters?.supplier_id) 
        : 'All'
);
const selectedUm = ref(props.filters?.unit_of_measure || 'All');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
const applyFilter = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            `/${teamSlug.value}/procurement`,
            {
                tab: activeTab.value,
                search: search.value || undefined,
                status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
                supplier_id: selectedSupplier.value !== 'All' ? selectedSupplier.value : undefined,
                unit_of_measure: activeTab.value === 'purchase_orders' && selectedUm.value !== 'All' ? selectedUm.value : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
};

const switchTab = (tab: string) => {
    activeTab.value = tab;
    search.value = '';
    selectedStatus.value = 'All';
    selectedSupplier.value = 'All';
    selectedUm.value = 'All';
    applyFilter();
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = 'All';
    selectedSupplier.value = 'All';
    selectedUm.value = 'All';
    applyFilter();
};

const hasActiveFilters = computed(() => {
    return !!search.value || 
        selectedStatus.value !== 'All' || 
        selectedSupplier.value !== 'All' || 
        (activeTab.value === 'purchase_orders' && selectedUm.value !== 'All');
});

// ==========================================
// 1. COMMERCIAL INVOICES LOGIC
// ==========================================
const showCreateCiModal = ref(false);
const ciForm = useForm({
    invoice_date: new Date().toISOString().substring(0, 10),
    invoice_number: '',
    quantity: 1,
    description: '',
    unit_cost: 0.00,
    supplier_id: null as number | null,
    notes: '',
});

const ciSelectedProduct = ref<string>('');
const isCiCustomDescription = computed(() => ciSelectedProduct.value === '__others__');

const onCiProductChange = () => {
    if (ciSelectedProduct.value === '__others__') {
        ciForm.description = '';
    } else {
        const prod = props.products.find(p => String(p.id) === ciSelectedProduct.value);
        if (prod) {
            ciForm.description = prod.name;
            if (prod.cost_price || prod.price) {
                ciForm.unit_cost = Number(prod.cost_price || prod.price);
            }
        }
    }
};

const openCreateCiModal = () => {
    ciForm.reset();
    ciForm.clearErrors();
    ciForm.invoice_date = new Date().toISOString().substring(0, 10);
    ciForm.invoice_number = '';
    ciForm.quantity = 1;
    ciForm.unit_cost = 0;
    if (props.products.length > 0) {
        ciSelectedProduct.value = String(props.products[0].id);
        ciForm.description = props.products[0].name;
        ciForm.unit_cost = Number(props.products[0].cost_price || props.products[0].price || 0);
    } else {
        ciSelectedProduct.value = '__others__';
        ciForm.description = '';
    }
    showCreateCiModal.value = true;
};

const submitCi = () => {
    ciForm.post(`/${teamSlug.value}/procurement/commercial-invoices`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateCiModal.value = false;
            ciForm.reset();
            showNotification('Commercial invoice added successfully!', 'success');
        },
    });
};

const showDeleteCiModal = ref(false);
const selectedCi = ref<CommercialInvoiceItem | null>(null);

const openDeleteCiModal = (ci: CommercialInvoiceItem) => {
    selectedCi.value = ci;
    showDeleteCiModal.value = true;
};

const confirmDeleteCi = () => {
    if (!selectedCi.value) return;
    router.delete(`/${teamSlug.value}/procurement/commercial-invoices/${selectedCi.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteCiModal.value = false;
            selectedCi.value = null;
            showNotification('Commercial invoice deleted successfully.', 'success');
        },
    });
};

// CI CSV Import
const showCiImportModal = ref(false);
const ciImportFile = ref<File | null>(null);
const isValidatingCiCsv = ref(false);
const isImportingCiCsv = ref(false);
const ciCsvValidationResult = ref<any>(null);
const ciCsvError = ref('');

const openCiImportModal = () => {
    ciImportFile.value = null;
    ciCsvValidationResult.value = null;
    ciCsvError.value = '';
    showCiImportModal.value = true;
};

const handleCiFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        ciImportFile.value = input.files[0];
        validateCiCsv();
    }
};

const validateCiCsv = async () => {
    if (!ciImportFile.value) return;
    isValidatingCiCsv.value = true;
    ciCsvError.value = '';

    const formData = new FormData();
    formData.append('file', ciImportFile.value);
    formData.append('execute', '0');

    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    try {
        const response = await fetch(`/${teamSlug.value}/procurement/commercial-invoices/import-csv`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            ciCsvValidationResult.value = data;
        } else {
            ciCsvError.value = data.message || 'Failed to validate CSV structure.';
            ciCsvValidationResult.value = null;
        }
    } catch (err: any) {
        ciCsvError.value = err.message || 'Failed to process CSV file.';
        ciCsvValidationResult.value = null;
    } finally {
        isValidatingCiCsv.value = false;
    }
};

const downloadSampleCiCsv = () => {
    const headers = 'DATE,COMMERCIAL INVOICE,QUANTITY,Description,UNIT COST\n';
    const sampleRows = [
        '10-25-2026,CI-035-0722,50,"Heavy Duty Electrical Conduit 20mm",120.00',
        '10-25-2026,CI-036-0722,15,"Industrial Circuit Breaker 3-Phase 100A",1450.00',
        '07-22-2022,CI-035-0722,100,"Single Gang Electrical Outlet Grounded",95.50'
    ].join('\n');

    const blob = new Blob([headers + sampleRows], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'mr_pride_commercial_invoices_template.csv';
    link.click();
    URL.revokeObjectURL(link.href);
    showNotification('Sample Commercial Invoice CSV downloaded with required headers!', 'info');
};

const executeCiCsvImport = () => {
    if (!ciImportFile.value || !ciCsvValidationResult.value || ciCsvValidationResult.value.summary?.valid_count === 0) {
        showNotification('No valid rows available to import.', 'error');
        return;
    }

    isImportingCiCsv.value = true;
    const formData = new FormData();
    formData.append('file', ciImportFile.value);
    formData.append('execute', '1');

    router.post(`/${teamSlug.value}/procurement/commercial-invoices/import-csv`, formData as any, {
        onSuccess: () => {
            showCiImportModal.value = false;
            isImportingCiCsv.value = false;
            showNotification('Commercial invoices imported successfully!', 'success');
        },
        onError: (err) => {
            isImportingCiCsv.value = false;
            const msg = Object.values(err)[0] as string;
            showNotification(msg || 'Failed to import CSV.', 'error');
        }
    });
};

// ==========================================
// 2. PURCHASE ORDERS LOGIC
// ==========================================
const showCreatePoModal = ref(false);
const poForm = useForm({
    po_date: new Date().toISOString().substring(0, 10),
    po_number: '',
    quantity: 1,
    item_sku: '',
    description: '',
    unit_of_measure: 'PCS',
    unit_cost: 0.00,
    supplier_id: null as number | null,
    notes: '',
});

const poSelectedProduct = ref<string>('');
const isPoCustomDescription = computed(() => poSelectedProduct.value === '__others__');

const onPoProductChange = () => {
    if (poSelectedProduct.value === '__others__') {
        poForm.description = '';
        poForm.item_sku = '';
    } else {
        const prod = props.products.find(p => String(p.id) === poSelectedProduct.value);
        if (prod) {
            poForm.description = prod.name;
            poForm.item_sku = prod.sku;
            if (prod.cost_price || prod.price) {
                poForm.unit_cost = Number(prod.cost_price || prod.price);
            }
        }
    }
};

const openCreatePoModal = () => {
    poForm.reset();
    poForm.clearErrors();
    poForm.po_date = new Date().toISOString().substring(0, 10);
    poForm.po_number = '';
    poForm.quantity = 1;
    poForm.unit_of_measure = 'PCS';
    poForm.unit_cost = 0;
    if (props.products.length > 0) {
        poSelectedProduct.value = String(props.products[0].id);
        poForm.description = props.products[0].name;
        poForm.item_sku = props.products[0].sku;
        poForm.unit_cost = Number(props.products[0].cost_price || props.products[0].price || 0);
    } else {
        poSelectedProduct.value = '__others__';
        poForm.description = '';
        poForm.item_sku = '';
    }
    showCreatePoModal.value = true;
};

const submitPo = () => {
    poForm.post(`/${teamSlug.value}/procurement/purchase-orders`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreatePoModal.value = false;
            poForm.reset();
            showNotification('Purchase order created successfully!', 'success');
        },
    });
};

const showDeletePoModal = ref(false);
const selectedPo = ref<PurchaseOrderItem | null>(null);

const openDeletePoModal = (po: PurchaseOrderItem) => {
    selectedPo.value = po;
    showDeletePoModal.value = true;
};

const confirmDeletePo = () => {
    if (!selectedPo.value) return;
    router.delete(`/${teamSlug.value}/procurement/purchase-orders/${selectedPo.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeletePoModal.value = false;
            selectedPo.value = null;
            showNotification('Purchase order deleted successfully.', 'success');
        },
    });
};

// PO CSV Import
const showPoImportModal = ref(false);
const poImportFile = ref<File | null>(null);
const isValidatingPoCsv = ref(false);
const isImportingPoCsv = ref(false);
const poCsvValidationResult = ref<any>(null);
const poCsvError = ref('');

const openPoImportModal = () => {
    poImportFile.value = null;
    poCsvValidationResult.value = null;
    poCsvError.value = '';
    showPoImportModal.value = true;
};

const handlePoFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        poImportFile.value = input.files[0];
        validatePoCsv();
    }
};

const validatePoCsv = async () => {
    if (!poImportFile.value) return;
    isValidatingPoCsv.value = true;
    poCsvError.value = '';

    const formData = new FormData();
    formData.append('file', poImportFile.value);
    formData.append('execute', '0');

    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    try {
        const response = await fetch(`/${teamSlug.value}/procurement/purchase-orders/import-csv`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            poCsvValidationResult.value = data;
        } else {
            poCsvError.value = data.message || 'Failed to validate CSV structure.';
            poCsvValidationResult.value = null;
        }
    } catch (err: any) {
        poCsvError.value = err.message || 'Failed to process CSV file.';
        poCsvValidationResult.value = null;
    } finally {
        isValidatingPoCsv.value = false;
    }
};

const downloadSamplePoCsv = () => {
    const headers = 'Date,Purchase Order No.,Quantity,ITEM/SKU Number,Description,U/M,Unit Cost\n';
    const sampleRows = [
        '10-25-2026,4000005472,100,068SSCR2,"0.68mm Stainless Steel Conduit Rod 2m",PCS,450.00',
        '10-25-2026,4000005473,40,BRK-40A,"Circuit Breaker 2P 40A Rail Mounted",BOX,320.00',
        '10-25-2026,4000005474,250,WIR-THHN,"THHN Building Wire 3.5mm Stranded",MTR,35.00'
    ].join('\n');

    const blob = new Blob([headers + sampleRows], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'mr_pride_purchase_orders_template.csv';
    link.click();
    URL.revokeObjectURL(link.href);
    showNotification('Sample Purchase Order CSV downloaded with required headers!', 'info');
};

const executePoCsvImport = () => {
    if (!poImportFile.value || !poCsvValidationResult.value || poCsvValidationResult.value.summary?.valid_count === 0) {
        showNotification('No valid rows available to import.', 'error');
        return;
    }

    isImportingPoCsv.value = true;
    const formData = new FormData();
    formData.append('file', poImportFile.value);
    formData.append('execute', '1');

    router.post(`/${teamSlug.value}/procurement/purchase-orders/import-csv`, formData as any, {
        onSuccess: () => {
            showPoImportModal.value = false;
            isImportingPoCsv.value = false;
            showNotification('Purchase orders imported successfully!', 'success');
        },
        onError: (err) => {
            isImportingPoCsv.value = false;
            const msg = Object.values(err)[0] as string;
            showNotification(msg || 'Failed to import CSV.', 'error');
        }
    });
};

// ==========================================
// 3. PURCHASE REQUESTS LOGIC
// ==========================================
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

// Action Confirmation Modal State
interface ConfirmActionOptions {
    title: string;
    description: string;
    details?: Array<{ label: string; value: string | number }>;
    confirmText?: string;
    action: () => void;
}

const showConfirmModal = ref(false);
const confirmOptions = ref<ConfirmActionOptions>({
    title: 'Confirm Action',
    description: 'Are you sure you want to proceed?',
    details: [],
    confirmText: 'Confirm & Submit',
    action: () => {},
});

const openConfirmModal = (options: ConfirmActionOptions) => {
    confirmOptions.value = {
        confirmText: 'Confirm & Submit',
        ...options,
    };
    showConfirmModal.value = true;
};

const handleConfirmedAction = () => {
    showConfirmModal.value = false;
    confirmOptions.value.action();
};

const promptCreateRequest = () => {
    form.clearErrors();
    if (!form.product_id) {
        form.setError('product_id', 'Please select an item to order.');
        return;
    }
    if (!form.supplier_id) {
        form.setError('supplier_id', 'Please select a supplier.');
        return;
    }
    if (!form.quantity || form.quantity < 1) {
        form.setError('quantity', 'Quantity must be at least 1.');
        return;
    }

    const prod = props.products.find(p => p.id === form.product_id);
    const sup = props.suppliers.find(s => s.id === form.supplier_id);

    openConfirmModal({
        title: 'Confirm Purchase Request',
        description: `Are you sure you want to submit this purchase request for ${form.quantity} units of "${prod?.name || 'Item'}"?`,
        details: [
            { label: 'Item to Order', value: prod ? `${prod.name} (${prod.sku})` : 'Selected Item' },
            { label: 'Supplier', value: sup?.name || 'Selected Supplier' },
            { label: 'Quantity', value: `${form.quantity} units` },
            { label: 'Notes', value: form.notes || 'None' },
        ],
        confirmText: 'Submit Request',
        action: submitRequest,
    });
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

    <!-- Notification Toast -->
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
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Procurement & Sourcing</h1>
                <p class="text-gray-500 mt-1">Manage Commercial Invoices, Purchase Orders, and Internal Restock Requests.</p>
            </div>

            <!-- Contextual Actions -->
            <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                <template v-if="activeTab === 'commercial_invoices'">
                    <button
                        type="button"
                        @click="openCiImportModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 hover:border-gray-300 shadow-xs transition-colors cursor-pointer"
                    >
                        <UploadCloud class="w-4 h-4 text-emerald-600" />
                        <span>Import CSV</span>
                    </button>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        @click="openCreateCiModal"
                    >
                        <Plus class="w-4 h-4 mr-1.5 inline" />
                        New Commercial Invoice
                    </SpecularButton>
                </template>

                <template v-else-if="activeTab === 'purchase_orders'">
                    <button
                        type="button"
                        @click="openPoImportModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 hover:border-gray-300 shadow-xs transition-colors cursor-pointer"
                    >
                        <UploadCloud class="w-4 h-4 text-emerald-600" />
                        <span>Import CSV</span>
                    </button>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        @click="openCreatePoModal"
                    >
                        <Plus class="w-4 h-4 mr-1.5 inline" />
                        New Purchase Order
                    </SpecularButton>
                </template>

                <template v-else>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        @click="openCreateModal"
                    >
                        <Plus class="w-4 h-4 mr-1.5 inline" />
                        New Purchase Request
                    </SpecularButton>
                </template>
            </div>
        </div>

        <!-- Tab Switcher -->
        <div class="flex items-center gap-2 border-b border-gray-200 pb-3 mb-6 overflow-x-auto">
            <button
                type="button"
                @click="switchTab('commercial_invoices')"
                :class="[
                    'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all cursor-pointer whitespace-nowrap',
                    activeTab === 'commercial_invoices'
                        ? 'bg-[#F8B803] text-gray-900 shadow-sm'
                        : 'bg-white text-gray-600 hover:text-gray-900 hover:bg-gray-100 border border-transparent'
                ]"
            >
                <Receipt class="w-4 h-4" />
                <span>Commercial Invoices</span>
                <span 
                    v-if="metrics.totalCommercialInvoices !== undefined" 
                    class="ml-1 px-2 py-0.5 text-xs rounded-full bg-black/10 text-gray-900 font-bold"
                >
                    {{ metrics.totalCommercialInvoices }}
                </span>
            </button>

            <button
                type="button"
                @click="switchTab('purchase_orders')"
                :class="[
                    'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all cursor-pointer whitespace-nowrap',
                    activeTab === 'purchase_orders'
                        ? 'bg-[#F8B803] text-gray-900 shadow-sm'
                        : 'bg-white text-gray-600 hover:text-gray-900 hover:bg-gray-100 border border-transparent'
                ]"
            >
                <ShoppingCart class="w-4 h-4" />
                <span>Purchase Orders</span>
                <span 
                    v-if="metrics.totalPurchaseOrders !== undefined" 
                    class="ml-1 px-2 py-0.5 text-xs rounded-full bg-black/10 text-gray-900 font-bold"
                >
                    {{ metrics.totalPurchaseOrders }}
                </span>
            </button>

            <button
                type="button"
                @click="switchTab('requests')"
                :class="[
                    'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all cursor-pointer whitespace-nowrap',
                    activeTab === 'requests'
                        ? 'bg-[#F8B803] text-gray-900 shadow-sm'
                        : 'bg-white text-gray-600 hover:text-gray-900 hover:bg-gray-100 border border-transparent'
                ]"
            >
                <Package class="w-4 h-4" />
                <span>Purchase Requests</span>
                <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-black/10 text-gray-900 font-bold">
                    {{ metrics.totalRequests }}
                </span>
            </button>
        </div>

        <!-- Metrics Cards Section -->
        <div v-if="activeTab === 'commercial_invoices'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total Invoices</span>
                    <Receipt class="w-4 h-4 text-emerald-500" />
                </div>
                <div class="text-2xl font-black text-gray-900">{{ metrics.totalCommercialInvoices || 0 }}</div>
                <div class="text-xs text-gray-500 mt-1">Logged vendor invoices</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total Cost Value</span>
                    <Package class="w-4 h-4 text-[#F8B803]" />
                </div>
                <div class="text-2xl font-black text-[#B45309]">{{ formatCurrency(metrics.totalCommercialInvoiceAmount || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1">Total invoiced goods value</div>
            </div>
        </div>

        <div v-else-if="activeTab === 'purchase_orders'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total Purchase Orders</span>
                    <ShoppingCart class="w-4 h-4 text-blue-500" />
                </div>
                <div class="text-2xl font-black text-gray-900">{{ metrics.totalPurchaseOrders || 0 }}</div>
                <div class="text-xs text-gray-500 mt-1">Issued vendor POs</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total PO Amount</span>
                    <Receipt class="w-4 h-4 text-[#F8B803]" />
                </div>
                <div class="text-2xl font-black text-[#B45309]">{{ formatCurrency(metrics.totalPurchaseOrderAmount || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1">Total PO commitment</div>
            </div>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

        <!-- Search & Filters Bar (Beside search bar) -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 mb-6 bg-white p-3 rounded-2xl border border-gray-200 shadow-xs">
            <!-- Search Bar -->
            <div class="relative flex-1 max-w-md">
                <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    @input="applyFilter"
                    type="text"
                    :placeholder="
                        activeTab === 'commercial_invoices' 
                            ? 'Search by invoice #, description or supplier...'
                            : activeTab === 'purchase_orders'
                            ? 'Search by PO #, SKU, description or supplier...'
                            : 'Search by request #, product or supplier...'
                    "
                    class="w-full pl-10 pr-4 py-2 bg-gray-50/80 hover:bg-white focus:bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-xs transition-colors"
                />
            </div>

            <!-- Filters beside search bar -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Status Filter (Tailored per tab) -->
                <div class="relative min-w-[140px]">
                    <select
                        v-model="selectedStatus"
                        @change="applyFilter"
                        class="w-full bg-gray-50/80 hover:bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#F8B803] focus:bg-white shadow-xs cursor-pointer transition-colors"
                    >
                        <option value="All">All Statuses</option>
                        <template v-if="activeTab === 'commercial_invoices'">
                            <option value="Received">Received</option>
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Cancelled">Cancelled</option>
                        </template>
                        <template v-else-if="activeTab === 'purchase_orders'">
                            <option value="Issued">Issued</option>
                            <option value="Pending">Pending</option>
                            <option value="Received">Received</option>
                            <option value="Cancelled">Cancelled</option>
                        </template>
                        <template v-else>
                            <option value="Requested">Requested</option>
                            <option value="Ordered">Ordered</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </template>
                    </select>
                </div>

                <!-- Supplier Filter (For all tabs) -->
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

                <!-- U/M Filter (Specifically for Purchase Orders) -->
                <div v-if="activeTab === 'purchase_orders'" class="relative min-w-[110px]">
                    <select
                        v-model="selectedUm"
                        @change="applyFilter"
                        class="w-full bg-gray-50/80 hover:bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#F8B803] focus:bg-white shadow-xs cursor-pointer transition-colors uppercase"
                    >
                        <option value="All">All U/M</option>
                        <option value="PCS">PCS</option>
                        <option value="BOX">BOX</option>
                        <option value="MTR">MTR</option>
                        <option value="KG">KG</option>
                        <option value="SET">SET</option>
                        <option value="ROLL">ROLL</option>
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

        <!-- ======================================================== -->
        <!-- TAB 1: COMMERCIAL INVOICES TABLE -->
        <!-- ======================================================== -->
        <div v-if="activeTab === 'commercial_invoices'" class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-sm flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">DATE</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">COMMERCIAL INVOICE</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-center tracking-wider">QUANTITY</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Description</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-right tracking-wider">UNIT COST</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-right tracking-wider">TOTAL AMOUNT</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-right tracking-wider">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!commercialInvoices?.data?.length">
                            <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                <div class="max-w-md mx-auto space-y-2">
                                    <Receipt class="w-8 h-8 mx-auto text-gray-300" />
                                    <p class="font-medium text-gray-700">No commercial invoices found</p>
                                    <p class="text-xs text-gray-400">Click "+ New Commercial Invoice" or use "Import CSV" to upload records.</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="ci in commercialInvoices?.data" :key="ci.id" class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-6 py-4 text-gray-700 font-medium whitespace-nowrap">{{ formatDate(ci.invoice_date) }}</td>
                            <td class="px-6 py-4 font-mono font-bold text-[#b45309] whitespace-nowrap">{{ ci.invoice_number }}</td>
                            <td class="px-6 py-4 text-center font-bold text-gray-900">{{ ci.quantity }}</td>
                            <td class="px-6 py-4 text-gray-900 font-medium max-w-md truncate">{{ ci.description }}</td>
                            <td class="px-6 py-4 text-right font-medium text-gray-800 whitespace-nowrap">{{ formatCurrency(ci.unit_cost) }}</td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900 whitespace-nowrap">{{ formatCurrency(ci.total_cost) }}</td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="openDeleteCiModal(ci)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 border border-red-200 rounded-lg transition-colors cursor-pointer"
                                    title="Delete invoice"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Delete</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Commercial Invoices Pagination -->
            <div v-if="commercialInvoices && commercialInvoices.total > commercialInvoices.data.length" class="p-4 flex items-center justify-between border-t border-gray-100">
                <span class="text-xs text-gray-500">
                    Showing {{ commercialInvoices.data.length }} of {{ commercialInvoices.total }} commercial invoices
                </span>
                <div class="flex gap-1">
                    <button
                        v-for="(link, i) in commercialInvoices.links"
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

        <!-- ======================================================== -->
        <!-- TAB 2: PURCHASE ORDERS TABLE -->
        <!-- ======================================================== -->
        <div v-else-if="activeTab === 'purchase_orders'" class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-sm flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Date</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Purchase Order No.</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-center tracking-wider">Quantity</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">ITEM/SKU Number</th>
                            <th class="px-6 py-4 font-bold text-gray-900 tracking-wider">Description</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-center tracking-wider">U/M</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-right tracking-wider">Unit Cost</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-right tracking-wider">Total Amount</th>
                            <th class="px-6 py-4 font-bold text-gray-900 text-right tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!purchaseOrders?.data?.length">
                            <td colspan="9" class="px-6 py-10 text-center text-gray-500">
                                <div class="max-w-md mx-auto space-y-2">
                                    <ShoppingCart class="w-8 h-8 mx-auto text-gray-300" />
                                    <p class="font-medium text-gray-700">No purchase orders found</p>
                                    <p class="text-xs text-gray-400">Click "+ New Purchase Order" or "Import CSV" to create purchase orders.</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="po in purchaseOrders?.data" :key="po.id" class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-6 py-4 text-gray-700 font-medium whitespace-nowrap">{{ formatDate(po.po_date) }}</td>
                            <td class="px-6 py-4 font-mono font-bold text-blue-700 whitespace-nowrap">{{ po.po_number }}</td>
                            <td class="px-6 py-4 text-center font-bold text-gray-900">{{ po.quantity }}</td>
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-800 whitespace-nowrap">{{ po.item_sku || '—' }}</td>
                            <td class="px-6 py-4 text-gray-900 font-medium max-w-sm truncate">{{ po.description }}</td>
                            <td class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ po.unit_of_measure || 'PCS' }}</td>
                            <td class="px-6 py-4 text-right font-medium text-gray-800 whitespace-nowrap">{{ formatCurrency(po.unit_cost) }}</td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900 whitespace-nowrap">{{ formatCurrency(po.total_cost) }}</td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="openDeletePoModal(po)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 border border-red-200 rounded-lg transition-colors cursor-pointer"
                                    title="Delete order"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Delete</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Purchase Orders Pagination -->
            <div v-if="purchaseOrders && purchaseOrders.total > purchaseOrders.data.length" class="p-4 flex items-center justify-between border-t border-gray-100">
                <span class="text-xs text-gray-500">
                    Showing {{ purchaseOrders.data.length }} of {{ purchaseOrders.total }} purchase orders
                </span>
                <div class="flex gap-1">
                    <button
                        v-for="(link, i) in purchaseOrders.links"
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

        <!-- ======================================================== -->
        <!-- TAB 3: PURCHASE REQUESTS TABLE -->
        <!-- ======================================================== -->
        <div v-else class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-sm flex flex-col">
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
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ formatDate(req.requested_at) }}</td>
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

    <!-- ======================================================== -->
    <!-- MODAL: CREATE COMMERCIAL INVOICE -->
    <!-- ======================================================== -->
    <div v-if="showCreateCiModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">New Commercial Invoice</h3>
                    <p class="text-xs text-gray-500">Record a commercial invoice for procurement</p>
                </div>
                <button @click="showCreateCiModal = false" class="text-gray-400 hover:text-gray-600 text-xl cursor-pointer">&times;</button>
            </div>

            <form @submit.prevent="submitCi" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- DATE -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">DATE *</label>
                        <input
                            v-model="ciForm.invoice_date"
                            type="date"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="ciForm.errors.invoice_date" class="text-xs text-red-500 mt-0.5 block">{{ ciForm.errors.invoice_date }}</span>
                    </div>

                    <!-- COMMERCIAL INVOICE NO -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            COMMERCIAL INVOICE
                            <span class="text-gray-400 font-normal">(Sample: CI-035-0722)</span>
                        </label>
                        <input
                            v-model="ciForm.invoice_number"
                            type="text"
                            placeholder="e.g. CI-035-0722 (or leave blank to auto-generate)"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none font-mono"
                        />
                        <span v-if="ciForm.errors.invoice_number" class="text-xs text-red-500 mt-0.5 block">{{ ciForm.errors.invoice_number }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- QUANTITY -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">QUANTITY *</label>
                        <input
                            v-model.number="ciForm.quantity"
                            type="number"
                            min="1"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="ciForm.errors.quantity" class="text-xs text-red-500 mt-0.5 block">{{ ciForm.errors.quantity }}</span>
                    </div>

                    <!-- UNIT COST -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">UNIT COST (PHP) *</label>
                        <input
                            v-model.number="ciForm.unit_cost"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="ciForm.errors.unit_cost" class="text-xs text-red-500 mt-0.5 block">{{ ciForm.errors.unit_cost }}</span>
                    </div>
                </div>

                <!-- Description / Product Selector with Others -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description / Product *</label>
                    <select
                        v-model="ciSelectedProduct"
                        @change="onCiProductChange"
                        class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none bg-white"
                    >
                        <option value="" disabled>-- Select a Product --</option>
                        <option v-for="p in products" :key="p.id" :value="String(p.id)">
                            {{ p.name }} ({{ p.sku }})
                        </option>
                        <option value="__others__">-- Others (Type Custom Description) --</option>
                    </select>

                    <!-- Opens textbox when "Others" is selected -->
                    <div v-if="isCiCustomDescription" class="mt-2.5 animate-in fade-in slide-in-from-top-1 duration-150">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Custom Product Description *</label>
                        <textarea
                            v-model="ciForm.description"
                            rows="2"
                            required
                            placeholder="Type custom product description..."
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        ></textarea>
                    </div>
                    <span v-if="ciForm.errors.description" class="text-xs text-red-500 mt-0.5 block">{{ ciForm.errors.description }}</span>
                </div>

                <!-- Supplier (Optional) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Supplier (Optional)</label>
                    <select
                        v-model="ciForm.supplier_id"
                        class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                    >
                        <option :value="null">-- None / Direct Import --</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <!-- Estimated Total Display -->
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 flex justify-between items-center text-sm font-semibold">
                    <span class="text-gray-600">Calculated Total:</span>
                    <span class="text-base text-gray-900 font-bold font-mono">
                        {{ formatCurrency((ciForm.quantity || 0) * (ciForm.unit_cost || 0)) }}
                    </span>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                    <SpecularButton
                        type="button"
                        variant="secondary"
                        size="md"
                        :radius="10"
                        @click="showCreateCiModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        type="submit"
                        variant="brand"
                        size="md"
                        :radius="10"
                        :disabled="ciForm.processing"
                    >
                        {{ ciForm.processing ? 'Saving...' : 'Save Commercial Invoice' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: IMPORT COMMERCIAL INVOICES CSV -->
    <!-- ======================================================== -->
    <div v-if="showCiImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Import Commercial Invoices CSV</h3>
                    <p class="text-xs text-gray-500">Headers: <code class="bg-gray-100 px-1 py-0.5 rounded font-bold text-gray-800">DATE, COMMERCIAL INVOICE, QUANTITY, Description, UNIT COST</code></p>
                </div>
                <button @click="showCiImportModal = false" class="text-gray-400 hover:text-gray-600 text-xl cursor-pointer">&times;</button>
            </div>

            <!-- Upload Area & Sample Template -->
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between p-4 bg-amber-50/60 border border-amber-100 rounded-xl mb-4">
                <div>
                    <h4 class="text-sm font-bold text-gray-900">Need the template?</h4>
                    <p class="text-xs text-gray-600">Sample CI format: <code class="font-mono text-amber-800 font-bold">CI-035-0722</code></p>
                </div>
                <button
                    type="button"
                    @click="downloadSampleCiCsv"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-amber-200 text-amber-900 hover:bg-amber-50 shadow-xs cursor-pointer whitespace-nowrap"
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
                    @change="handleCiFileChange"
                    class="w-full text-xs text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#F8B803] file:text-gray-900 hover:file:opacity-90 cursor-pointer border border-gray-200 rounded-xl p-2"
                />
            </div>

            <!-- Loading State -->
            <div v-if="isValidatingCiCsv" class="py-6 text-center text-gray-500 text-xs">
                <Clock class="w-5 h-5 mx-auto animate-spin mb-1 text-[#F8B803]" />
                Validating CSV rows...
            </div>

            <!-- Error Message -->
            <div v-if="ciCsvError" class="p-3 mb-4 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
                <AlertCircle class="w-4 h-4 shrink-0 text-red-500 mt-0.5" />
                <span>{{ ciCsvError }}</span>
            </div>

            <!-- Preview Results -->
            <div v-if="ciCsvValidationResult" class="space-y-4">
                <div class="flex gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                    <div>
                        <span class="text-gray-500">Total Rows:</span>
                        <span class="ml-1 font-bold text-gray-900">{{ ciCsvValidationResult.summary.total_rows }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Valid:</span>
                        <span class="ml-1 font-bold text-emerald-600">{{ ciCsvValidationResult.summary.valid_count }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Invalid:</span>
                        <span class="ml-1 font-bold text-red-600">{{ ciCsvValidationResult.summary.invalid_count }}</span>
                    </div>
                </div>

                <!-- Preview Table -->
                <div v-if="ciCsvValidationResult.valid_rows?.length" class="max-h-60 overflow-y-auto border border-gray-200 rounded-xl">
                    <table class="w-full text-left text-xs text-gray-600">
                        <thead class="bg-gray-50 font-bold text-gray-700 border-b border-gray-200 sticky top-0">
                            <tr>
                                <th class="p-2">DATE</th>
                                <th class="p-2">COMMERCIAL INVOICE</th>
                                <th class="p-2 text-center">QUANTITY</th>
                                <th class="p-2">Description</th>
                                <th class="p-2 text-right">UNIT COST</th>
                                <th class="p-2 text-right">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="r in ciCsvValidationResult.valid_rows.slice(0, 10)" :key="r.row_number">
                                <td class="p-2 whitespace-nowrap">{{ r.display_date || r.date }}</td>
                                <td class="p-2 font-mono font-bold text-amber-700 whitespace-nowrap">{{ r.invoice_number }}</td>
                                <td class="p-2 text-center font-bold">{{ r.quantity }}</td>
                                <td class="p-2 max-w-xs truncate">{{ r.description }}</td>
                                <td class="p-2 text-right whitespace-nowrap">{{ formatCurrency(r.unit_cost) }}</td>
                                <td class="p-2 text-right font-bold whitespace-nowrap">{{ formatCurrency(r.total_cost) }}</td>
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
                    @click="showCiImportModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    variant="brand"
                    size="md"
                    :radius="10"
                    :disabled="isImportingCiCsv || !ciCsvValidationResult || ciCsvValidationResult.summary?.valid_count === 0"
                    @click="executeCiCsvImport"
                >
                    {{ isImportingCiCsv ? 'Importing...' : `Import ${ciCsvValidationResult?.summary?.valid_count || ''} Invoices` }}
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: CREATE PURCHASE ORDER -->
    <!-- ======================================================== -->
    <div v-if="showCreatePoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">New Purchase Order</h3>
                    <p class="text-xs text-gray-500">Issue an official purchase order to vendor</p>
                </div>
                <button @click="showCreatePoModal = false" class="text-gray-400 hover:text-gray-600 text-xl cursor-pointer">&times;</button>
            </div>

            <form @submit.prevent="submitPo" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date *</label>
                        <input
                            v-model="poForm.po_date"
                            type="date"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="poForm.errors.po_date" class="text-xs text-red-500 mt-0.5 block">{{ poForm.errors.po_date }}</span>
                    </div>

                    <!-- Purchase Order No. -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Purchase Order No.
                            <span class="text-gray-400 font-normal">(Sample: 4000005472)</span>
                        </label>
                        <input
                            v-model="poForm.po_number"
                            type="text"
                            placeholder="e.g. 4000005472 (or auto-generate)"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none font-mono"
                        />
                        <span v-if="poForm.errors.po_number" class="text-xs text-red-500 mt-0.5 block">{{ poForm.errors.po_number }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Quantity -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Quantity *</label>
                        <input
                            v-model.number="poForm.quantity"
                            type="number"
                            min="1"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="poForm.errors.quantity" class="text-xs text-red-500 mt-0.5 block">{{ poForm.errors.quantity }}</span>
                    </div>

                    <!-- ITEM/SKU Number -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ITEM/SKU Number</label>
                        <input
                            v-model="poForm.item_sku"
                            type="text"
                            placeholder="e.g. 068SSCR2"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none font-mono"
                        />
                    </div>

                    <!-- U/M -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">U/M</label>
                        <input
                            v-model="poForm.unit_of_measure"
                            type="text"
                            placeholder="e.g. PCS, BOX, MTR"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none uppercase"
                        />
                    </div>
                </div>

                <!-- Description / Product Selector with Others -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description / Product *</label>
                    <select
                        v-model="poSelectedProduct"
                        @change="onPoProductChange"
                        class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none bg-white"
                    >
                        <option value="" disabled>-- Select a Product --</option>
                        <option v-for="p in products" :key="p.id" :value="String(p.id)">
                            {{ p.name }} ({{ p.sku }})
                        </option>
                        <option value="__others__">-- Others (Type Custom Description) --</option>
                    </select>

                    <!-- Opens textbox when "Others" is selected -->
                    <div v-if="isPoCustomDescription" class="mt-2.5 animate-in fade-in slide-in-from-top-1 duration-150">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Custom Product Description *</label>
                        <textarea
                            v-model="poForm.description"
                            rows="2"
                            required
                            placeholder="Type custom product description..."
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        ></textarea>
                    </div>
                    <span v-if="poForm.errors.description" class="text-xs text-red-500 mt-0.5 block">{{ poForm.errors.description }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Unit Cost -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit Cost (PHP) *</label>
                        <input
                            v-model.number="poForm.unit_cost"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        />
                        <span v-if="poForm.errors.unit_cost" class="text-xs text-red-500 mt-0.5 block">{{ poForm.errors.unit_cost }}</span>
                    </div>

                    <!-- Supplier -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Supplier</label>
                        <select
                            v-model="poForm.supplier_id"
                            class="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:border-[#F8B803] focus:outline-none"
                        >
                            <option :value="null">-- Select Supplier --</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Estimated Total Display -->
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 flex justify-between items-center text-sm font-semibold">
                    <span class="text-gray-600">PO Total Amount:</span>
                    <span class="text-base text-gray-900 font-bold font-mono">
                        {{ formatCurrency((poForm.quantity || 0) * (poForm.unit_cost || 0)) }}
                    </span>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                    <SpecularButton
                        type="button"
                        variant="secondary"
                        size="md"
                        :radius="10"
                        @click="showCreatePoModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        type="submit"
                        variant="brand"
                        size="md"
                        :radius="10"
                        :disabled="poForm.processing"
                    >
                        {{ poForm.processing ? 'Issuing...' : 'Issue Purchase Order' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: IMPORT PURCHASE ORDERS CSV -->
    <!-- ======================================================== -->
    <div v-if="showPoImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Import Purchase Orders CSV</h3>
                    <p class="text-xs text-gray-500">Headers: <code class="bg-gray-100 px-1 py-0.5 rounded font-bold text-gray-800">Date, Purchase Order No., Quantity, ITEM/SKU Number, Description, U/M, Unit Cost</code></p>
                </div>
                <button @click="showPoImportModal = false" class="text-gray-400 hover:text-gray-600 text-xl cursor-pointer">&times;</button>
            </div>

            <!-- Upload Area & Sample Template -->
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between p-4 bg-blue-50/60 border border-blue-100 rounded-xl mb-4">
                <div>
                    <h4 class="text-sm font-bold text-gray-900">Need the template?</h4>
                    <p class="text-xs text-gray-600">Sample PO No: <code class="font-mono text-blue-800 font-bold">4000005472</code></p>
                </div>
                <button
                    type="button"
                    @click="downloadSamplePoCsv"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-blue-200 text-blue-900 hover:bg-blue-50 shadow-xs cursor-pointer whitespace-nowrap"
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
                    @change="handlePoFileChange"
                    class="w-full text-xs text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#F8B803] file:text-gray-900 hover:file:opacity-90 cursor-pointer border border-gray-200 rounded-xl p-2"
                />
            </div>

            <!-- Loading State -->
            <div v-if="isValidatingPoCsv" class="py-6 text-center text-gray-500 text-xs">
                <Clock class="w-5 h-5 mx-auto animate-spin mb-1 text-[#F8B803]" />
                Validating CSV rows...
            </div>

            <!-- Error Message -->
            <div v-if="poCsvError" class="p-3 mb-4 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
                <AlertCircle class="w-4 h-4 shrink-0 text-red-500 mt-0.5" />
                <span>{{ poCsvError }}</span>
            </div>

            <!-- Preview Results -->
            <div v-if="poCsvValidationResult" class="space-y-4">
                <div class="flex gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                    <div>
                        <span class="text-gray-500">Total Rows:</span>
                        <span class="ml-1 font-bold text-gray-900">{{ poCsvValidationResult.summary.total_rows }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Valid:</span>
                        <span class="ml-1 font-bold text-emerald-600">{{ poCsvValidationResult.summary.valid_count }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Invalid:</span>
                        <span class="ml-1 font-bold text-red-600">{{ poCsvValidationResult.summary.invalid_count }}</span>
                    </div>
                </div>

                <!-- Preview Table -->
                <div v-if="poCsvValidationResult.valid_rows?.length" class="max-h-60 overflow-y-auto border border-gray-200 rounded-xl">
                    <table class="w-full text-left text-xs text-gray-600">
                        <thead class="bg-gray-50 font-bold text-gray-700 border-b border-gray-200 sticky top-0">
                            <tr>
                                <th class="p-2">Date</th>
                                <th class="p-2">Purchase Order No.</th>
                                <th class="p-2 text-center">Quantity</th>
                                <th class="p-2">ITEM/SKU</th>
                                <th class="p-2">Description</th>
                                <th class="p-2 text-center">U/M</th>
                                <th class="p-2 text-right">Unit Cost</th>
                                <th class="p-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="r in poCsvValidationResult.valid_rows.slice(0, 10)" :key="r.row_number">
                                <td class="p-2 whitespace-nowrap">{{ r.display_date || r.date }}</td>
                                <td class="p-2 font-mono font-bold text-blue-700 whitespace-nowrap">{{ r.po_number }}</td>
                                <td class="p-2 text-center font-bold">{{ r.quantity }}</td>
                                <td class="p-2 font-mono text-xs">{{ r.item_sku || '—' }}</td>
                                <td class="p-2 max-w-xs truncate">{{ r.description }}</td>
                                <td class="p-2 text-center font-bold">{{ r.unit_of_measure }}</td>
                                <td class="p-2 text-right whitespace-nowrap">{{ formatCurrency(r.unit_cost) }}</td>
                                <td class="p-2 text-right font-bold whitespace-nowrap">{{ formatCurrency(r.total_cost) }}</td>
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
                    @click="showPoImportModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    variant="brand"
                    size="md"
                    :radius="10"
                    :disabled="isImportingPoCsv || !poCsvValidationResult || poCsvValidationResult.summary?.valid_count === 0"
                    @click="executePoCsvImport"
                >
                    {{ isImportingPoCsv ? 'Importing...' : `Import ${poCsvValidationResult?.summary?.valid_count || ''} Purchase Orders` }}
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: DELETE COMMERCIAL INVOICE -->
    <!-- ======================================================== -->
    <div v-if="showDeleteCiModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Commercial Invoice</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to delete commercial invoice <span class="font-semibold text-gray-900">{{ selectedCi?.invoice_number }}</span>?
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showDeleteCiModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    variant="destructive"
                    size="md"
                    :radius="10"
                    @click="confirmDeleteCi"
                >
                    Delete Invoice
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: DELETE PURCHASE ORDER -->
    <!-- ======================================================== -->
    <div v-if="showDeletePoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Purchase Order</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to delete purchase order <span class="font-semibold text-gray-900">{{ selectedPo?.po_number }}</span>?
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showDeletePoModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    variant="destructive"
                    size="md"
                    :radius="10"
                    @click="confirmDeletePo"
                >
                    Delete Order
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: CREATE PURCHASE REQUEST (ORIGINAL) -->
    <!-- ======================================================== -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900">New Purchase Request</h3>
                <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-xl cursor-pointer">&times;</button>
            </div>

            <form @submit.prevent="promptCreateRequest" class="space-y-4">
                <!-- Item to Order -->
                <div class="relative">
                    <select id="req_product"
                            v-model="form.product_id" 
                            required 
                            class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm">
                        <option value="" disabled selected>-- Choose Product --</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">
                            {{ p.name }} (Current: {{ p.stock }})
                        </option>
                    </select>
                    <label for="req_product"
                           class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]">
                        Item to Order
                    </label>
                    <span v-if="form.errors.product_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.product_id }}</span>
                </div>

                <!-- Supplier -->
                <div class="relative">
                    <select id="req_supplier"
                            v-model="form.supplier_id" 
                            required 
                            class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm">
                        <option value="" disabled selected>-- Choose Supplier --</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <label for="req_supplier"
                           class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]">
                        Supplier
                    </label>
                    <span v-if="form.errors.supplier_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.supplier_id }}</span>
                </div>

                <!-- Order Quantity -->
                <div class="relative">
                    <input id="req_quantity"
                           v-model.number="form.quantity" 
                           type="number" 
                           min="1" 
                           placeholder=" "
                           required 
                           class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" />
                    <label for="req_quantity"
                           class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                           :class="form.quantity !== null && form.quantity !== undefined && (form.quantity as unknown) !== '' ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                        Order Quantity
                    </label>
                    <span v-if="form.errors.quantity" class="text-xs text-red-500 mt-1 block">{{ form.errors.quantity }}</span>
                </div>

                <!-- Notes / Instructions -->
                <div class="relative">
                    <textarea id="req_notes"
                              v-model="form.notes" 
                              rows="2" 
                              placeholder=" "
                              class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"></textarea>
                    <label for="req_notes"
                           class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                           :class="form.notes ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                        Notes / Instructions
                    </label>
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

    <!-- ======================================================== -->
    <!-- MODAL: DELETE PURCHASE REQUEST -->
    <!-- ======================================================== -->
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

    <!-- ======================================================== -->
    <!-- MODAL: ACTION CONFIRMATION -->
    <!-- ======================================================== -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="w-11 h-11 rounded-full bg-amber-50 text-[#F8B803] flex items-center justify-center mb-4 border border-amber-100">
                <CheckCircle2 class="w-6 h-6" />
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
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="handleConfirmedAction"
                    :disabled="form.processing"
                >
                    <Check class="w-4 h-4 mr-1.5 inline" />
                    <span>{{ confirmOptions.confirmText || 'Confirm & Submit' }}</span>
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
