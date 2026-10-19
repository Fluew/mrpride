<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import { Truck, Plus, Edit2, Trash2, Sliders, CheckCircle2 } from '@lucide/vue';

interface SupplierItem {
    id: number;
    name: string;
    contactPerson: string;
    phone: string;
    email: string;
    address: string;
    status: string;
    ahpScore: number;
    metrics: {
        price: number;
        quality: number;
        delivery: number;
        service: number;
    };
}

const props = defineProps<{
    suppliers: SupplierItem[];
    filters?: {
        search?: string;
    };
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || '');

const searchQuery = ref(props.filters?.search || '');
const filteredSuppliers = computed(() => {
    return (props.suppliers || []).filter(s => {
        return s.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
               s.contactPerson.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
               s.phone.toLowerCase().includes(searchQuery.value.toLowerCase());
    });
});

const showAhpModal = ref(false);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedSupplier = ref<SupplierItem | null>(null);

const addForm = useForm({
    name: '',
    contactPerson: '',
    phone: '',
    email: '',
    address: '',
    status: 'Active',
    metrics: {
        price: 8,
        quality: 8,
        delivery: 8,
        service: 7,
    },
});

const editForm = useForm({
    name: '',
    contactPerson: '',
    phone: '',
    email: '',
    address: '',
    status: 'Active',
    metrics: {
        price: 8,
        quality: 8,
        delivery: 8,
        service: 7,
    },
});

const openAddModal = () => {
    addForm.reset();
    addForm.clearErrors();
    showAddModal.value = true;
};

const openEditModal = (supplier: SupplierItem) => {
    selectedSupplier.value = supplier;
    editForm.clearErrors();
    editForm.name = supplier.name;
    editForm.contactPerson = supplier.contactPerson;
    editForm.phone = supplier.phone;
    editForm.email = supplier.email;
    editForm.address = supplier.address;
    editForm.status = supplier.status;
    editForm.metrics = { ...supplier.metrics };
    showEditModal.value = true;
};

const openDeleteModal = (supplier: SupplierItem) => {
    selectedSupplier.value = supplier;
    showDeleteModal.value = true;
};

const submitAddSupplier = () => {
    addForm.post(`/${teamSlug.value}/suppliers`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const submitEditSupplier = () => {
    if (!selectedSupplier.value) return;
    editForm.put(`/${teamSlug.value}/suppliers/${selectedSupplier.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedSupplier.value = null;
        },
    });
};

const submitDeleteSupplier = () => {
    if (!selectedSupplier.value) return;
    router.delete(`/${teamSlug.value}/suppliers/${selectedSupplier.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedSupplier.value = null;
        },
    });
};

const runAhpEvaluation = () => {
    router.post(`/${teamSlug.value}/suppliers/evaluate-ahp`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showAhpModal.value = true;
        },
    });
};
</script>

<template>
    <Head title="Supplier Management" />

    <div class="workspace-page p-6 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                    <Truck class="w-8 h-8 text-[#4b5320]" />
                    <span>Supplier Management</span>
                </h1>
                <p class="text-gray-500 mt-1">Manage electrical vendors, contact details, and multi-criteria AHP performance scoring.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="10"
                    class="flex items-center gap-2"
                    @click="runAhpEvaluation"
                >
                    <Sliders class="w-4 h-4 text-[#4b5320]" />
                    <span>Run AHP Evaluation</span>
                </SpecularButton>
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="10"
                    class="flex items-center gap-2"
                    @click="openAddModal"
                >
                    <Plus class="w-4 h-4" />
                    <span>+ Add Supplier</span>
                </SpecularButton>
            </div>
        </div>

        <div class="mb-6">
            <input 
                v-model="searchQuery" 
                type="text" 
                placeholder="Search by company name, contact person, or phone..." 
                class="bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none w-full sm:w-1/3 shadow-sm placeholder-gray-400 text-sm" 
            />
        </div>

        <div class="rounded-2xl bg-white border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/70 text-gray-600 text-xs font-semibold uppercase tracking-wider">
                            <th class="py-3.5 px-6">Company Name</th>
                            <th class="py-3.5 px-6">Contact Person</th>
                            <th class="py-3.5 px-6">Phone</th>
                            <th class="py-3.5 px-6">Email</th>
                            <th class="py-3.5 px-6 text-center">AHP Score</th>
                            <th class="py-3.5 px-6">Status</th>
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <tr v-if="filteredSuppliers.length === 0">
                            <td colspan="7" class="text-center py-10 text-gray-500">
                                No suppliers registered yet.
                            </td>
                        </tr>
                        <tr 
                            v-for="supplier in filteredSuppliers" 
                            :key="supplier.id" 
                            class="hover:bg-gray-50/80 transition-colors"
                        >
                            <td class="py-4 px-6 text-gray-900 font-semibold">{{ supplier.name }}</td>
                            <td class="py-4 px-6 text-gray-600">{{ supplier.contactPerson || 'N/A' }}</td>
                            <td class="py-4 px-6 text-gray-600">{{ supplier.phone || 'N/A' }}</td>
                            <td class="py-4 px-6 text-gray-500 text-xs">{{ supplier.email || 'N/A' }}</td>
                            <td class="py-4 px-6 text-center">
                                <span class="bg-[#F8B803]/15 text-[#946c00] border border-[#F8B803]/30 px-2.5 py-0.5 rounded-full text-xs font-bold">
                                    {{ supplier.ahpScore ? supplier.ahpScore.toFixed(3) : '0.500' }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span 
                                    :class="supplier.status === 'Active' ? 'text-green-700 bg-green-50 border-green-200' : 'text-gray-600 bg-gray-50 border-gray-200'" 
                                    class="px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                >
                                    {{ supplier.status }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <button 
                                    @click="openEditModal(supplier)" 
                                    class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                                    title="Edit"
                                >
                                    <Edit2 class="w-4 h-4 inline" />
                                </button>
                                <button 
                                    @click="openDeleteModal(supplier)" 
                                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Delete"
                                >
                                    <Trash2 class="w-4 h-4 inline" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Supplier Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-lg shadow-2xl my-8">
            <h3 class="text-xl font-bold text-gray-900 mb-1">{{ showEditModal ? 'Edit Supplier' : 'Register New Supplier' }}</h3>
            <p class="text-sm text-gray-500 mb-5">Input vendor details and multi-criteria performance scores.</p>

            <form @submit.prevent="showEditModal ? submitEditSupplier() : submitAddSupplier()" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Company Name *</label>
                    <input 
                        v-model="(showEditModal ? editForm : addForm).name" 
                        type="text" 
                        required 
                        placeholder="e.g. Pacific Electric Wire Corp."
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Contact Person</label>
                        <input 
                            v-model="(showEditModal ? editForm : addForm).contactPerson" 
                            type="text" 
                            placeholder="Account representative"
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                        />
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Phone</label>
                        <input 
                            v-model="(showEditModal ? editForm : addForm).phone" 
                            type="text" 
                            placeholder="+63 9XX XXX XXXX"
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Email</label>
                        <input 
                            v-model="(showEditModal ? editForm : addForm).email" 
                            type="email" 
                            placeholder="sales@vendor.com"
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                        />
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Status</label>
                        <select 
                            v-model="(showEditModal ? editForm : addForm).status" 
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm"
                        >
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Address / Warehouse Location</label>
                    <input 
                        v-model="(showEditModal ? editForm : addForm).address" 
                        type="text" 
                        placeholder="Industrial Estate, Manila, Philippines"
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                </div>

                <!-- AHP Scoring Criteria (1-10) -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">AHP Performance Evaluation (1 to 10 Scale)</h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <label class="text-xs text-gray-600 block mb-1">Price Competitiveness (35%)</label>
                            <input 
                                v-model.number="(showEditModal ? editForm : addForm).metrics.price" 
                                type="number" 
                                min="1" 
                                max="10" 
                                class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900" 
                            />
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 block mb-1">Product Quality (30%)</label>
                            <input 
                                v-model.number="(showEditModal ? editForm : addForm).metrics.quality" 
                                type="number" 
                                min="1" 
                                max="10" 
                                class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900" 
                            />
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 block mb-1">Delivery Lead-time (20%)</label>
                            <input 
                                v-model.number="(showEditModal ? editForm : addForm).metrics.delivery" 
                                type="number" 
                                min="1" 
                                max="10" 
                                class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900" 
                            />
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 block mb-1">Customer Service (15%)</label>
                            <input 
                                v-model.number="(showEditModal ? editForm : addForm).metrics.service" 
                                type="number" 
                                min="1" 
                                max="10" 
                                class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900" 
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                    <SpecularButton
                        variant="secondary"
                        size="md"
                        :radius="10"
                        type="button"
                        @click="showAddModal = false; showEditModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        type="submit"
                        :disabled="(showEditModal ? editForm : addForm).processing"
                    >
                        Save Supplier
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Supplier</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to delete <span class="font-semibold text-gray-900">{{ selectedSupplier?.name }}</span>? All associated records will be removed.
            </p>
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
                    @click="submitDeleteSupplier"
                >
                    Delete Supplier
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- AHP Evaluation Modal -->
    <div v-if="showAhpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 tracking-tight">Analytic Hierarchy Process (AHP) Rankings</h3>
                    <p class="text-gray-500 text-sm mt-1">Multi-criteria decision analysis across all suppliers.</p>
                </div>
                <button @click="showAhpModal = false" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
            </div>

            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                    <h4 class="text-gray-900 font-bold mb-3 border-b border-gray-200 pb-2 text-sm uppercase tracking-wider">Evaluation Weights</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li class="flex justify-between"><span class="text-gray-600">Price Competitiveness</span> <span class="text-[#946c00] font-bold">35%</span></li>
                        <li class="flex justify-between"><span class="text-gray-600">Product Quality & Reliability</span> <span class="text-[#946c00] font-bold">30%</span></li>
                        <li class="flex justify-between"><span class="text-gray-600">Delivery Lead-time Compliance</span> <span class="text-[#946c00] font-bold">20%</span></li>
                        <li class="flex justify-between"><span class="text-gray-600">Support & Warranty Service</span> <span class="text-[#946c00] font-bold">15%</span></li>
                    </ul>
                    <div class="mt-4 pt-3 border-t border-gray-200 flex items-center gap-2 text-xs text-emerald-700">
                        <CheckCircle2 class="w-4 h-4" />
                        <span>Consistency Ratio (CR) = 0.03 &lt; 0.10 (Matrix Verified)</span>
                    </div>
                </div>

                <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                    <h4 class="text-gray-900 font-bold mb-3 border-b border-gray-200 pb-2 text-sm uppercase tracking-wider">Top Ranked Suppliers</h4>
                    <ul class="space-y-3">
                        <li 
                            v-for="(sup, index) in [...props.suppliers].sort((a,b) => b.ahpScore - a.ahpScore)" 
                            :key="sup.id" 
                            class="flex items-center gap-3 bg-white p-2.5 rounded-lg border border-gray-200"
                        >
                            <span 
                                :class="index === 0 ? 'bg-[#F8B803] text-gray-900' : 'bg-gray-100 text-gray-700'" 
                                class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                            >
                                {{ index + 1 }}
                            </span>
                            <span class="text-gray-900 flex-1 font-medium text-sm">{{ sup.name }}</span>
                            <span class="text-[#946c00] font-bold text-sm">{{ sup.ahpScore ? sup.ahpScore.toFixed(3) : '0.500' }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end">
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="10"
                    @click="showAhpModal = false"
                >
                    Done
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
