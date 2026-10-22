<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import { 
    FileText, 
    Plus, 
    Search, 
    Printer, 
    CheckCircle2, 
    Clock, 
    AlertTriangle, 
    XCircle, 
    Check, 
    X, 
    Trash2, 
    Pencil, 
    Eye, 
    ShoppingCart, 
    Download,
    Building2,
    Calendar,
    Phone,
    Mail,
    MapPin,
    AlertCircle,
    RotateCcw,
    Layers,
    ArrowRight
} from '@lucide/vue';

interface QuotationItem {
    id?: number;
    product_id?: number | null;
    sku: string;
    product_name: string;
    quantity: number;
    unit_price: number;
    discount: number;
    subtotal: number;
}

interface Quotation {
    id: number;
    quotation_number: string;
    client_id?: number | null;
    client_name: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    quotation_date: string;
    valid_until: string;
    subtotal: number;
    discount: number;
    tax_rate: number;
    tax_amount: number;
    grand_total: number;
    status: 'Draft' | 'Pending' | 'Approved' | 'Rejected' | 'Cancelled' | 'Expired' | 'Completed';
    notes?: string | null;
    terms_and_conditions?: string | null;
    prepared_by?: { name: string } | null;
    converted_to_sale_id?: number | null;
    sale?: { invoice_number: string } | null;
    items: QuotationItem[];
}

interface Product {
    id: number;
    sku: string;
    name: string;
    category: string;
    price: number;
    stock: number;
    status: string;
}

interface Client {
    id: number;
    company_name: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
}

const props = defineProps<{
    quotations: {
        data: Quotation[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    filters: {
        search?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
    };
    metrics: {
        totalQuotations: number;
        pendingCount: number;
        approvedCount: number;
        totalQuotedValue: number;
    };
    products: Product[];
    clients: Client[];
    nextNumber: string;
    statusOptions: { value: string; label: string; badgeClass: string }[];
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || (page.props as any).team?.slug || '');

// Filters
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || 'All');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

let filterTimeout: ReturnType<typeof setTimeout> | null = null;
const applyFilters = () => {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(
            `/${teamSlug.value}/quotations`,
            {
                search: search.value || undefined,
                status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = 'All';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

// Single consolidated watcher — avoids double-firing vs calling applyFilters individually
watch([search, selectedStatus, dateFrom, dateTo], applyFilters);

// Modals State
const showCreateEditModal = ref(false);
const isEditing = ref(false);
const showViewModal = ref(false);
const showPrintModal = ref(false);
const showStatusModal = ref(false);
const showConvertModal = ref(false);
const showDeleteModal = ref(false);
const activeQuotation = ref<Quotation | null>(null);

// Create / Edit Form State
const selectedExistingClientId = ref<string>('');
const form = ref<{
    id?: number;
    client_name: string;
    contact_person: string;
    email: string;
    phone: string;
    address: string;
    quotation_date: string;
    valid_until: string;
    status: string;
    notes: string;
    terms_and_conditions: string;
    tax_rate: number;
    discount: number;
    items: {
        product_id: number | null;
        sku: string;
        product_name: string;
        quantity: number;
        unit_price: number;
        discount: number;
        stock: number;
    }[];
}>({
    client_name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    quotation_date: new Date().toISOString().slice(0, 10),
    valid_until: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    status: 'Draft',
    notes: '',
    terms_and_conditions: "1. Price Validity: Quotation is valid until the stated expiration date.\n2. Payment Terms: 50% down payment upon confirmation, 50% upon delivery.\n3. Delivery: 5-7 business days from receipt of purchase order.\n4. Warranty: 1-Year standard manufacturer warranty on electrical equipment.\n5. To accept, please sign and return via email.",
    tax_rate: 12.00,
    discount: 0,
    items: []
});

// Watch client selection to auto-fill form
watch(selectedExistingClientId, (newId) => {
    if (!newId) return;
    const client = props.clients.find(c => c.id.toString() === newId.toString());
    if (client) {
        form.value.client_name = client.company_name;
        form.value.contact_person = client.contact_person || '';
        form.value.email = client.email || '';
        form.value.phone = client.phone || '';
        form.value.address = client.address || '';
    }
});

// Dynamic Calculation Computeds
const formSubtotal = computed(() => {
    return form.value.items.reduce((sum, item) => {
        const disc = Number(item.discount) || 0;
        const price = Number(item.unit_price) || 0;
        const qty = Number(item.quantity) || 0;
        return sum + Math.max(0, (price - disc) * qty);
    }, 0);
});

const formTaxAmount = computed(() => {
    const taxable = Math.max(0, formSubtotal.value - (Number(form.value.discount) || 0));
    return (taxable * (Number(form.value.tax_rate) || 0)) / 100;
});

const formGrandTotal = computed(() => {
    const taxable = Math.max(0, formSubtotal.value - (Number(form.value.discount) || 0));
    return taxable + formTaxAmount.value;
});

// Line Items Methods
const addItem = () => {
    // Default to first product if available
    const firstProd = props.products[0];
    form.value.items.push({
        product_id: firstProd ? firstProd.id : null,
        sku: firstProd ? firstProd.sku : 'SKU-001',
        product_name: firstProd ? firstProd.name : '',
        quantity: 1,
        unit_price: firstProd ? Number(firstProd.price) : 0,
        discount: 0,
        stock: firstProd ? firstProd.stock : 0,
    });
};

const onProductSelect = (index: number, productId: number | null) => {
    if (!productId) return;
    const prod = props.products.find(p => p.id === productId);
    if (prod) {
        form.value.items[index].product_id = prod.id;
        form.value.items[index].sku = prod.sku;
        form.value.items[index].product_name = prod.name;
        form.value.items[index].unit_price = Number(prod.price);
        form.value.items[index].stock = prod.stock;
    }
};

const removeItem = (index: number) => {
    form.value.items.splice(index, 1);
};

// Form Actions
const openCreateModal = () => {
    isEditing.value = false;
    selectedExistingClientId.value = '';
    form.value = {
        client_name: '',
        contact_person: '',
        email: '',
        phone: '',
        address: '',
        quotation_date: new Date().toISOString().slice(0, 10),
        valid_until: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
        status: 'Draft',
        notes: '',
        terms_and_conditions: "1. Price Validity: Quotation is valid until the stated expiration date.\n2. Payment Terms: 50% down payment upon confirmation, 50% upon delivery.\n3. Delivery: 5-7 business days from receipt of purchase order.\n4. Warranty: 1-Year standard manufacturer warranty on electrical equipment.\n5. To accept, please sign and return via email.",
        tax_rate: 12.00,
        discount: 0,
        items: []
    };
    addItem();
    showCreateEditModal.value = true;
};

const openEditModal = (quote: Quotation) => {
    isEditing.value = true;
    selectedExistingClientId.value = quote.client_id ? quote.client_id.toString() : '';
    form.value = {
        id: quote.id,
        client_name: quote.client_name,
        contact_person: quote.contact_person || '',
        email: quote.email || '',
        phone: quote.phone || '',
        address: quote.address || '',
        quotation_date: quote.quotation_date,
        valid_until: quote.valid_until,
        status: quote.status,
        notes: quote.notes || '',
        terms_and_conditions: quote.terms_and_conditions || '',
        tax_rate: Number(quote.tax_rate),
        discount: Number(quote.discount),
        items: quote.items.map(item => {
            const matchedProd = props.products.find(p => p.id === item.product_id || p.sku === item.sku);
            return {
                product_id: item.product_id || null,
                sku: item.sku,
                product_name: item.product_name,
                quantity: item.quantity,
                unit_price: Number(item.unit_price),
                discount: Number(item.discount),
                stock: matchedProd ? matchedProd.stock : 0,
            };
        })
    };
    if (form.value.items.length === 0) addItem();
    showCreateEditModal.value = true;
};

const formError = ref<string | null>(null);
const isSubmitting = ref(false);

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
    confirmText: 'Confirm & Save',
    action: () => {},
});

const openConfirmModal = (options: ConfirmActionOptions) => {
    confirmOptions.value = {
        confirmText: 'Confirm & Save',
        ...options,
    };
    showConfirmModal.value = true;
};

const handleConfirmedAction = () => {
    showConfirmModal.value = false;
    confirmOptions.value.action();
};

const promptSaveQuotation = () => {
    formError.value = null;

    if (!form.value.client_name.trim()) {
        formError.value = 'Client / Company Name is required.';
        return;
    }
    if (form.value.items.length === 0) {
        formError.value = 'At least one line item must be added to the quotation.';
        return;
    }
    if (form.value.valid_until < form.value.quotation_date) {
        formError.value = 'Valid Until date cannot be earlier than the Quotation date.';
        return;
    }
    for (let i = 0; i < form.value.items.length; i++) {
        const item = form.value.items[i];
        if (!item.product_name.trim() || !item.sku.trim()) {
            formError.value = `Item #${i + 1} must have a product name and SKU.`;
            return;
        }
        if (Number(item.quantity) <= 0) {
            formError.value = `Item #${i + 1} quantity must be greater than zero.`;
            return;
        }
        if (Number(item.unit_price) < 0) {
            formError.value = `Item #${i + 1} unit price cannot be negative.`;
            return;
        }
    }

    const itemCount = form.value.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    const totalAmount = formTotal.value;

    openConfirmModal({
        title: isEditing.value ? 'Confirm Quotation Update' : 'Confirm New Quotation',
        description: isEditing.value
            ? `Are you sure you want to save changes to quotation for "${form.value.client_name}"?`
            : `Are you sure you want to generate a formal quotation for "${form.value.client_name}"?`,
        details: [
            { label: 'Client / Company', value: form.value.client_name },
            { label: 'Line Items', value: `${form.value.items.length} items (${itemCount} total units)` },
            { label: 'Total Valuation', value: formatPrice(totalAmount) },
            { label: 'Validity Date', value: form.value.valid_until },
        ],
        confirmText: isEditing.value ? 'Save Changes' : 'Create Quotation',
        action: saveQuotation,
    });
};

const saveQuotation = () => {
    isSubmitting.value = true;

    if (isEditing.value && form.value.id) {
        router.put(`/${teamSlug.value}/quotations/${form.value.id}`, form.value, {
            onSuccess: () => {
                showCreateEditModal.value = false;
                isSubmitting.value = false;
            },
            onError: (err) => {
                formError.value = Object.values(err)[0] as string;
                isSubmitting.value = false;
            }
        });
    } else {
        router.post(`/${teamSlug.value}/quotations`, form.value, {
            onSuccess: () => {
                showCreateEditModal.value = false;
                isSubmitting.value = false;
            },
            onError: (err) => {
                formError.value = Object.values(err)[0] as string;
                isSubmitting.value = false;
            }
        });
    }
};

// Status Update Modal
const targetStatus = ref<string>('Pending');
const openStatusModal = (quote: Quotation) => {
    activeQuotation.value = quote;
    targetStatus.value = quote.status;
    showStatusModal.value = true;
};

const isUpdatingStatus = ref(false);

const promptUpdateStatus = () => {
    if (!activeQuotation.value) return;
    openConfirmModal({
        title: 'Confirm Status Change',
        description: `Are you sure you want to change status of quotation ${activeQuotation.value.quotation_number} to "${targetStatus.value}"?`,
        details: [
            { label: 'Quotation #', value: activeQuotation.value.quotation_number },
            { label: 'Client / Company', value: activeQuotation.value.client_name },
            { label: 'Current Status', value: activeQuotation.value.status },
            { label: 'New Target Status', value: targetStatus.value },
        ],
        confirmText: 'Update Status',
        action: updateStatusSubmit,
    });
};

const updateStatusSubmit = () => {
    if (!activeQuotation.value) return;
    isUpdatingStatus.value = true;
    router.patch(`/${teamSlug.value}/quotations/${activeQuotation.value.id}/status`, {
        status: targetStatus.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false;
            isUpdatingStatus.value = false;
        },
        onError: () => {
            isUpdatingStatus.value = false;
        }
    });
};

// Convert to Sale Modal
const paymentMethod = ref('Bank Transfer / Check');
const openConvertModal = (quote: Quotation) => {
    activeQuotation.value = quote;
    paymentMethod.value = 'Bank Transfer / Check';
    showConvertModal.value = true;
};

const convertToSaleSubmit = () => {
    if (!activeQuotation.value) return;
    router.post(`/${teamSlug.value}/quotations/${activeQuotation.value.id}/convert-to-sale`, {
        payment_method: paymentMethod.value
    }, {
        onSuccess: () => {
            showConvertModal.value = false;
        }
    });
};

// View & Print
const openViewModal = (quote: Quotation) => {
    activeQuotation.value = quote;
    showViewModal.value = true;
};

const openPrintModal = (quote: Quotation) => {
    activeQuotation.value = quote;
    showPrintModal.value = true;
};

const triggerPrint = () => {
    window.print();
};

// Delete
const openDeleteModal = (quote: Quotation) => {
    activeQuotation.value = quote;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!activeQuotation.value) return;
    router.delete(`/${teamSlug.value}/quotations/${activeQuotation.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false;
        }
    });
};

// Helpers
const formatCurrency = (amount: number | string) => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(num || 0);
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'Approved':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Pending':
            return 'bg-[#F8B803]/15 text-[#4b5320] border-[#F8B803]/30 font-semibold';
        case 'Completed':
            return 'bg-blue-50 text-blue-700 border-blue-200 font-semibold';
        case 'Draft':
            return 'bg-gray-100 text-gray-700 border-gray-200';
        case 'Rejected':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'Expired':
            return 'bg-amber-50 text-amber-800 border-amber-300';
        case 'Cancelled':
            return 'bg-slate-100 text-slate-500 border-slate-200 line-through';
        default:
            return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};
</script>

<template>
    <Head title="Quotation Management | MRP ElectroDesk" />

    <div class="workspace-page">
        <!-- Page Title & Main Action -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-1">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Quotation Management</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#F8B803]/20 text-[#4b5320] border border-[#F8B803]/30">
                        MRP ElectroDesk
                    </span>
                </div>
                <p class="text-gray-500 mt-1">Create electrical project estimates, manage client approvals, and convert quotations to sales.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="12"
                @click="openCreateModal"
            >
                <Plus class="w-5 h-5" />
                <span>+ Create Quotation</span>
            </SpecularButton>
        </div>

        <!-- Metrics Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Quotations</p>
                    <h3 class="text-2xl font-black text-gray-900 mt-1">{{ metrics.totalQuotations }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">All time logged quotes</p>
                </div>
                <div class="p-3 bg-gray-50 text-gray-700 rounded-xl border border-gray-100">
                    <FileText class="w-6 h-6 text-[#4b5320]" />
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Pending Review</p>
                    <h3 class="text-2xl font-black text-gray-900 mt-1">{{ metrics.pendingCount }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Awaiting customer sign-off</p>
                </div>
                <div class="p-3 bg-amber-50 text-amber-600 rounded-xl border border-amber-100">
                    <Clock class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Approved Quotes</p>
                    <h3 class="text-2xl font-black text-gray-900 mt-1">{{ metrics.approvedCount }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Ready for Convert to Sale</p>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl border border-emerald-100">
                    <CheckCircle2 class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-[#4b5320] uppercase tracking-wider">Active Pipeline</p>
                    <h3 class="text-2xl font-black text-[#4b5320] mt-1">{{ formatCurrency(metrics.totalQuotedValue) }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Total active proposal value</p>
                </div>
                <div class="p-3 bg-[#F8B803]/15 text-[#4b5320] rounded-xl border border-[#F8B803]/30">
                    <ShoppingCart class="w-6 h-6" />
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto flex-1">
                <!-- Search -->
                <div class="relative flex-1 sm:max-w-xs">
                    <Search class="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                    <input v-model="search" 
                           type="text" 
                           placeholder="Search quote #, client, email..." 
                           class="w-full pl-9 pr-4 py-2 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-[#F8B803] focus:bg-white focus:outline-none shadow-2xs" />
                </div>

                <!-- Status Filter -->
                <div class="w-full sm:w-44">
                    <select v-model="selectedStatus" 
                            class="w-full py-2 px-3 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-[#F8B803] focus:bg-white focus:outline-none shadow-2xs">
                        <option value="All">All Statuses</option>
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="flex items-center gap-2">
                    <input v-model="dateFrom" type="date" title="Quotation Date From" class="py-2 px-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:border-[#F8B803] focus:outline-none" />
                    <span class="text-gray-400 text-xs">to</span>
                    <input v-model="dateTo" type="date" title="Quotation Date To" class="py-2 px-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:border-[#F8B803] focus:outline-none" />
                </div>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                <button v-if="search || selectedStatus !== 'All' || dateFrom || dateTo" 
                        @click="resetFilters" 
                        class="px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-gray-100 rounded-lg flex items-center gap-1.5 transition-colors">
                    <RotateCcw class="w-3.5 h-3.5" />
                    <span>Reset</span>
                </button>
            </div>
        </div>

        <!-- Quotations Table Card -->
        <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col justify-between">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                            <th class="pb-3 font-semibold">Quotation ID</th>
                            <th class="pb-3 font-semibold">Client / Company</th>
                            <th class="pb-3 font-semibold">Quote Date</th>
                            <th class="pb-3 font-semibold">Valid Until</th>
                            <th class="pb-3 font-semibold text-right">Total Amount</th>
                            <th class="pb-3 font-semibold text-center">Status</th>
                            <th class="pb-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <tr v-for="quote in quotations.data" :key="quote.id" class="hover:bg-gray-50/80 transition-colors group">
                            <!-- Quotation Number -->
                            <td class="py-4">
                                <div class="font-mono text-sm font-bold text-gray-900 flex items-center gap-1.5">
                                    <span>{{ quote.quotation_number }}</span>
                                </div>
                                <span class="text-[11px] text-gray-400 block">{{ quote.items.length }} line {{ quote.items.length === 1 ? 'item' : 'items' }}</span>
                            </td>

                            <!-- Client -->
                            <td class="py-4">
                                <p class="text-gray-900 font-semibold leading-snug">{{ quote.client_name }}</p>
                                <p v-if="quote.contact_person" class="text-xs text-gray-500 mt-0.5 flex items-center gap-1">
                                    <span>{{ quote.contact_person }}</span>
                                    <span v-if="quote.phone" class="text-gray-400">&bull; {{ quote.phone }}</span>
                                </p>
                            </td>

                            <!-- Dates -->
                            <td class="py-4 text-gray-600 text-xs font-medium">{{ quote.quotation_date }}</td>
                            <td class="py-4 text-xs font-medium">
                                <div class="flex items-center gap-1.5">
                                    <span :class="quote.valid_until < new Date().toISOString().slice(0, 10) && quote.status !== 'Completed' ? 'text-amber-700 font-semibold' : 'text-gray-600'">
                                        {{ quote.valid_until }}
                                    </span>
                                    <span v-if="quote.valid_until < new Date().toISOString().slice(0, 10) && quote.status !== 'Completed'" 
                                          class="px-1.5 py-0.2 rounded text-[10px] bg-amber-100 text-amber-800 font-bold">
                                        Expired
                                    </span>
                                </div>
                            </td>

                            <!-- Total Amount -->
                            <td class="py-4 text-right">
                                <span class="text-base font-bold text-gray-900 block">{{ formatCurrency(quote.grand_total) }}</span>
                                <span class="text-[10px] text-gray-400 block">incl. 12% VAT</span>
                            </td>

                            <!-- Status -->
                            <td class="py-4 text-center">
                                <span :class="getStatusBadge(quote.status)" class="px-2.5 py-1 rounded-full text-xs font-medium border inline-flex items-center gap-1">
                                    {{ quote.status }}
                                </span>
                                <span v-if="quote.sale" class="block text-[10px] text-blue-600 font-mono mt-1 font-semibold">
                                    Sale: {{ quote.sale.invoice_number }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Convert to Sale Button (Only if Approved) -->
                                    <SpecularButton
                                        v-if="quote.status === 'Approved' && !quote.converted_to_sale_id" 
                                        variant="success"
                                        size="sm"
                                        :radius="8"
                                        @click="openConvertModal(quote)" 
                                        title="Convert Approved Quote to Sale"
                                    >
                                        <ShoppingCart class="w-3.5 h-3.5" />
                                        <span>Convert to Sale</span>
                                    </SpecularButton>

                                    <!-- Update Status Quick Action -->
                                    <button @click="openStatusModal(quote)" 
                                            title="Update Status"
                                            class="px-2 py-1 bg-white border border-gray-200 hover:border-[#F8B803] text-gray-700 rounded-lg text-xs transition-colors shadow-2xs">
                                        Status
                                    </button>

                                    <!-- Print / Preview -->
                                    <button @click="openPrintModal(quote)" 
                                            title="Print / Preview Quotation"
                                            class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                                        <Printer class="w-4 h-4 text-[#4b5320]" />
                                    </button>

                                    <!-- Edit -->
                                    <button @click="openEditModal(quote)" 
                                            title="Edit Quotation"
                                            class="p-1.5 text-gray-600 hover:text-[#4b5320] hover:bg-[#F8B803]/10 rounded-lg transition-colors">
                                        <Pencil class="w-4 h-4" />
                                    </button>

                                    <!-- Delete (Only Draft/Cancelled/Rejected) -->
                                    <button @click="openDeleteModal(quote)" 
                                            title="Delete Quotation"
                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="quotations.data.length === 0">
                            <td colspan="7" class="py-14 text-center text-gray-400">
                                <FileText class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                                <p class="text-base font-semibold text-gray-700">No Quotations Found</p>
                                <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
                                    No quotations match the active filters. Click "+ Create Quotation" to generate a new customer estimate.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="quotations.total > 0" class="mt-5 pt-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-sm text-gray-500">
                <span>
                    Showing <strong class="text-gray-800">{{ (quotations.current_page - 1) * 10 + 1 }}</strong> to 
                    <strong class="text-gray-800">{{ Math.min(quotations.current_page * 10, quotations.total) }}</strong> of 
                    <strong class="text-gray-800">{{ quotations.total }}</strong> quotations
                </span>
                <div class="flex items-center gap-2">
                    <button v-for="(link, i) in quotations.links" 
                            :key="i"
                            :disabled="!link.url || link.active"
                            @click="link.url && router.get(link.url)"
                            v-html="link.label"
                            :class="[
                                link.active ? 'bg-[#4b5320] text-white font-bold' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50',
                                !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="px-3 py-1 text-xs rounded-lg shadow-2xs transition-colors">
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- CREATE / EDIT QUOTATION MODAL -->
    <!-- ========================================================================= -->
    <div v-if="showCreateEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 overflow-y-auto">
        <div class="bg-white border border-gray-200 rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/70">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-[#F8B803]/20 text-[#4b5320] rounded-lg">
                        <FileText class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">
                            {{ isEditing ? `Edit Quotation (${form.id})` : 'Create New Quotation' }}
                        </h3>
                        <p class="text-xs text-gray-500">Select items from inventory with live pricing and dynamic total calculations</p>
                    </div>
                </div>
                <button @click="showCreateEditModal = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Error Banner -->
            <div v-if="formError" class="mx-6 mt-4 p-3.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-center gap-2">
                <AlertCircle class="w-4 h-4 shrink-0 text-rose-600" />
                <span class="font-medium">{{ formError }}</span>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto space-y-6">
                <!-- Section 1: Client Information -->
                <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-200">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-1.5">
                            <Building2 class="w-4 h-4 text-[#4b5320]" />
                            <span>Client / Customer Information</span>
                        </h4>

                        <!-- Quick Select Existing Client -->
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <span class="text-xs text-gray-500">Load Existing:</span>
                            <select v-model="selectedExistingClientId" class="py-1 px-2.5 bg-white border border-gray-300 rounded text-xs text-gray-800 focus:border-[#F8B803] focus:outline-none">
                                <option value="">-- Choose Existing Client --</option>
                                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <!-- Company / Client Name -->
                        <div class="relative">
                            <input id="q_client_name"
                                   v-model="form.client_name" 
                                   type="text" 
                                   placeholder=" " 
                                   class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" 
                                   required />
                            <label for="q_client_name"
                                   class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                   :class="form.client_name ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                                Company / Client Name
                            </label>
                        </div>

                        <!-- Contact Person -->
                        <div class="relative">
                            <input id="q_contact_person"
                                   v-model="form.contact_person" 
                                   type="text" 
                                   placeholder=" " 
                                   class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" />
                            <label for="q_contact_person"
                                   class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                   :class="form.contact_person ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                                Contact Person
                            </label>
                        </div>

                        <!-- Email Address -->
                        <div class="relative">
                            <input id="q_email"
                                   v-model="form.email" 
                                   type="email" 
                                   placeholder=" " 
                                   class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" />
                            <label for="q_email"
                                   class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                   :class="form.email ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                                Email Address
                            </label>
                        </div>

                        <!-- Contact / Phone Number -->
                        <div class="relative">
                            <input id="q_phone"
                                   v-model="form.phone" 
                                   type="text" 
                                   placeholder=" " 
                                   class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" />
                            <label for="q_phone"
                                   class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                   :class="form.phone ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                                Contact / Phone Number
                            </label>
                        </div>

                        <!-- Project Site / Billing Address -->
                        <div class="relative md:col-span-2">
                            <input id="q_address"
                                   v-model="form.address" 
                                   type="text" 
                                   placeholder=" " 
                                   class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" />
                            <label for="q_address"
                                   class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                   :class="form.address ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                                Project Site / Billing Address
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Quotation Metadata -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Quotation Date -->
                    <div class="relative">
                        <input id="q_date"
                               v-model="form.quotation_date" 
                               type="date" 
                               class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" 
                               required />
                        <label for="q_date"
                               class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]">
                            Quotation Date
                        </label>
                    </div>

                    <!-- Valid Until -->
                    <div class="relative">
                        <input id="q_valid_until"
                               v-model="form.valid_until" 
                               type="date" 
                               :min="form.quotation_date" 
                               class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm" 
                               required />
                        <label for="q_valid_until"
                               class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]">
                            Valid Until / Expiration
                        </label>
                    </div>

                    <!-- Initial Status -->
                    <div class="relative">
                        <select id="q_status"
                                v-model="form.status" 
                                class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm">
                            <option value="Draft">Draft</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                        </select>
                        <label for="q_status"
                               class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]">
                            Initial Status
                        </label>
                    </div>
                </div>

                <!-- Section 3: Quotation Line Items (Inventory Integration) -->
                <div>
                    <div class="flex justify-between items-center mb-2 pb-1 border-b border-gray-100">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-1.5">
                                <Layers class="w-4 h-4 text-[#4b5320]" />
                                <span>Quotation Line Items</span>
                            </h4>
                            <p class="text-[11px] text-gray-400">Products are populated directly from inventory with real-time stock verification.</p>
                        </div>
                        <button type="button" @click="addItem" class="px-3 py-1.5 bg-[#F8B803]/20 hover:bg-[#F8B803]/30 text-[#4b5320] font-bold rounded-lg text-xs transition-colors flex items-center gap-1">
                            <Plus class="w-3.5 h-3.5" />
                            <span>Add Product</span>
                        </button>
                    </div>

                    <!-- Items Table -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                                <tr>
                                    <th class="p-2.5 font-semibold w-5/12">Product Selection / SKU</th>
                                    <th class="p-2.5 font-semibold w-24 text-center">Stock</th>
                                    <th class="p-2.5 font-semibold w-20">Qty *</th>
                                    <th class="p-2.5 font-semibold w-28">Unit Price (₱)</th>
                                    <th class="p-2.5 font-semibold w-24">Discount (₱)</th>
                                    <th class="p-2.5 font-semibold text-right w-28">Subtotal</th>
                                    <th class="p-2.5 text-center w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-gray-50/60 transition-colors">
                                    <!-- Product Dropdown -->
                                    <td class="p-2.5">
                                        <select :value="item.product_id" 
                                                @change="onProductSelect(index, Number(($event.target as HTMLSelectElement).value))"
                                                class="w-full bg-white border border-gray-200 rounded p-1.5 text-xs text-gray-900 focus:border-[#F8B803] focus:outline-none mb-1">
                                            <option :value="null">-- Select Product from Inventory --</option>
                                            <option v-for="p in products" :key="p.id" :value="p.id">
                                                {{ p.name }} ({{ p.sku }}) - ₱{{ p.price }} [Stock: {{ p.stock }}]
                                            </option>
                                        </select>
                                        <div class="flex items-center gap-2">
                                            <input v-model="item.product_name" type="text" placeholder="Custom Product Name" class="flex-1 bg-gray-50/50 border border-gray-200 rounded p-1 text-[11px] text-gray-900 focus:outline-none" />
                                            <input v-model="item.sku" type="text" placeholder="SKU" class="w-28 font-mono bg-gray-50/50 border border-gray-200 rounded p-1 text-[11px] text-gray-500 focus:outline-none" />
                                        </div>
                                    </td>

                                    <!-- Stock & Stock Warning -->
                                    <td class="p-2.5 text-center">
                                        <span :class="item.stock <= 0 ? 'bg-rose-100 text-rose-700' : (item.quantity > item.stock ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700')"
                                              class="px-2 py-0.5 rounded-full text-[10px] font-bold block">
                                            {{ item.stock }} in stock
                                        </span>
                                        <span v-if="item.quantity > item.stock" class="text-[10px] text-rose-600 font-semibold block mt-1 leading-tight">
                                            ⚠️ Insufficient stock
                                        </span>
                                    </td>

                                    <!-- Qty -->
                                    <td class="p-2.5">
                                        <input v-model.number="item.quantity" type="number" min="1" class="w-full bg-white border border-gray-200 rounded p-1.5 text-xs text-center font-bold text-gray-900 focus:border-[#F8B803] focus:outline-none" />
                                    </td>

                                    <!-- Unit Price -->
                                    <td class="p-2.5">
                                        <input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="w-full bg-white border border-gray-200 rounded p-1.5 text-xs text-right font-medium text-gray-900 focus:border-[#F8B803] focus:outline-none" />
                                    </td>

                                    <!-- Discount -->
                                    <td class="p-2.5">
                                        <input v-model.number="item.discount" type="number" min="0" step="0.01" class="w-full bg-white border border-gray-200 rounded p-1.5 text-xs text-right text-gray-600 focus:border-[#F8B803] focus:outline-none" />
                                    </td>

                                    <!-- Item Subtotal -->
                                    <td class="p-2.5 text-right font-mono font-bold text-gray-900">
                                        {{ formatCurrency(Math.max(0, (item.unit_price - item.discount) * item.quantity)) }}
                                    </td>

                                    <!-- Delete Item -->
                                    <td class="p-2.5 text-center">
                                        <button type="button" @click="removeItem(index)" class="text-gray-400 hover:text-rose-600 p-1 rounded hover:bg-rose-50" :disabled="form.items.length === 1">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 4: Notes, Terms, & Financial Calculation Summary -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-gray-100">
                    <!-- Left: Notes & Terms -->
                    <div class="space-y-3">
                        <div class="relative">
                            <textarea id="q_notes"
                                      v-model="form.notes" 
                                      rows="2" 
                                      placeholder=" " 
                                      class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"></textarea>
                            <label for="q_notes"
                                   class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                   :class="form.notes ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                                Internal Notes / Scope Description
                            </label>
                        </div>

                        <div class="relative">
                            <textarea id="q_terms"
                                      v-model="form.terms_and_conditions" 
                                      rows="3" 
                                      placeholder=" " 
                                      class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-600 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"></textarea>
                            <label for="q_terms"
                                   class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                   :class="form.terms_and_conditions ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''">
                                Terms & Conditions
                            </label>
                        </div>
                    </div>

                    <!-- Right: Dynamic Totals Panel -->
                    <div class="bg-gray-50/80 p-4 rounded-xl border border-gray-200 flex flex-col justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3">Calculated Quotation Summary</h4>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between text-gray-600">
                                <span>Items Subtotal:</span>
                                <span class="font-mono font-semibold">{{ formatCurrency(formSubtotal) }}</span>
                            </div>

                            <div class="flex justify-between items-center text-gray-600">
                                <span>Quotation Discount (₱):</span>
                                <input v-model.number="form.discount" type="number" min="0" step="0.01" class="w-24 bg-white border border-gray-200 rounded p-1 text-xs text-right font-mono font-semibold text-rose-600 focus:outline-none" />
                            </div>

                            <div class="flex justify-between items-center text-gray-600">
                                <div class="flex items-center gap-1">
                                    <span>Value Added Tax (VAT %):</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input v-model.number="form.tax_rate" type="number" min="0" max="100" step="0.5" class="w-16 bg-white border border-gray-200 rounded p-1 text-xs text-right font-mono text-gray-800 focus:outline-none" />
                                    <span class="font-mono font-semibold">{{ formatCurrency(formTaxAmount) }}</span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-gray-200 flex justify-between items-baseline">
                                <span class="text-sm font-bold text-gray-900">Grand Total:</span>
                                <span class="text-2xl font-black text-[#4b5320] font-mono">{{ formatCurrency(formGrandTotal) }}</span>
                            </div>
                        </div>

                        <div class="mt-4 p-2 bg-emerald-50 rounded-lg border border-emerald-200 text-[11px] text-emerald-800 flex items-center gap-1.5">
                            <CheckCircle2 class="w-3.5 h-3.5 shrink-0 text-emerald-600" />
                            <span>Creating a quotation does not deduct inventory stock until converted to sale.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end items-center gap-3">
                <SpecularButton
                    type="button"
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showCreateEditModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button" 
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="promptSaveQuotation" 
                    :disabled="isSubmitting"
                >
                    <Check class="w-4 h-4" />
                    <span>{{ isEditing ? 'Update Quotation' : 'Save Quotation' }}</span>
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- UPDATE STATUS MODAL -->
    <!-- ========================================================================= -->
    <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Update Quotation Status</h3>
            <p class="text-xs text-gray-500 mb-4">Quotation: {{ activeQuotation?.quotation_number }} ({{ activeQuotation?.client_name }})</p>

            <div class="space-y-3">
                <label class="block text-xs font-semibold text-gray-700">Select New Status</label>
                <div class="grid grid-cols-1 gap-2">
                    <label v-for="opt in statusOptions" :key="opt.value" 
                           :class="targetStatus === opt.value ? 'border-[#F8B803] bg-[#F8B803]/10 ring-1 ring-[#F8B803]' : 'border-gray-200 bg-white'"
                           class="border rounded-xl p-3 cursor-pointer flex items-center justify-between transition-all">
                        <div class="flex items-center gap-2">
                            <input type="radio" v-model="targetStatus" :value="opt.value" class="accent-[#4b5320]" />
                            <span class="text-sm font-bold text-gray-900">{{ opt.label }}</span>
                        </div>
                        <span :class="opt.badgeClass" class="text-xs px-2 py-0.5 rounded-full border">
                            {{ opt.label }}
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-3 border-t border-gray-100">
                <SpecularButton
                    type="button"
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showStatusModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    variant="brand"
                    size="md"
                    :radius="10"
                    :disabled="isUpdatingStatus"
                    @click="promptUpdateStatus"
                >
                    {{ isUpdatingStatus ? 'Updating...' : 'Update Status' }}
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- CONVERT TO SALE MODAL -->
    <!-- ========================================================================= -->
    <div v-if="showConvertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-lg shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-emerald-100 text-emerald-700 rounded-xl">
                    <ShoppingCart class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Convert Quote to Sale</h3>
                    <p class="text-xs text-gray-500">Record sales invoice and deduct items from inventory stock</p>
                </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-2 text-xs mb-4">
                <div class="flex justify-between"><span class="text-gray-500">Quotation ID:</span> <span class="font-bold text-gray-900">{{ activeQuotation?.quotation_number }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Client / Company:</span> <span class="font-bold text-gray-900">{{ activeQuotation?.client_name }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Items to Transfer:</span> <span class="font-bold text-gray-900">{{ activeQuotation?.items.length }} items</span></div>
                <div class="flex justify-between pt-2 border-t border-gray-200"><span class="text-gray-700 font-bold">Total Sales Amount:</span> <span class="font-black text-base text-[#4b5320]">{{ formatCurrency(activeQuotation?.grand_total || 0) }}</span></div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Method</label>
                <select v-model="paymentMethod" class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-xs text-gray-900 focus:border-[#F8B803] focus:outline-none">
                    <option>Bank Transfer / Check</option>
                    <option>Cash on Delivery (COD)</option>
                    <option>Direct Cash</option>
                    <option>Credit Terms (30 Days)</option>
                </select>
            </div>

            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2 mb-6">
                <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <span>
                    Executing this conversion will immediately deduct the required item quantities from your active Inventory database and mark this quotation as <strong>Completed</strong>.
                </span>
            </div>

            <div class="flex justify-end gap-3">
                <SpecularButton
                    type="button"
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showConvertModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    variant="success"
                    size="md"
                    :radius="10"
                    @click="convertToSaleSubmit"
                >
                    <Check class="w-4 h-4" />
                    <span>Confirm & Execute Sale</span>
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- PROFESSIONAL PRINT / PREVIEW MODAL -->
    <!-- ========================================================================= -->
    <div v-if="showPrintModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4 overflow-y-auto print:p-0 print:bg-white print:static">
        <div class="bg-white border border-gray-200 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] print:max-h-none print:shadow-none print:border-none">
            <!-- Modal Actions Bar (Hidden in print) -->
            <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex justify-between items-center print:hidden">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <Printer class="w-4 h-4 text-[#4b5320]" />
                    <span>Print Preview — Quotation #{{ activeQuotation?.quotation_number }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="triggerPrint" class="px-4 py-2 bg-[#4b5320] text-white font-bold text-xs rounded-lg shadow-sm hover:opacity-95 flex items-center gap-2">
                        <Printer class="w-4 h-4" />
                        <span>Print Quotation</span>
                    </button>
                    <button @click="showPrintModal = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Printable Quotation Sheet -->
            <div id="quotation-print-sheet" class="p-8 sm:p-12 overflow-y-auto space-y-8 bg-white text-gray-900">
                <!-- Branded Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start border-b-2 border-[#4b5320] pb-6 gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-[#4b5320] tracking-tight">MR-PRIDE ELECTRICAL SUPPLIES</h1>
                        <p class="text-xs text-gray-500 mt-1 uppercase font-semibold">Industrial & Commercial Electrical Solutions</p>
                        <p class="text-xs text-gray-500 mt-1">Direct Electrical Importers & Authorized Distributor</p>
                        <p class="text-xs text-gray-500">Contact: +63 (2) 8123-4567 | info@mrpelectrodesk.ph</p>
                    </div>

                    <div class="sm:text-right">
                        <span class="inline-block px-3 py-1 bg-[#F8B803]/20 text-[#4b5320] font-black text-xl tracking-widest rounded-lg mb-2">
                            QUOTATION
                        </span>
                        <p class="text-sm font-bold text-gray-900 font-mono">No: {{ activeQuotation?.quotation_number }}</p>
                        <p class="text-xs text-gray-500 mt-1">Date: <strong class="text-gray-800">{{ activeQuotation?.quotation_date }}</strong></p>
                        <p class="text-xs text-gray-500">Valid Until: <strong class="text-gray-800">{{ activeQuotation?.valid_until }}</strong></p>
                        <p class="text-xs text-gray-500 mt-1">Status: <span class="font-bold uppercase text-[#4b5320]">{{ activeQuotation?.status }}</span></p>
                    </div>
                </div>

                <!-- Client Information Block -->
                <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                    <h4 class="text-xs font-bold uppercase text-[#4b5320] tracking-wider mb-3">CLIENT INFORMATION</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <p class="text-gray-400 font-semibold uppercase text-[10px]">Company Name</p>
                            <p class="text-sm font-bold text-gray-900 mt-0.5">{{ activeQuotation?.client_name }}</p>
                            <p v-if="activeQuotation?.contact_person" class="text-gray-600 mt-1">Attention: <strong>{{ activeQuotation?.contact_person }}</strong></p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-semibold uppercase text-[10px]">Contact Details</p>
                            <p v-if="activeQuotation?.email" class="text-gray-700 mt-0.5">Email: {{ activeQuotation?.email }}</p>
                            <p v-if="activeQuotation?.phone" class="text-gray-700">Phone: {{ activeQuotation?.phone }}</p>
                            <p v-if="activeQuotation?.address" class="text-gray-700 mt-1">Address: {{ activeQuotation?.address }}</p>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div>
                    <h4 class="text-xs font-bold uppercase text-[#4b5320] tracking-wider mb-2">QUOTED ITEMS & MATERIALS</h4>
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 border-b border-gray-300">
                                <th class="py-2.5 px-3 font-bold w-12 text-center">#</th>
                                <th class="py-2.5 px-3 font-bold">Product Description</th>
                                <th class="py-2.5 px-3 font-bold w-28">SKU</th>
                                <th class="py-2.5 px-3 font-bold text-center w-16">Qty</th>
                                <th class="py-2.5 px-3 font-bold text-right w-24">Unit Price</th>
                                <th class="py-2.5 px-3 font-bold text-right w-20">Discount</th>
                                <th class="py-2.5 px-3 font-bold text-right w-28">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(item, idx) in activeQuotation?.items" :key="idx" class="hover:bg-gray-50">
                                <td class="py-3 px-3 text-center text-gray-400 font-mono">{{ idx + 1 }}</td>
                                <td class="py-3 px-3">
                                    <p class="font-bold text-gray-900">{{ item.product_name }}</p>
                                </td>
                                <td class="py-3 px-3 font-mono text-gray-500 text-[11px]">{{ item.sku }}</td>
                                <td class="py-3 px-3 text-center font-bold text-gray-900">{{ item.quantity }}</td>
                                <td class="py-3 px-3 text-right font-medium text-gray-800">{{ formatCurrency(item.unit_price) }}</td>
                                <td class="py-3 px-3 text-right text-gray-500">{{ item.discount > 0 ? formatCurrency(item.discount) : '-' }}</td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals Section -->
                <div class="flex justify-end pt-2">
                    <div class="w-full sm:w-72 space-y-2 text-xs">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal:</span>
                            <span class="font-mono font-bold">{{ formatCurrency(activeQuotation?.subtotal || 0) }}</span>
                        </div>
                        <div v-if="activeQuotation?.discount && activeQuotation.discount > 0" class="flex justify-between text-rose-600">
                            <span>Discount:</span>
                            <span class="font-mono font-bold">-{{ formatCurrency(activeQuotation.discount) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Value Added Tax (12% VAT):</span>
                            <span class="font-mono font-bold">{{ formatCurrency(activeQuotation?.tax_amount || 0) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t-2 border-gray-900 text-sm">
                            <span class="font-black text-gray-900 uppercase">Grand Total:</span>
                            <span class="font-black text-base text-[#4b5320] font-mono">{{ formatCurrency(activeQuotation?.grand_total || 0) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes & Terms -->
                <div class="pt-4 border-t border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs text-gray-600">
                    <div v-if="activeQuotation?.notes">
                        <h5 class="font-bold uppercase text-gray-700 text-[10px] mb-1">Notes & Scope:</h5>
                        <p class="whitespace-pre-line leading-relaxed bg-gray-50 p-3 rounded-lg border border-gray-200">{{ activeQuotation?.notes }}</p>
                    </div>

                    <div v-if="activeQuotation?.terms_and_conditions">
                        <h5 class="font-bold uppercase text-gray-700 text-[10px] mb-1">Terms & Conditions:</h5>
                        <p class="whitespace-pre-line leading-relaxed bg-gray-50 p-3 rounded-lg border border-gray-200 text-[11px]">{{ activeQuotation?.terms_and_conditions }}</p>
                    </div>
                </div>

                <!-- Signature & Conforme Block -->
                <div class="pt-8 border-t border-gray-200 grid grid-cols-2 gap-8 text-xs">
                    <div>
                        <p class="text-gray-500 mb-8">Prepared By:</p>
                        <div class="border-b border-gray-400 w-48 mb-1"></div>
                        <p class="font-bold text-gray-900">{{ activeQuotation?.prepared_by?.name || 'Authorized Technical Sales' }}</p>
                        <p class="text-gray-400 text-[10px]">MR-PRIDE Electrical Supplies</p>
                    </div>

                    <div>
                        <p class="text-gray-500 mb-8">Conforme / Customer Approval:</p>
                        <div class="border-b border-gray-400 w-48 mb-1"></div>
                        <p class="font-bold text-gray-900">Authorized Client Signature</p>
                        <p class="text-gray-400 text-[10px]">Date Signed:</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- CONFIRM DELETE MODAL -->
    <!-- ========================================================================= -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-4">
                <Trash2 class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Quotation</h3>
            <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                Are you sure you want to delete quotation <strong class="text-gray-900">"{{ activeQuotation?.quotation_number }}"</strong> for {{ activeQuotation?.client_name }}? This action cannot be undone.
            </p>
            <div class="flex justify-end gap-3">
                <SpecularButton
                    type="button"
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showDeleteModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    type="button"
                    variant="destructive"
                    size="md"
                    :radius="10"
                    @click="confirmDelete"
                >
                    Delete
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- Action Confirmation Modal -->
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
                    :disabled="isSubmitting"
                >
                    <Check class="w-4 h-4 mr-1.5 inline" />
                    <span>{{ confirmOptions.confirmText || 'Confirm & Save' }}</span>
                </SpecularButton>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #quotation-print-sheet, #quotation-print-sheet * {
        visibility: visible;
    }
    #quotation-print-sheet {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
    }
}
</style>
