<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const requests = ref([
    { id: 'PR-10045', item: 'Heavy Duty Copper Wire (100m)', supplier: 'Global Wire Systems', qty: 50, date: 'Oct 25, 2026', status: 'Requested' },
    { id: 'PR-10044', item: 'LED Floodlight 200W', supplier: 'Lumen Lighting Co.', qty: 200, date: 'Oct 22, 2026', status: 'Ordered' },
    { id: 'PR-10043', item: 'Magnetic Contactor 220V', supplier: 'ElectroTech Industries', qty: 150, date: 'Oct 18, 2026', status: 'Delivered' },
    { id: 'PR-10042', item: 'Industrial Circuit Breaker', supplier: 'ElectroTech Industries', qty: 20, date: 'Oct 10, 2026', status: 'Cancelled' },
]);

const getStatusClass = (status: string) => {
    if (status === 'Requested') return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
    if (status === 'Ordered') return 'bg-[#F8B803]/10 text-[#F8B803] border-[#F8B803]/20';
    if (status === 'Delivered') return 'bg-green-500/10 text-green-500 border-green-500/20';
    return 'bg-red-500/10 text-red-500 border-red-500/20';
};

const updateStatus = (req: any) => {
    const statuses = ['Requested', 'Ordered', 'Delivered', 'Cancelled'];
    const currentIndex = statuses.indexOf(req.status);
    req.status = statuses[(currentIndex + 1) % statuses.length];
};

const showCreateModal = ref(false);

const saveRequest = () => {
    alert('Purchase request submitted! (Prototype)');
    showCreateModal.value = false;
};
</script>

<template>
    <Head title="Procurement Monitoring" />

    <div class="workspace-page">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Procurement Monitoring</h1>
                <p class="text-gray-500 mt-1">Track purchase requests and delivery statuses.</p>
            </div>
            <button @click="showCreateModal = true" class="w-full md:w-auto px-5 py-2.5 bg-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all duration-300">
                + New Purchase Request
            </button>
        </div>

        <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 text-sm">
                            <th class="pb-3 font-medium">Request ID</th>
                            <th class="pb-3 font-medium">Item Name</th>
                            <th class="pb-3 font-medium">Supplier</th>
                            <th class="pb-3 font-medium text-center">Qty</th>
                            <th class="pb-3 font-medium">Date Requested</th>
                            <th class="pb-3 font-medium text-center">Status</th>
                            <th class="pb-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr v-for="req in requests" :key="req.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                            <td class="py-4 text-gray-900 font-medium">{{ req.id }}</td>
                            <td class="py-4 text-gray-600">{{ req.item }}</td>
                            <td class="py-4 text-gray-500">{{ req.supplier }}</td>
                            <td class="py-4 text-center text-gray-900 font-bold">{{ req.qty }}</td>
                            <td class="py-4 text-gray-500">{{ req.date }}</td>
                            <td class="py-4 text-center">
                                <span :class="getStatusClass(req.status)" class="px-2.5 py-1 rounded-full text-xs font-medium border">
                                    {{ req.status }}
                                </span>
                            </td>
                            <td class="py-4 text-right">
                                <button @click="updateStatus(req)" class="px-3 py-1 bg-white border border-gray-200 hover:border-[#F8B803] text-gray-600 hover:text-gray-900 rounded text-xs transition-colors shadow-sm">
                                    Update Status
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
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-4">New Purchase Request</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Item to Order</label>
                    <select class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm">
                        <option>Heavy Duty Copper Wire (100m)</option>
                        <option>LED Floodlight 200W</option>
                        <option>Magnetic Contactor 220V</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Supplier</label>
                    <select class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm">
                        <option>Global Wire Systems</option>
                        <option>Lumen Lighting Co.</option>
                        <option>ElectroTech Industries</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Quantity</label>
                    <input type="number" min="1" value="10" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" />
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8">
                <button @click="showCreateModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-900">Cancel</button>
                <button @click="saveRequest" class="px-4 py-2 bg-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all">Submit Request</button>
            </div>
        </div>
    </div>
</template>
