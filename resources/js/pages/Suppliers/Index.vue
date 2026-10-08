<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';

const suppliers = ref([
    { id: 1, name: 'ElectroTech Industries', contactPerson: 'Michael Chang', phone: '+1 (555) 123-4567', email: 'sales@electrotech.com', status: 'Active', ahpScore: 0.842, metrics: { price: 9, quality: 8, delivery: 9, service: 7 } },
    { id: 2, name: 'Global Wire Systems', contactPerson: 'Sarah Jenkins', phone: '+1 (555) 987-6543', email: 'orders@globalwire.com', status: 'Active', ahpScore: 0.615, metrics: { price: 6, quality: 7, delivery: 5, service: 8 } },
    { id: 3, name: 'Lumen Lighting Co.', contactPerson: 'David Ross', phone: '+1 (555) 456-7890', email: 'support@lumenlight.com', status: 'Inactive', ahpScore: 0.420, metrics: { price: 5, quality: 6, delivery: 4, service: 4 } },
]);

const showAhpModal = ref(false);

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedSupplier = ref<any>(null);

const openAddModal = () => showAddModal.value = true;
const openEditModal = (supplier: any) => { selectedSupplier.value = { ...supplier }; showEditModal.value = true; };
const openDeleteModal = (supplier: any) => { selectedSupplier.value = supplier; showDeleteModal.value = true; };

const saveSupplier = () => { alert('Supplier saved (Prototype)'); showAddModal.value = false; showEditModal.value = false; };
const deleteSupplier = () => { alert('Supplier deleted (Prototype)'); showDeleteModal.value = false; };
</script>

<template>
    <Head title="Supplier Management" />

    <div class="workspace-page">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Supplier Management</h1>
                <p class="text-gray-500 mt-1">Manage vendor contact information and details.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showAhpModal = true"
                >
                    Run AHP Evaluation
                </SpecularButton>
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="openAddModal"
                >
                    + Add Supplier
                </SpecularButton>
            </div>
        </div>

        <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 text-sm">
                            <th class="pb-3 font-medium">Company Name</th>
                            <th class="pb-3 font-medium">Contact Person</th>
                            <th class="pb-3 font-medium">Phone</th>
                            <th class="pb-3 font-medium">AHP Score</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr v-for="supplier in suppliers" :key="supplier.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                            <td class="py-4 text-gray-900 font-medium">{{ supplier.name }}</td>
                            <td class="py-4 text-gray-600">{{ supplier.contactPerson }}</td>
                            <td class="py-4 text-gray-600">{{ supplier.phone }}</td>
                            <td class="py-4">
                                <span class="text-[#F8B803] font-bold">{{ supplier.ahpScore }}</span>
                            </td>
                            <td class="py-4">
                                <span :class="supplier.status === 'Active' ? 'text-green-600 bg-green-50 border-green-200' : 'text-gray-500 bg-gray-50 border-gray-200'" class="px-2.5 py-1 rounded-full text-xs font-medium border">
                                    {{ supplier.status }}
                                </span>
                            </td>
                            <td class="py-4 text-right">
                                <button @click="showAhpModal = true" class="text-blue-600 hover:text-blue-800 mr-3 text-xs">AHP Details</button>
                                <button @click="openEditModal(supplier)" class="text-gray-500 hover:text-gray-900 mr-3">Edit</button>
                                <button @click="openDeleteModal(supplier)" class="text-red-500 hover:text-red-700">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-[500px] shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-4">{{ showEditModal ? 'Edit Supplier' : 'Add Supplier' }}</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Company Name</label>
                    <input type="text" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" :value="selectedSupplier?.name || ''" />
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Contact Person</label>
                    <input type="text" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" :value="selectedSupplier?.contactPerson || ''" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm mb-1">Phone</label>
                        <input type="text" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" :value="selectedSupplier?.phone || ''" />
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm mb-1">Email</label>
                        <input type="email" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" :value="selectedSupplier?.email || ''" />
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showAddModal = false; showEditModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="saveSupplier"
                >
                    Save
                </SpecularButton>
            </div>
        </div>
    </div>

    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-[400px] shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Delete</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete {{ selectedSupplier?.name }}? This action cannot be undone.</p>
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
                    @click="deleteSupplier"
                >
                    Delete
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- AHP Evaluation Modal -->
    <div v-if="showAhpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 tracking-tight">Analytic Hierarchy Process (AHP)</h3>
                    <p class="text-gray-500">Supplier Evaluation Matrix & Pairwise Comparisons</p>
                </div>
                <button @click="showAhpModal = false" class="text-gray-500 hover:text-gray-900">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h4 class="text-gray-900 font-bold mb-3 border-b border-gray-200 pb-2">Criteria Weights (Normalized)</h4>
                    <ul class="space-y-2 text-sm">
                        <li class="flex justify-between"><span class="text-gray-600">Price (Cost)</span> <span class="text-[#F8B803] font-medium">0.450</span></li>
                        <li class="flex justify-between"><span class="text-gray-600">Quality</span> <span class="text-[#F8B803] font-medium">0.300</span></li>
                        <li class="flex justify-between"><span class="text-gray-600">Delivery Reliability</span> <span class="text-[#F8B803] font-medium">0.150</span></li>
                        <li class="flex justify-between"><span class="text-gray-600">Service / Support</span> <span class="text-[#F8B803] font-medium">0.100</span></li>
                    </ul>
                    <div class="mt-4 pt-3 border-t border-gray-200">
                        <p class="text-xs text-green-600">Consistency Ratio (CR) = 0.04 &lt; 0.10 (Acceptable)</p>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h4 class="text-gray-900 font-bold mb-3 border-b border-gray-200 pb-2">Final Supplier Rankings</h4>
                    <ul class="space-y-3">
                        <li v-for="(sup, index) in suppliers.sort((a,b) => b.ahpScore - a.ahpScore)" :key="sup.id" class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-700">{{ index + 1 }}</span>
                            <span class="text-gray-900 flex-1">{{ sup.name }}</span>
                            <span class="text-[#F8B803] font-bold">{{ sup.ahpScore }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                <h4 class="text-gray-900 font-bold mb-4">Detailed Performance Matrix</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="pb-2">Supplier</th>
                                <th class="pb-2 text-center">Price (45%)</th>
                                <th class="pb-2 text-center">Quality (30%)</th>
                                <th class="pb-2 text-center">Delivery (15%)</th>
                                <th class="pb-2 text-center">Service (10%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="sup in suppliers" :key="sup.id">
                                <td class="py-3 text-gray-900 font-medium">{{ sup.name }}</td>
                                <td class="py-3 text-center">{{ sup.metrics.price }}/10</td>
                                <td class="py-3 text-center">{{ sup.metrics.quality }}/10</td>
                                <td class="py-3 text-center">{{ sup.metrics.delivery }}/10</td>
                                <td class="py-3 text-center">{{ sup.metrics.service }}/10</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="showAhpModal = false"
                >
                    Close Evaluation
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
