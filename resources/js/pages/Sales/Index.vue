<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';

const sales = ref([
    { id: 'INV-2023-001', date: '2023-10-24 14:30', customer: 'Walk-in Customer', items: 3, total: 4500, status: 'Completed' },
    { id: 'INV-2023-002', date: '2023-10-24 15:15', customer: 'Alpha Builders Inc.', items: 12, total: 24500, status: 'Completed' },
    { id: 'INV-2023-003', date: '2023-10-25 09:10', customer: 'Walk-in Customer', items: 1, total: 850, status: 'Completed' },
    { id: 'INV-2023-004', date: '2023-10-25 11:20', customer: 'Tech Solutions', items: 5, total: 15350, status: 'Pending Payment' },
    { id: 'INV-2023-005', date: '2023-10-26 10:45', customer: 'Omega Constructions', items: 45, total: 125000, status: 'Completed' },
    { id: 'INV-2023-006', date: '2023-10-26 13:20', customer: 'Walk-in Customer', items: 2, total: 1200, status: 'Completed' },
    { id: 'INV-2023-007', date: '2023-10-27 08:30', customer: 'City Electrics', items: 8, total: 34200, status: 'Pending Payment' },
    { id: 'INV-2023-008', date: '2023-10-27 16:15', customer: 'Walk-in Customer', items: 1, total: 450, status: 'Completed' },
]);

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};

// POS Modal State
const posModalOpen = ref(false);
const currentTransaction = ref({
    customer: 'Walk-in Customer',
    items: [] as any[],
});
const productSearch = ref('');
const availableProducts = [
    { id: 1, name: 'Industrial Circuit Breaker', price: 120 },
    { id: 2, name: 'Heavy Duty Copper Wire (100m)', price: 85 },
    { id: 3, name: 'LED Floodlight 200W', price: 45 },
];

const openPosModal = () => {
    currentTransaction.value = { customer: 'Walk-in Customer', items: [] };
    posModalOpen.value = true;
};

const closePosModal = () => {
    posModalOpen.value = false;
};

const addItemToCart = (product: any) => {
    const existing = currentTransaction.value.items.find((i: any) => i.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        currentTransaction.value.items.push({ ...product, qty: 1 });
    }
};

const removeItemFromCart = (index: number) => {
    currentTransaction.value.items.splice(index, 1);
};

const cartTotal = computed(() => {
    return currentTransaction.value.items.reduce((sum: number, item: any) => sum + (item.price * item.qty), 0);
});

const completeTransaction = () => {
    if (currentTransaction.value.items.length === 0) {
        alert('Cart is empty!');
        return;
    }
    alert('Transaction completed and recorded! (Prototype)');
    closePosModal();
};

const printInvoice = (id: string) => {
    alert(`Opening invoice print preview for ${id}... (Prototype)`);
};
</script>

<template>
    <Head title="Sales Management" />

    <div class="workspace-page">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Sales Management</h1>
                <p class="text-gray-500 mt-1">Record transactions and manage customer sales.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="10"
                @click="openPosModal"
                class="w-full md:w-auto"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span>New Transaction (POS)</span>
            </SpecularButton>
        </div>

        <!-- Sales History Table -->
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden flex-1 flex flex-col shadow-sm">
            <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Recent Sales History</h3>
                <div class="flex gap-2">
                    <input type="text" placeholder="Search invoices..." class="bg-white border border-gray-200 rounded p-1.5 text-sm text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                </div>
            </div>
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-4 font-medium">Invoice ID</th>
                            <th class="px-6 py-4 font-medium">Date & Time</th>
                            <th class="px-6 py-4 font-medium">Customer</th>
                            <th class="px-6 py-4 font-medium">Items</th>
                            <th class="px-6 py-4 font-medium">Total Amount</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="sale in sales" :key="sale.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-mono text-[#F8B803]">{{ sale.id }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ sale.date }}</td>
                            <td class="px-6 py-4 font-medium text-gray-900">{{ sale.customer }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ sale.items }} items</td>
                            <td class="px-6 py-4 font-bold text-gray-900">{{ formatCurrency(sale.total) }}</td>
                            <td class="px-6 py-4">
                                <span :class="{
                                    'px-2 py-1 rounded text-xs font-medium': true,
                                    'bg-green-500/20 text-green-500': sale.status === 'Completed',
                                    'bg-[#4b5320]/20 text-[#4b5320]': sale.status === 'Pending Payment'
                                }">
                                    {{ sale.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <SpecularButton
                                    variant="secondary"
                                    size="sm"
                                    :radius="8"
                                    @click="printInvoice(sale.id)"
                                >
                                    View / Print Invoice
                                </SpecularButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- POS Modal (Simulated) -->
        <div v-if="posModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xl w-full max-w-5xl flex h-[80vh] overflow-hidden">
                
                <!-- Left: Product Selection -->
                <div class="w-2/3 border-r border-gray-200 flex flex-col">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Products</h2>
                        <input v-model="productSearch" type="text" placeholder="Search product by name or SKU..." class="w-full bg-white border border-gray-200 rounded p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                    </div>
                    <div class="p-4 flex-1 overflow-y-auto grid grid-cols-3 gap-4 auto-rows-max">
                        <div v-for="product in availableProducts" :key="product.id" @click="addItemToCart(product)" class="bg-white border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-[#F8B803] shadow-sm transition-colors flex flex-col justify-between h-32">
                            <span class="text-gray-900 font-medium text-sm leading-tight">{{ product.name }}</span>
                            <span class="text-[#F8B803] font-bold mt-2">{{ formatCurrency(product.price) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Cart & Checkout -->
                <div class="w-1/3 flex flex-col bg-gray-50">
                    <div class="p-4 border-b border-gray-200 bg-white flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">Current Sale</h2>
                        <button @click="closePosModal" class="text-gray-500 hover:text-gray-900">&times;</button>
                    </div>
                    
                    <div class="p-4 border-b border-gray-200">
                        <label class="block text-xs text-gray-500 mb-1">Customer Name</label>
                        <input v-model="currentTransaction.customer" type="text" class="w-full bg-white border border-gray-200 rounded p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                    </div>

                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="currentTransaction.items.length === 0" class="text-gray-500 text-center mt-10 text-sm">
                            Cart is empty. Select products to add.
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="(item, index) in currentTransaction.items" :key="index" class="flex justify-between items-center bg-white border border-gray-200 p-2 rounded shadow-sm">
                                <div class="flex-1">
                                    <div class="text-sm text-gray-900 truncate max-w-[150px]">{{ item.name }}</div>
                                    <div class="text-xs text-gray-500">{{ formatCurrency(item.price) }} x {{ item.qty }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900">{{ formatCurrency(item.price * item.qty) }}</div>
                                    <button @click="removeItemFromCart(index)" class="text-red-500 text-xs hover:underline mt-1">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-white border-t border-gray-200">
                        <div class="flex justify-between text-gray-500 mb-2">
                            <span>Subtotal</span>
                            <span>{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-500 mb-4">
                            <span>Tax (0%)</span>
                            <span>₱0.00</span>
                        </div>
                        <div class="flex justify-between text-gray-900 text-2xl font-black mb-6">
                            <span>Total</span>
                            <span class="text-[#F8B803]">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        
                        <SpecularButton
                            variant="brand"
                            size="lg"
                            :radius="10"
                            class="w-full"
                            @click="completeTransaction"
                        >
                            Complete Transaction
                        </SpecularButton>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
