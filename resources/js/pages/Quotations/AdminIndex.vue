<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const quotations = ref([
    { id: 'QT-2023-001', client: 'Alpha Builders Inc.', date: '2023-10-24', total: 45000, status: 'Approved' },
    { id: 'QT-2023-002', client: 'Tech Solutions LLC', date: '2023-10-25', total: 12500, status: 'Pending' },
    { id: 'QT-2023-003', client: 'Omega Constructions', date: '2023-10-26', total: 89000, status: 'Completed' },
    { id: 'QT-2023-004', client: 'City Electrics', date: '2023-10-27', total: 34200, status: 'Pending' },
    { id: 'QT-2023-005', client: 'Nova Developers', date: '2023-10-28', total: 67500, status: 'Approved' },
    { id: 'QT-2023-006', client: 'Prime Infrastructure', date: '2023-10-29', total: 112000, status: 'Completed' },
]);

const showCreateModal = ref(false);

const newQuotation = ref({
    client: '',
    contactPerson: '',
    email: '',
    validUntil: '',
    notes: '',
    items: [
        { desc: '', qty: 1, price: 0 }
    ]
});

const addItem = () => {
    newQuotation.value.items.push({ desc: '', qty: 1, price: 0 });
};

const removeItem = (index: number) => {
    newQuotation.value.items.splice(index, 1);
};

const calculateTotal = computed(() => {
    return newQuotation.value.items.reduce((sum, item) => sum + (item.qty * item.price), 0);
});

const saveQuotation = () => {
    if (!newQuotation.value.client) {
        alert('Please enter a client name.');
        return;
    }
    alert('Quotation saved successfully! (Prototype)');
    showCreateModal.value = false;
    newQuotation.value = { 
        client: '', 
        contactPerson: '',
        email: '',
        validUntil: '',
        notes: '',
        items: [{ desc: '', qty: 1, price: 0 }] 
    };
};

const updateStatus = (quote: any) => {
    const statuses = ['Pending', 'Approved', 'Completed'];
    const currentIndex = statuses.indexOf(quote.status);
    quote.status = statuses[(currentIndex + 1) % statuses.length];
};

const printQuotation = (id: string) => {
    alert(`Opening print preview for ${id}... (Prototype)`);
};

const getStatusClass = (status: string) => {
    if (status === 'Approved') return 'bg-green-500/10 text-green-500 border-green-500/20';
    if (status === 'Pending') return 'bg-[#F8B803]/10 text-[#F8B803] border-[#F8B803]/20';
    if (status === 'Completed') return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
    return 'bg-gray-500/10 text-gray-400 border-gray-500/20';
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};
</script>

<template>
    <Head title="Quotation Management" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Quotation Management</h1>
                <p class="text-gray-500 mt-1">Create and manage client quotations.</p>
            </div>
            <button @click="showCreateModal = true" class="w-full md:w-auto px-5 py-2.5 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all duration-300">
                + Create Quotation
            </button>
        </div>

        <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 text-sm">
                            <th class="pb-3 font-medium">Quotation ID</th>
                            <th class="pb-3 font-medium">Client</th>
                            <th class="pb-3 font-medium">Date</th>
                            <th class="pb-3 font-medium text-right">Total Amount</th>
                            <th class="pb-3 font-medium text-center">Status</th>
                            <th class="pb-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr v-for="quote in quotations" :key="quote.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                            <td class="py-4 text-gray-900 font-medium">{{ quote.id }}</td>
                            <td class="py-4 text-gray-600">{{ quote.client }}</td>
                            <td class="py-4 text-gray-500">{{ quote.date }}</td>
                            <td class="py-4 text-right text-gray-900 font-bold">{{ formatCurrency(quote.total) }}</td>
                            <td class="py-4 text-center">
                                <span :class="getStatusClass(quote.status)" class="px-2.5 py-1 rounded-full text-xs font-medium border">
                                    {{ quote.status }}
                                </span>
                            </td>
                            <td class="py-4 text-right flex justify-end gap-2">
                                <button @click="updateStatus(quote)" class="px-3 py-1 bg-white border border-gray-200 hover:border-[#F8B803] text-gray-600 hover:text-gray-900 rounded text-xs transition-colors shadow-sm">
                                    Update Status
                                </button>
                                <button @click="printQuotation(quote.id)" class="px-3 py-1 bg-gray-100 border border-gray-200 hover:bg-gray-200 text-gray-700 rounded text-xs transition-colors">
                                    Print / Preview
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Quotation Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Create New Quotation</h3>
                <button @click="showCreateModal = false" class="text-gray-500 hover:text-gray-900 text-2xl">&times;</button>
            </div>
            
            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-gray-700 text-sm mb-1">Client / Company Name *</label>
                        <input v-model="newQuotation.client" type="text" placeholder="Enter client name..." class="w-full bg-white border border-gray-200 rounded-lg p-3 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm mb-1">Contact Person</label>
                        <input v-model="newQuotation.contactPerson" type="text" placeholder="E.g. John Doe" class="w-full bg-white border border-gray-200 rounded-lg p-3 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm mb-1">Email Address</label>
                        <input v-model="newQuotation.email" type="email" placeholder="client@example.com" class="w-full bg-white border border-gray-200 rounded-lg p-3 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm mb-1">Valid Until</label>
                        <input v-model="newQuotation.validUntil" type="date" class="w-full bg-white border border-gray-200 rounded-lg p-3 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-gray-700 text-sm mb-1">Additional Notes</label>
                        <textarea v-model="newQuotation.notes" placeholder="Any special terms or conditions..." rows="2" class="w-full bg-white border border-gray-200 rounded-lg p-3 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm"></textarea>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-gray-700 text-sm">Line Items</label>
                        <button @click="addItem" class="text-xs text-[#F8B803] hover:underline">+ Add Item</button>
                    </div>
                    
                    <div class="space-y-3">
                        <div v-for="(item, index) in newQuotation.items" :key="index" class="flex gap-3 items-center">
                            <input v-model="item.desc" type="text" placeholder="Description" class="flex-1 bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm shadow-sm" />
                            <input v-model.number="item.qty" type="number" min="1" placeholder="Qty" class="w-20 bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm shadow-sm" />
                            <input v-model.number="item.price" type="number" min="0" placeholder="Price" class="w-32 bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none text-sm shadow-sm" />
                            <div class="w-24 text-right text-gray-900 font-mono text-sm">
                                {{ formatCurrency(item.qty * item.price) }}
                            </div>
                            <button @click="removeItem(index)" class="text-red-500 hover:text-red-700 w-6 text-center" :disabled="newQuotation.items.length === 1">&times;</button>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-4 flex justify-end">
                    <div class="text-right">
                        <p class="text-gray-500 text-sm">Total Amount</p>
                        <h2 class="text-3xl font-bold text-[#F8B803]">{{ formatCurrency(calculateTotal) }}</h2>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-8">
                <button @click="showCreateModal = false" class="px-5 py-2.5 text-gray-500 hover:text-gray-900">Cancel</button>
                <button @click="saveQuotation" class="px-5 py-2.5 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all">Save Quotation</button>
            </div>
        </div>
    </div>
</template>
