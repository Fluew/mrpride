<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import { 
    Search, 
    Plus, 
    Printer, 
    Receipt, 
    ShoppingCart, 
    TrendingUp, 
    Calendar, 
    CheckCircle2, 
    Check,
    Clock, 
    Trash2, 
    X,
    CreditCard,
    Building2,
    Smartphone,
    Upload,
    FileText,
    ExternalLink
} from '@lucide/vue';

interface SaleItem {
    id: number;
    sku: string;
    product_name: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
}

interface Sale {
    id: number;
    invoice_number: string;
    client_name: string;
    sale_date: string;
    subtotal: number;
    discount: number;
    tax_amount: number;
    grand_total: number;
    status: string;
    payment_method?: string;
    payment_reference?: string;
    amount_paid?: number;
    payment_date?: string;
    proof_of_payment?: string;
    proof_url?: string;
    notes?: string;
    items?: SaleItem[];
}

interface ProductItem {
    id: number;
    sku: string;
    name: string;
    price: number;
    stock: number;
    category: string;
}

const props = defineProps<{
    sales: {
        data: Sale[];
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
        totalSales: number;
        todaySales: number;
        monthSales: number;
        pendingPayments: number;
    };
    products: ProductItem[];
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
            `/${teamSlug.value}/sales`,
            {
                search: search.value || undefined,
                status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
};

// POS State
const posModalOpen = ref(false);
const productSearch = ref('');
const currentCustomer = ref('');
const paymentMethod = ref<'Bank Transfer' | 'GCash'>('Bank Transfer');
const paymentReference = ref('');
const amountPaid = ref<number | ''>('');
const paymentDate = ref(new Date().toISOString().split('T')[0]);
const proofOfPaymentFile = ref<File | null>(null);
const proofFileName = ref('');
const notes = ref('');
const cart = ref<Array<{
    product_id: number;
    name: string;
    sku: string;
    price: number;
    stock: number;
    qty: number;
}>>([]);

const filteredProducts = computed(() => {
    const q = productSearch.value.toLowerCase().trim();
    if (!q) return props.products;
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || p.category.toLowerCase().includes(q)
    );
});

const openPosModal = () => {
    currentCustomer.value = '';
    paymentMethod.value = 'Bank Transfer';
    paymentReference.value = '';
    amountPaid.value = '';
    paymentDate.value = new Date().toISOString().split('T')[0];
    proofOfPaymentFile.value = null;
    proofFileName.value = '';
    notes.value = '';
    cart.value = [];
    productSearch.value = '';
    posModalOpen.value = true;
};

const closePosModal = () => {
    posModalOpen.value = false;
};

const addToCart = (product: ProductItem) => {
    const existing = cart.value.find(item => item.product_id === product.id);
    if (existing) {
        if (existing.qty < product.stock) {
            existing.qty++;
        }
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            sku: product.sku,
            price: Number(product.price),
            stock: product.stock,
            qty: 1,
        });
    }
};

const updateQty = (index: number, newQty: number) => {
    const item = cart.value[index];
    if (newQty <= 0) {
        cart.value.splice(index, 1);
    } else if (newQty <= item.stock) {
        item.qty = newQty;
    }
};

const removeFromCart = (index: number) => {
    cart.value.splice(index, 1);
};

const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.price * item.qty, 0);
});

// Auto-sync amount paid with cart total when total changes
watch(cartTotal, (newTotal) => {
    if (newTotal > 0) {
        amountPaid.value = Number(newTotal.toFixed(2));
    } else {
        amountPaid.value = '';
    }
});

const handleProofFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        proofOfPaymentFile.value = target.files[0];
        proofFileName.value = target.files[0].name;
    } else {
        proofOfPaymentFile.value = null;
        proofFileName.value = '';
    }
};

const clearProofFile = () => {
    proofOfPaymentFile.value = null;
    proofFileName.value = '';
};

const canComplete = computed(() => {
    return (
        cart.value.length > 0 &&
        currentCustomer.value.trim().length > 0 &&
        paymentReference.value.trim().length > 0 &&
        amountPaid.value !== '' &&
        Number(amountPaid.value) > 0 &&
        Boolean(paymentDate.value) &&
        !isSubmitting.value
    );
});

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
    confirmText: 'Confirm & Record',
    action: () => {},
});

const openConfirmModal = (options: ConfirmActionOptions) => {
    confirmOptions.value = {
        confirmText: 'Confirm & Record',
        ...options,
    };
    showConfirmModal.value = true;
};

const handleConfirmedAction = () => {
    showConfirmModal.value = false;
    confirmOptions.value.action();
};

const promptCompleteTransaction = () => {
    if (!canComplete.value) return;

    const itemCount = cart.value.reduce((s, i) => s + i.qty, 0);

    openConfirmModal({
        title: 'Confirm Sale Transaction',
        description: `Are you sure you want to finalize and record this sale for "${currentCustomer.value.trim()}"?`,
        details: [
            { label: 'Customer Name', value: currentCustomer.value.trim() },
            { label: 'Payment Method', value: paymentMethod.value },
            { label: 'Payment Reference', value: paymentReference.value.trim() },
            { label: 'Amount Paid', value: formatCurrency(Number(amountPaid.value)) },
            { label: 'Payment Date', value: paymentDate.value },
            { label: 'Proof of Payment', value: proofFileName.value ? proofFileName.value : 'None attached' },
            { label: 'Items in Order', value: `${cart.value.length} distinct items (${itemCount} units)` },
            { label: 'Total Amount', value: formatCurrency(cartTotal.value) },
        ],
        confirmText: 'Complete Sale',
        action: completeTransaction,
    });
};

const isSubmitting = ref(false);
const completeTransaction = () => {
    if (!canComplete.value) return;

    isSubmitting.value = true;
    router.post(
        `/${teamSlug.value}/sales`,
        {
            customer: currentCustomer.value.trim(),
            payment_method: paymentMethod.value,
            payment_reference: paymentReference.value.trim(),
            amount_paid: Number(amountPaid.value),
            payment_date: paymentDate.value,
            notes: notes.value || undefined,
            proof_of_payment: proofOfPaymentFile.value || undefined,
            items: cart.value.map(item => ({
                product_id: item.product_id,
                qty: item.qty,
            })),
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                isSubmitting.value = false;
                closePosModal();
            },
            onError: () => {
                isSubmitting.value = false;
            },
        }
    );
};

// Invoice Modal State
const selectedInvoice = ref<Sale | null>(null);
const invoiceModalOpen = ref(false);

const viewInvoice = (sale: Sale) => {
    selectedInvoice.value = sale;
    invoiceModalOpen.value = true;
};

const printSheet = () => {
    window.print();
};
</script>

<template>
    <Head title="Sales Management" />

    <div class="workspace-page">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Sales & Point of Sale</h1>
                <p class="text-gray-500 mt-1">Record store sales, process payments, and automatically reconcile inventory.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="10"
                @click="openPosModal"
                class="w-full md:w-auto"
            >
                <Plus class="w-4 h-4 mr-1.5 inline" />
                New Sale (POS)
            </SpecularButton>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Total Sales</span>
                    <Receipt class="w-4 h-4 text-gray-400" />
                </div>
                <div class="text-2xl font-black text-gray-900">{{ metrics.totalSales }}</div>
                <div class="text-xs text-gray-500 mt-1">Completed transactions</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Today's Revenue</span>
                    <TrendingUp class="w-4 h-4 text-emerald-500" />
                </div>
                <div class="text-2xl font-black text-emerald-600">{{ formatCurrency(metrics.todaySales) }}</div>
                <div class="text-xs text-gray-500 mt-1">Earned today</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Month Revenue</span>
                    <Calendar class="w-4 h-4 text-[#F8B803]" />
                </div>
                <div class="text-2xl font-black text-[#F8B803]">{{ formatCurrency(metrics.monthSales) }}</div>
                <div class="text-xs text-gray-500 mt-1">Current month to date</div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span>Pending Payments</span>
                    <Clock class="w-4 h-4 text-amber-500" />
                </div>
                <div class="text-2xl font-black text-amber-600">{{ metrics.pendingPayments }}</div>
                <div class="text-xs text-gray-500 mt-1">Awaiting settlement</div>
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
                    placeholder="Search by invoice number or client name..."
                    class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm"
                />
            </div>
            <select
                v-model="selectedStatus"
                @change="applyFilter"
                class="bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803] shadow-sm min-w-[150px]"
            >
                <option value="All">All Statuses</option>
                <option value="Completed">Completed</option>
                <option value="Pending Payment">Pending Payment</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>

        <!-- Sales History Table -->
        <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-sm flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-medium">Invoice ID</th>
                            <th class="px-6 py-4 font-medium">Date & Time</th>
                            <th class="px-6 py-4 font-medium">Customer</th>
                            <th class="px-6 py-4 font-medium">Items</th>
                            <th class="px-6 py-4 font-medium">Payment Method</th>
                            <th class="px-6 py-4 font-medium">Total Amount</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!sales.data.length">
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                No sales records found. Click "+ New Sale (POS)" to record a transaction.
                            </td>
                        </tr>
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-mono font-bold text-[#F8B803]">{{ sale.invoice_number }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ new Date(sale.sale_date).toLocaleString() }}</td>
                            <td class="px-6 py-4 font-medium text-gray-900">{{ sale.client_name }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ sale.items?.length || 0 }} items</td>
                            <td class="px-6 py-4 text-gray-600 text-xs">
                                <div class="font-medium text-gray-900">{{ sale.payment_method || 'Bank Transfer' }}</div>
                                <div v-if="sale.payment_reference" class="text-[11px] text-gray-500 font-mono mt-0.5">
                                    Ref: {{ sale.payment_reference }}
                                </div>
                            </td>
                            <td class="px-6 py-4 font-black text-gray-900">{{ formatCurrency(Number(sale.grand_total)) }}</td>
                            <td class="px-6 py-4">
                                <span :class="[
                                    'px-2.5 py-1 rounded-full text-xs font-semibold border',
                                    sale.status === 'Completed' ? 'bg-green-50 text-green-700 border-green-200' :
                                    sale.status === 'Pending Payment' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                    'bg-gray-50 text-gray-600 border-gray-200'
                                ]">
                                    {{ sale.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <SpecularButton
                                    variant="secondary"
                                    size="sm"
                                    :radius="8"
                                    @click="viewInvoice(sale)"
                                >
                                    <Printer class="w-3.5 h-3.5 mr-1 inline" />
                                    Invoice
                                </SpecularButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="sales.total > sales.data.length" class="p-4 flex items-center justify-between border-t border-gray-100">
                <span class="text-xs text-gray-500">
                    Showing {{ sales.data.length }} of {{ sales.total }} transactions
                </span>
                <div class="flex gap-1">
                    <button
                        v-for="(link, i) in sales.links"
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

        <!-- POS Modal -->
        <div v-if="posModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-6xl flex flex-col md:flex-row h-[90vh] overflow-hidden">
                
                <!-- Left: Catalog -->
                <div class="w-full md:w-7/12 border-r border-gray-200 flex flex-col h-full overflow-hidden">
                    <div class="p-4 border-b border-gray-200 bg-gray-50 shrink-0">
                        <h2 class="text-lg font-bold text-gray-900 mb-2">Select Products</h2>
                        <div class="relative">
                            <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="productSearch"
                                type="text"
                                placeholder="Search by name, SKU or category..."
                                class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm"
                            />
                        </div>
                    </div>
                    
                    <div class="p-4 flex-1 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 auto-rows-max">
                        <div
                            v-for="p in filteredProducts"
                            :key="p.id"
                            @click="addToCart(p)"
                            class="bg-white border border-gray-200 hover:border-[#F8B803] rounded-xl p-3.5 cursor-pointer shadow-sm transition-all hover:shadow flex flex-col justify-between h-32 group"
                        >
                            <div>
                                <span class="text-xs text-gray-400 font-mono block">{{ p.sku }}</span>
                                <h4 class="text-gray-900 font-semibold text-sm leading-snug line-clamp-2 mt-0.5 group-hover:text-[#F8B803] transition-colors">{{ p.name }}</h4>
                            </div>
                            <div class="flex justify-between items-baseline pt-2 border-t border-gray-50">
                                <span class="text-xs text-gray-500">Stock: <strong class="text-gray-700">{{ p.stock }}</strong></span>
                                <span class="text-[#F8B803] font-black text-sm">{{ formatCurrency(Number(p.price)) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Cart & Checkout -->
                <div class="w-full md:w-5/12 flex flex-col bg-gray-50 h-full overflow-hidden">
                    <div class="p-3.5 border-b border-gray-200 bg-white flex justify-between items-center shrink-0">
                        <div class="flex items-center gap-2">
                            <ShoppingCart class="w-5 h-5 text-gray-700" />
                            <h2 class="text-base font-bold text-gray-900">Current Order</h2>
                        </div>
                        <button @click="closePosModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    <!-- Scrollable Form & Cart Section -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-3.5">
                        <!-- Customer Name Input -->
                        <div>
                            <label for="pos_customer" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Customer Name <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="pos_customer"
                                v-model="currentCustomer"
                                type="text"
                                placeholder="Customer Name"
                                class="block w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm placeholder:text-gray-400 font-medium shadow-xs"
                                required
                            />
                        </div>

                        <!-- Select payment method -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                                Select payment method <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="paymentMethod = 'Bank Transfer'"
                                    :class="[
                                        'flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border text-xs font-bold transition-all shadow-xs cursor-pointer',
                                        paymentMethod === 'Bank Transfer'
                                            ? 'border-[#4b5320] bg-[#4b5320] text-white shadow-sm ring-2 ring-[#4b5320]/20'
                                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50'
                                    ]"
                                >
                                    <Building2 class="w-4 h-4 shrink-0" />
                                    <span>Bank Transfer</span>
                                </button>

                                <button
                                    type="button"
                                    @click="paymentMethod = 'GCash'"
                                    :class="[
                                        'flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border text-xs font-bold transition-all shadow-xs cursor-pointer',
                                        paymentMethod === 'GCash'
                                            ? 'border-[#007DFE] bg-[#007DFE] text-white shadow-sm ring-2 ring-[#007DFE]/20'
                                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50'
                                    ]"
                                >
                                    <Smartphone class="w-4 h-4 shrink-0" />
                                    <span>GCash</span>
                                </button>
                            </div>
                        </div>

                        <!-- Enter payment details -->
                        <div class="rounded-xl border border-gray-200 bg-white p-3.5 space-y-3 shadow-xs">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                <span class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <Receipt class="w-3.5 h-3.5 text-[#4b5320]" />
                                    Enter payment details
                                </span>
                                <span class="text-[11px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">
                                    {{ paymentMethod }}
                                </span>
                            </div>

                            <!-- Payment reference number (required) -->
                            <div>
                                <label for="pos_ref" class="block text-xs font-semibold text-gray-700 mb-1">
                                    Payment reference number <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="pos_ref"
                                    v-model="paymentReference"
                                    type="text"
                                    :placeholder="paymentMethod === 'GCash' ? 'e.g. GCash Ref # (e.g. 1002348912)' : 'e.g. Bank Transfer Ref / Trace #'"
                                    class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none placeholder:text-gray-400 font-mono shadow-xs"
                                    required
                                />
                            </div>

                            <!-- Amount paid & Payment date -->
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="pos_amount" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Amount paid <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-bold">₱</span>
                                        <input
                                            id="pos_amount"
                                            v-model.number="amountPaid"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            class="block w-full rounded-lg border border-gray-200 bg-white pl-6 pr-2.5 py-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none font-semibold shadow-xs"
                                            required
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label for="pos_date" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Payment date <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        id="pos_date"
                                        v-model="paymentDate"
                                        type="date"
                                        class="block w-full rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none shadow-xs"
                                        required
                                    />
                                </div>
                            </div>

                            <!-- Optional proof of payment, if needed -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                    Optional proof of payment, if needed
                                </label>
                                <div v-if="!proofOfPaymentFile" class="relative">
                                    <input
                                        id="pos_proof"
                                        type="file"
                                        accept="image/*,application/pdf"
                                        @change="handleProofFileChange"
                                        class="sr-only"
                                    />
                                    <label
                                        for="pos_proof"
                                        class="flex items-center justify-center gap-2 w-full px-3 py-2 border border-dashed border-gray-300 rounded-lg bg-gray-50/50 hover:bg-gray-50 cursor-pointer text-xs text-gray-600 transition-colors"
                                    >
                                        <Upload class="w-3.5 h-3.5 text-gray-400" />
                                        <span>Upload receipt / transfer screenshot</span>
                                    </label>
                                </div>
                                <div v-else class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs">
                                    <div class="flex items-center gap-2 truncate text-gray-700">
                                        <FileText class="w-3.5 h-3.5 text-[#4b5320] shrink-0" />
                                        <span class="truncate font-medium">{{ proofFileName }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearProofFile"
                                        class="text-gray-400 hover:text-red-500 ml-2 p-0.5 cursor-pointer"
                                        title="Remove file"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Optional Notes -->
                        <div>
                            <label for="pos_notes" class="block text-xs font-semibold text-gray-700 mb-1">
                                Order Notes <span class="text-gray-400 font-normal">(Optional)</span>
                            </label>
                            <input
                                id="pos_notes"
                                v-model="notes"
                                type="text"
                                placeholder="Add any special instructions or remarks..."
                                class="block w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none placeholder:text-gray-400 shadow-xs"
                            />
                        </div>

                        <!-- Cart Items -->
                        <div class="space-y-2 pt-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Cart Items ({{ cart.length }})
                                </span>
                                <span v-if="cart.length > 0" class="text-xs text-gray-500">
                                    {{ cart.reduce((s, i) => s + i.qty, 0) }} total units
                                </span>
                            </div>

                            <div v-if="cart.length === 0" class="text-gray-400 text-center py-6 text-xs bg-white rounded-xl border border-dashed border-gray-200">
                                <ShoppingCart class="w-6 h-6 mx-auto mb-1 text-gray-300" />
                                Cart is empty. Click products on the left to add items.
                            </div>

                            <div
                                v-for="(item, idx) in cart"
                                :key="item.product_id"
                                class="flex justify-between items-center bg-white border border-gray-200 p-2.5 rounded-xl shadow-xs"
                            >
                                <div class="flex-1 pr-2">
                                    <div class="text-xs font-semibold text-gray-900 line-clamp-1">{{ item.name }}</div>
                                    <div class="text-[11px] text-gray-500">{{ formatCurrency(item.price) }} each</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-white">
                                        <button @click="updateQty(idx, item.qty - 1)" class="px-2 py-0.5 text-gray-600 hover:bg-gray-100 text-xs font-bold cursor-pointer">-</button>
                                        <span class="px-2 text-xs font-semibold text-gray-900">{{ item.qty }}</span>
                                        <button @click="updateQty(idx, item.qty + 1)" class="px-2 py-0.5 text-gray-600 hover:bg-gray-100 text-xs font-bold cursor-pointer">+</button>
                                    </div>
                                    <span class="text-xs font-bold text-gray-900 w-16 text-right">
                                        {{ formatCurrency(item.price * item.qty) }}
                                    </span>
                                    <button @click="removeFromCart(idx)" class="text-gray-400 hover:text-red-500 ml-1 cursor-pointer">
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total & Checkout Footer -->
                    <div class="p-4 bg-white border-t border-gray-200 shrink-0 shadow-xs">
                        <div class="flex justify-between text-gray-500 text-xs mb-1">
                            <span>Subtotal</span>
                            <span>{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-500 text-xs mb-2.5">
                            <span>Payment Method</span>
                            <span class="font-semibold text-gray-800">{{ paymentMethod }}</span>
                        </div>
                        <div class="flex justify-between items-baseline text-gray-900 text-lg font-black mb-3">
                            <span>Total</span>
                            <span class="text-[#F8B803] text-2xl">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        
                        <div v-if="!canComplete && cart.length > 0" class="mb-2 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1">
                            <span v-if="!currentCustomer.trim()">Enter Customer Name to proceed.</span>
                            <span v-else-if="!paymentReference.trim()">Enter Payment Reference Number.</span>
                            <span v-else-if="!amountPaid || Number(amountPaid) <= 0">Enter valid Amount Paid.</span>
                        </div>

                        <SpecularButton
                            variant="brand"
                            size="lg"
                            :radius="10"
                            class="w-full"
                            :disabled="!canComplete || isSubmitting"
                            @click="promptCompleteTransaction"
                        >
                            {{ isSubmitting ? 'Recording...' : 'Complete & Record Sale' }}
                        </SpecularButton>
                    </div>
                </div>

            </div>
        </div>

        <!-- Printable Invoice View Modal -->
        <div v-if="invoiceModalOpen && selectedInvoice" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
                <div class="flex justify-between items-start mb-6 border-b border-gray-100 pb-4">
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 tracking-tight">OFFICIAL RECEIPT / INVOICE</h2>
                        <p class="text-sm text-gray-500">Invoice: <strong class="text-[#F8B803]">{{ selectedInvoice.invoice_number }}</strong></p>
                    </div>
                    <button @click="invoiceModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs text-gray-600 mb-6 bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <div class="space-y-1">
                        <div class="font-bold text-gray-900 mb-1">Customer / Billed To:</div>
                        <div class="text-sm font-semibold text-gray-800">{{ selectedInvoice.client_name }}</div>
                        <div><span class="text-gray-500">Payment Method:</span> <strong class="text-gray-900">{{ selectedInvoice.payment_method || 'Bank Transfer' }}</strong></div>
                        <div v-if="selectedInvoice.payment_reference">
                            <span class="text-gray-500">Payment Reference:</span> <strong class="font-mono text-gray-900">{{ selectedInvoice.payment_reference }}</strong>
                        </div>
                        <div v-if="selectedInvoice.amount_paid">
                            <span class="text-gray-500">Amount Paid:</span> <strong class="text-gray-900">{{ formatCurrency(Number(selectedInvoice.amount_paid)) }}</strong>
                        </div>
                        <div v-if="selectedInvoice.payment_date">
                            <span class="text-gray-500">Payment Date:</span> <strong class="text-gray-900">{{ new Date(selectedInvoice.payment_date).toLocaleDateString() }}</strong>
                        </div>
                        <div v-if="selectedInvoice.proof_url" class="pt-1.5">
                            <a
                                :href="selectedInvoice.proof_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#4b5320] hover:underline"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                                View Proof of Payment Attachment
                            </a>
                        </div>
                    </div>
                    <div class="text-right space-y-1">
                        <div class="font-bold text-gray-900 mb-1">Invoice Details:</div>
                        <div>Date: {{ new Date(selectedInvoice.sale_date).toLocaleString() }}</div>
                        <div>Status: <span class="font-bold text-emerald-600">{{ selectedInvoice.status }}</span></div>
                        <div v-if="selectedInvoice.notes" class="text-gray-500 italic mt-2">
                            Notes: {{ selectedInvoice.notes }}
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <table class="w-full text-left text-xs mb-6">
                    <thead class="border-b border-gray-200 text-gray-500 uppercase">
                        <tr>
                            <th class="py-2">Item Description</th>
                            <th class="py-2 text-center">Qty</th>
                            <th class="py-2 text-right">Price</th>
                            <th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in selectedInvoice.items" :key="item.id">
                            <td class="py-2 text-gray-900 font-medium">{{ item.product_name }}</td>
                            <td class="py-2 text-center text-gray-600">{{ item.quantity }}</td>
                            <td class="py-2 text-right text-gray-600">{{ formatCurrency(Number(item.unit_price)) }}</td>
                            <td class="py-2 text-right font-bold text-gray-900">{{ formatCurrency(Number(item.subtotal)) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="border-t border-gray-200 pt-3 flex justify-between items-baseline text-lg font-black text-gray-900 mb-6">
                    <span>Grand Total</span>
                    <span class="text-[#F8B803] text-2xl">{{ formatCurrency(Number(selectedInvoice.grand_total)) }}</span>
                </div>

                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                    <SpecularButton
                        variant="secondary"
                        size="md"
                        :radius="10"
                        @click="invoiceModalOpen = false"
                    >
                        Close
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        @click="printSheet"
                    >
                        <Printer class="w-4 h-4 mr-1.5 inline" />
                        Print Invoice
                    </SpecularButton>
                </div>
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
                    <span>{{ confirmOptions.confirmText || 'Confirm & Record' }}</span>
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
