<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import { Banknote, ShoppingBag, Plus, Trash2, Printer, Search, CheckCircle2 } from '@lucide/vue';

interface SaleItem {
    id: number;
    sku: string;
    name: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
}

interface SaleRecord {
    id: string; // Invoice number
    db_id: number;
    date: string;
    customer: string;
    contact_person?: string;
    items_count: number;
    items: SaleItem[];
    subtotal: number;
    discount: number;
    tax_amount: number;
    total: number;
    status: string;
    payment_method: string;
    processed_by: string;
}

interface ProductItem {
    id: number;
    sku: string;
    name: string;
    price: number;
    stock: number;
}

const props = defineProps<{
    sales: SaleRecord[];
    products: ProductItem[];
    filters?: {
        search?: string;
    };
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || '');

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(amount || 0);
};

// POS Terminal State
const posModalOpen = ref(false);
const productSearch = ref('');
const invoiceModalOpen = ref(false);
const activeInvoice = ref<SaleRecord | null>(null);

const posForm = useForm({
    customer: 'Walk-in Customer',
    payment_method: 'Cash',
    items: [] as Array<{ id: number; name: string; sku: string; price: number; qty: number }>,
});

const openPosModal = () => {
    posForm.reset();
    posForm.items = [];
    posForm.customer = 'Walk-in Customer';
    posForm.payment_method = 'Cash';
    posModalOpen.value = true;
};

const closePosModal = () => {
    posModalOpen.value = false;
};

const filteredInventory = computed(() => {
    if (!productSearch.value.trim()) return props.products || [];
    return (props.products || []).filter(p => 
        p.name.toLowerCase().includes(productSearch.value.toLowerCase()) ||
        p.sku.toLowerCase().includes(productSearch.value.toLowerCase())
    );
});

const addItemToCart = (product: ProductItem) => {
    const existing = posForm.items.find(i => i.id === product.id);
    if (existing) {
        if (existing.qty < product.stock) {
            existing.qty++;
        }
    } else {
        posForm.items.push({
            id: product.id,
            sku: product.sku,
            name: product.name,
            price: product.price,
            qty: 1,
        });
    }
};

const removeItemFromCart = (index: number) => {
    posForm.items.splice(index, 1);
};

const cartTotal = computed(() => {
    return posForm.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
});

const submitTransaction = () => {
    if (posForm.items.length === 0) return;
    posForm.post(`/${teamSlug.value}/sales`, {
        preserveScroll: true,
        onSuccess: () => {
            posModalOpen.value = false;
            posForm.reset();
        },
    });
};

const openInvoiceModal = (sale: SaleRecord) => {
    activeInvoice.value = sale;
    invoiceModalOpen.value = true;
};

const printActiveInvoice = () => {
    window.print();
};
</script>

<template>
    <Head title="Sales & POS Monitoring" />

    <div class="workspace-page p-6 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                    <Banknote class="w-8 h-8 text-[#4b5320]" />
                    <span>Sales & Orders</span>
                </h1>
                <p class="text-gray-500 mt-1">Direct point-of-sale transactions, converted quotation sales, and invoicing.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="10"
                class="flex items-center gap-2"
                @click="openPosModal"
            >
                <ShoppingBag class="w-4 h-4" />
                <span>+ New Direct POS Sale</span>
            </SpecularButton>
        </div>

        <!-- Sales Records Table -->
        <div class="rounded-2xl bg-white border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/70 text-gray-600 text-xs font-semibold uppercase tracking-wider">
                            <th class="py-3.5 px-6">Invoice #</th>
                            <th class="py-3.5 px-6">Date</th>
                            <th class="py-3.5 px-6">Customer / Client</th>
                            <th class="py-3.5 px-6 text-center">Items</th>
                            <th class="py-3.5 px-6">Payment Method</th>
                            <th class="py-3.5 px-6 text-right">Total Amount</th>
                            <th class="py-3.5 px-6 text-center">Status</th>
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <tr v-if="sales.length === 0">
                            <td colspan="8" class="text-center py-10 text-gray-500">
                                No sales transactions recorded yet. Click "+ New Direct POS Sale" to start.
                            </td>
                        </tr>
                        <tr 
                            v-for="sale in sales" 
                            :key="sale.id" 
                            class="hover:bg-gray-50/80 transition-colors"
                        >
                            <td class="py-4 px-6 text-gray-900 font-bold text-xs">{{ sale.id }}</td>
                            <td class="py-4 px-6 text-gray-500 text-xs">{{ sale.date }}</td>
                            <td class="py-4 px-6 text-gray-900 font-medium">{{ sale.customer }}</td>
                            <td class="py-4 px-6 text-center text-gray-600">{{ sale.items_count }}</td>
                            <td class="py-4 px-6 text-gray-600 text-xs">{{ sale.payment_method }}</td>
                            <td class="py-4 px-6 text-right font-bold text-gray-900">{{ formatCurrency(sale.total) }}</td>
                            <td class="py-4 px-6 text-center">
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                    {{ sale.status }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <button 
                                    @click="openInvoiceModal(sale)" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition-colors"
                                >
                                    <Printer class="w-3.5 h-3.5" />
                                    <span>Receipt</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- POS Direct Sale Modal -->
    <div v-if="posModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white border border-gray-200 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden my-6">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/60">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <ShoppingBag class="w-5 h-5 text-[#4b5320]" />
                        <span>Direct POS Terminal Sale</span>
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Select in-stock items to complete sale and automatically deduct inventory.</p>
                </div>
                <button @click="closePosModal" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
            </div>

            <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200 max-h-[70vh] overflow-y-auto">
                <!-- Left: Product Catalog -->
                <div class="p-6 space-y-4">
                    <div class="relative">
                        <Search class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                        <input 
                            v-model="productSearch" 
                            type="text" 
                            placeholder="Search catalog by name or SKU..." 
                            class="w-full bg-white border border-gray-200 rounded-xl pl-9 pr-3 py-2 text-sm text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm"
                        />
                    </div>

                    <div class="space-y-2 max-h-[380px] overflow-y-auto pr-1">
                        <div 
                            v-for="prod in filteredInventory" 
                            :key="prod.id" 
                            class="p-3 border border-gray-200 rounded-xl flex items-center justify-between hover:border-[#F8B803] hover:bg-amber-50/20 transition-all cursor-pointer group"
                            @click="addItemToCart(prod)"
                        >
                            <div>
                                <div class="font-semibold text-gray-900 text-sm group-hover:text-[#4b5320]">{{ prod.name }}</div>
                                <div class="text-xs text-gray-500">SKU: {{ prod.sku }} • Stock: {{ prod.stock }} units</div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-gray-900 text-sm">{{ formatCurrency(prod.price) }}</div>
                                <button class="text-xs text-[#4b5320] font-semibold flex items-center gap-0.5 mt-0.5">
                                    <Plus class="w-3 h-3" /> Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Active Cart -->
                <div class="p-6 flex flex-col justify-between space-y-4 bg-gray-50/30">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600 block mb-1 uppercase tracking-wider">Customer Name</label>
                                <input 
                                    v-model="posForm.customer" 
                                    type="text" 
                                    required 
                                    class="w-full bg-white border border-gray-200 rounded-lg p-2 text-sm text-gray-900"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600 block mb-1 uppercase tracking-wider">Payment Method</label>
                                <select 
                                    v-model="posForm.payment_method" 
                                    class="w-full bg-white border border-gray-200 rounded-lg p-2 text-sm text-gray-900"
                                >
                                    <option value="Cash">Cash</option>
                                    <option value="GCash / Maya">GCash / Maya</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit / Debit Card">Credit / Debit Card</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Cart Items ({{ posForm.items.length }})</div>
                            <div v-if="posForm.items.length === 0" class="text-center py-12 text-gray-400 text-xs border-2 border-dashed border-gray-200 rounded-xl">
                                Cart is empty. Click items on the left to add.
                            </div>
                            <div v-else class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                                <div 
                                    v-for="(item, idx) in posForm.items" 
                                    :key="item.id" 
                                    class="flex items-center justify-between p-2.5 bg-white border border-gray-200 rounded-lg text-sm"
                                >
                                    <div class="flex-1 min-w-0 pr-2">
                                        <div class="font-medium text-gray-900 truncate text-xs">{{ item.name }}</div>
                                        <div class="text-[11px] text-gray-500">{{ formatCurrency(item.price) }} each</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input 
                                            v-model.number="item.qty" 
                                            type="number" 
                                            min="1" 
                                            class="w-14 bg-gray-50 border border-gray-200 rounded p-1 text-center text-xs font-bold"
                                        />
                                        <div class="text-xs font-bold text-gray-900 w-16 text-right">
                                            {{ formatCurrency(item.price * item.qty) }}
                                        </div>
                                        <button @click="removeItemFromCart(idx)" class="text-red-400 hover:text-red-600 p-1">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Summary & Submit -->
                    <div class="border-t border-gray-200 pt-4 space-y-3">
                        <div class="flex justify-between items-center text-base font-bold text-gray-900">
                            <span>Grand Total:</span>
                            <span class="text-xl text-[#4b5320]">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <SpecularButton
                            variant="brand"
                            size="lg"
                            :radius="12"
                            class="w-full flex items-center justify-center gap-2 font-bold"
                            :disabled="posForm.processing || posForm.items.length === 0"
                            @click="submitTransaction"
                        >
                            <CheckCircle2 class="w-5 h-5" />
                            <span>{{ posForm.processing ? 'Processing...' : 'Complete & Record Sale' }}</span>
                        </SpecularButton>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Invoice / Receipt Modal -->
    <div v-if="invoiceModalOpen && activeInvoice" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white border border-gray-200 rounded-2xl w-full max-w-lg shadow-2xl p-8 my-6">
            <div class="border-b-2 border-[#4b5320] pb-4 mb-6 flex justify-between items-start">
                <div>
                    <h2 class="text-xl font-bold text-[#4b5320]">MR-PRIDE ELECTRICAL SUPPLIES</h2>
                    <p class="text-xs text-gray-500">Official Sales Invoice / Transaction Record</p>
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold bg-[#F8B803]/20 text-[#946c00] px-2 py-0.5 rounded">PAID</span>
                    <div class="text-xs font-semibold text-gray-800 mt-1">{{ activeInvoice.id }}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs mb-6 text-gray-600">
                <div>
                    <div class="font-bold text-gray-900">Customer:</div>
                    <div>{{ activeInvoice.customer }}</div>
                    <div v-if="activeInvoice.contact_person">Attn: {{ activeInvoice.contact_person }}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-gray-900">Date:</div>
                    <div>{{ activeInvoice.date }}</div>
                    <div>Payment: {{ activeInvoice.payment_method }}</div>
                </div>
            </div>

            <div class="border-t border-b border-gray-200 py-3 mb-4">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-500 border-b border-gray-100">
                            <th class="text-left pb-2">Item</th>
                            <th class="text-center pb-2">Qty</th>
                            <th class="text-right pb-2">Price</th>
                            <th class="text-right pb-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="item in activeInvoice.items" :key="item.id">
                            <td class="py-2 text-gray-900 font-medium">{{ item.name }}</td>
                            <td class="py-2 text-center text-gray-600">{{ item.quantity }}</td>
                            <td class="py-2 text-right text-gray-600">{{ formatCurrency(item.unit_price) }}</td>
                            <td class="py-2 text-right text-gray-900 font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="space-y-1 text-xs text-right mb-6">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal:</span>
                    <span>{{ formatCurrency(activeInvoice.subtotal) }}</span>
                </div>
                <div class="flex justify-between font-bold text-base text-gray-900 pt-2 border-t border-gray-200">
                    <span>Grand Total:</span>
                    <span class="text-[#4b5320]">{{ formatCurrency(activeInvoice.total) }}</span>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
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
                    class="flex items-center gap-1.5"
                    @click="printActiveInvoice"
                >
                    <Printer class="w-4 h-4" />
                    <span>Print Invoice</span>
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
