<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
    CreditCard
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
const currentCustomer = ref('Walk-in Customer');
const paymentMethod = ref('Cash');
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
    currentCustomer.value = 'Walk-in Customer';
    paymentMethod.value = 'Cash';
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
    if (cart.value.length === 0) return;

    const itemCount = cart.value.reduce((s, i) => s + i.qty, 0);

    openConfirmModal({
        title: 'Confirm Sale Transaction',
        description: `Are you sure you want to finalize and record this sale for "${currentCustomer.value || 'Walk-in Customer'}"?`,
        details: [
            { label: 'Customer / Billed To', value: currentCustomer.value || 'Walk-in Customer' },
            { label: 'Items in Order', value: `${cart.value.length} distinct items (${itemCount} units)` },
            { label: 'Payment Method', value: paymentMethod.value },
            { label: 'Total Amount', value: formatCurrency(cartTotal.value) },
        ],
        confirmText: 'Complete Sale',
        action: completeTransaction,
    });
};

const isSubmitting = ref(false);
const completeTransaction = () => {
    if (cart.value.length === 0) return;

    isSubmitting.value = true;
    router.post(
        `/${teamSlug.value}/sales`,
        {
            customer: currentCustomer.value || 'Walk-in Customer',
            payment_method: paymentMethod.value,
            notes: notes.value,
            items: cart.value.map(item => ({
                product_id: item.product_id,
                qty: item.qty,
            })),
        },
        {
            preserveScroll: true,
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
                            <td class="px-6 py-4 text-gray-600 text-xs">{{ sale.payment_method || 'Cash' }}</td>
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
            <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col md:flex-row h-[85vh] overflow-hidden">
                
                <!-- Left: Catalog -->
                <div class="w-full md:w-3/5 border-r border-gray-200 flex flex-col">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
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
                <div class="w-full md:w-2/5 flex flex-col bg-gray-50">
                    <div class="p-4 border-b border-gray-200 bg-white flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <ShoppingCart class="w-5 h-5 text-gray-700" />
                            <h2 class="text-lg font-bold text-gray-900">Current Order</h2>
                        </div>
                        <button @click="closePosModal" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>

                    <!-- Customer & Payment inputs -->
                    <div class="p-4 border-b border-gray-200 space-y-3 bg-white">
                        <div class="relative">
                            <input
                                id="pos_customer"
                                v-model="currentCustomer"
                                type="text"
                                placeholder=" "
                                class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                            />
                            <label
                                for="pos_customer"
                                class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                :class="currentCustomer ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                            >
                                Customer Name
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <select
                                    id="pos_payment"
                                    v-model="paymentMethod"
                                    class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                                >
                                    <option value="Cash">Cash</option>
                                    <option value="GCash / Maya">GCash / Maya</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Check">Check</option>
                                </select>
                                <label
                                    for="pos_payment"
                                    class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]"
                                >
                                    Payment Method
                                </label>
                            </div>
                            <div class="relative">
                                <input
                                    id="pos_notes"
                                    v-model="notes"
                                    type="text"
                                    placeholder=" "
                                    class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                                />
                                <label
                                    for="pos_notes"
                                    class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                    :class="notes ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                                >
                                    Notes
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Items -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-2">
                        <div v-if="cart.length === 0" class="text-gray-400 text-center mt-12 text-sm">
                            <ShoppingCart class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                            Cart is empty.<br>Click products on the left to add items.
                        </div>
                        <div
                            v-for="(item, idx) in cart"
                            :key="item.product_id"
                            class="flex justify-between items-center bg-white border border-gray-200 p-2.5 rounded-xl shadow-sm"
                        >
                            <div class="flex-1 pr-2">
                                <div class="text-xs font-semibold text-gray-900 line-clamp-1">{{ item.name }}</div>
                                <div class="text-[11px] text-gray-500">{{ formatCurrency(item.price) }} each</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-white">
                                    <button @click="updateQty(idx, item.qty - 1)" class="px-2 py-0.5 text-gray-600 hover:bg-gray-100 text-xs font-bold">-</button>
                                    <span class="px-2 text-xs font-semibold text-gray-900">{{ item.qty }}</span>
                                    <button @click="updateQty(idx, item.qty + 1)" class="px-2 py-0.5 text-gray-600 hover:bg-gray-100 text-xs font-bold">+</button>
                                </div>
                                <span class="text-xs font-bold text-gray-900 w-16 text-right">
                                    {{ formatCurrency(item.price * item.qty) }}
                                </span>
                                <button @click="removeFromCart(idx)" class="text-gray-400 hover:text-red-500 ml-1">
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Total & Checkout -->
                    <div class="p-4 bg-white border-t border-gray-200">
                        <div class="flex justify-between text-gray-500 text-xs mb-1">
                            <span>Subtotal</span>
                            <span>{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-500 text-xs mb-3">
                            <span>Items Count</span>
                            <span>{{ cart.reduce((s, i) => s + i.qty, 0) }} units</span>
                        </div>
                        <div class="flex justify-between items-baseline text-gray-900 text-xl font-black mb-4">
                            <span>Total</span>
                            <span class="text-[#F8B803] text-2xl">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        
                        <SpecularButton
                            variant="brand"
                            size="lg"
                            :radius="10"
                            class="w-full"
                            :disabled="cart.length === 0 || isSubmitting"
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
                    <button @click="invoiceModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs text-gray-600 mb-6 bg-gray-50 p-4 rounded-xl">
                    <div>
                        <div class="font-bold text-gray-900 mb-1">Billed To:</div>
                        <div class="text-sm font-semibold text-gray-800">{{ selectedInvoice.client_name }}</div>
                        <div>Payment Method: {{ selectedInvoice.payment_method || 'Cash' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-gray-900 mb-1">Invoice Details:</div>
                        <div>Date: {{ new Date(selectedInvoice.sale_date).toLocaleString() }}</div>
                        <div>Status: <span class="font-bold text-emerald-600">{{ selectedInvoice.status }}</span></div>
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
