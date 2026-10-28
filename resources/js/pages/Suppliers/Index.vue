<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useCanDelete } from '@/composables/useCanDelete';
import { ref, computed, watch } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { Search, Plus, SlidersHorizontal, Trash2, Pencil, X, Check, CheckCircle2, Building2, Phone, Mail, Award } from '@lucide/vue';

interface SupplierMetrics {
    price?: number;
    quality?: number;
    delivery?: number;
    service?: number;
}

interface Supplier {
    id: number;
    name: string;
    contact_person?: string | null;
    phone?: string | null;
    email?: string | null;
    address?: string | null;
    status: 'Active' | 'Inactive';
    ahp_score?: number | null;
    metrics?: SupplierMetrics | null;
}

const props = defineProps<{
    suppliers: {
        data: Supplier[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    filters?: {
        search?: string;
        status?: string;
        sort?: string;
    };
}>();

const page = usePage();
const canDelete = useCanDelete();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || (page.props as any).team?.slug || '');

// Search & Filter
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || 'All');
const selectedSort = ref(props.filters?.sort || 'ahp_desc');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const triggerNavigation = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    router.get(
        `/${teamSlug.value}/suppliers`,
        {
            search: search.value || undefined,
            status: selectedStatus.value !== 'All' ? selectedStatus.value : undefined,
            sort: selectedSort.value !== 'ahp_desc' ? selectedSort.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const applySearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(triggerNavigation, 300);
};

const applyFilter = () => {
    triggerNavigation();
};

watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        if (newFilters.search !== undefined) search.value = newFilters.search || '';
        if (newFilters.status !== undefined) selectedStatus.value = newFilters.status || 'All';
        if (newFilters.sort !== undefined) selectedSort.value = newFilters.sort || 'ahp_desc';
    }
}, { deep: true });

// Modals
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const deleteConfirmInput = ref('');
const showAhpModal = ref(false);
const selectedSupplier = ref<Supplier | null>(null);

// Form
const form = useForm({
    id: null as number | null,
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    status: 'Active' as 'Active' | 'Inactive',
});

const openAddModal = () => {
    form.reset();
    form.clearErrors();
    form.status = 'Active';
    showAddModal.value = true;
};

const openEditModal = (supplier: Supplier) => {
    selectedSupplier.value = supplier;
    form.clearErrors();
    form.id = supplier.id;
    form.name = supplier.name;
    form.contact_person = supplier.contact_person || '';
    form.phone = supplier.phone || '';
    form.email = supplier.email || '';
    form.address = supplier.address || '';
    form.status = supplier.status;
    showEditModal.value = true;
};

const openDeleteModal = (supplier: Supplier) => {
    selectedSupplier.value = supplier;
    deleteConfirmInput.value = '';
    showDeleteModal.value = true;
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

const promptSaveSupplier = () => {
    if (!form.name.trim()) return;

    if (showEditModal.value) {
        openConfirmModal({
            title: 'Confirm Supplier Changes',
            description: `Are you sure you want to save modifications to "${form.name}"?`,
            details: [
                { label: 'Company Name', value: form.name },
                { label: 'Contact Person', value: form.contact_person || 'N/A' },
                { label: 'Phone', value: form.phone || 'N/A' },
                { label: 'Status', value: form.status },
            ],
            confirmText: 'Save Changes',
            action: submitEdit,
        });
    } else {
        openConfirmModal({
            title: 'Confirm New Supplier',
            description: `Are you sure you want to register "${form.name}" as an active supplier?`,
            details: [
                { label: 'Company Name', value: form.name },
                { label: 'Contact Person', value: form.contact_person || 'N/A' },
                { label: 'Phone', value: form.phone || 'N/A' },
                { label: 'Status', value: form.status },
            ],
            confirmText: 'Add Supplier',
            action: submitAdd,
        });
    }
};

const submitAdd = () => {
    form.post(`/${teamSlug.value}/suppliers`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

const submitEdit = () => {
    if (!form.id) return;
    form.put(`/${teamSlug.value}/suppliers/${form.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            form.reset();
        },
    });
};

const confirmDelete = () => {
    if (!selectedSupplier.value) return;
    router.delete(`/${teamSlug.value}/suppliers/${selectedSupplier.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedSupplier.value = null;
        },
    });
};

// AHP Evaluation State
const ahpWeights = ref({
    price: 50,
    quality: 30,
    delivery: 20,
});

const ahpSupplierScores = ref<Array<{
    id: number;
    name: string;
    price: number;
    quality: number;
    delivery: number;
}>>([]);

const initAhp = () => {
    ahpSupplierScores.value = props.suppliers.data.map(s => ({
        id: s.id,
        name: s.name,
        price: s.metrics?.price ?? 8,
        quality: s.metrics?.quality ?? 8,
        delivery: s.metrics?.delivery ?? 7,
    }));
    showAhpModal.value = true;
};

const calculatedRankings = computed(() => {
    const totalWeight = ahpWeights.value.price + ahpWeights.value.quality + ahpWeights.value.delivery;
    if (totalWeight <= 0) return [];

    const normW = {
        price: ahpWeights.value.price / totalWeight,
        quality: ahpWeights.value.quality / totalWeight,
        delivery: ahpWeights.value.delivery / totalWeight,
    };

    return ahpSupplierScores.value.map(s => {
        const score = (s.price / 10) * normW.price +
                      (s.quality / 10) * normW.quality +
                      (s.delivery / 10) * normW.delivery;
        return {
            ...s,
            calculatedScore: Number(score.toFixed(3)),
        };
    }).sort((a, b) => b.calculatedScore - a.calculatedScore);
});

const savingAhp = ref(false);
const promptSaveAhp = () => {
    openConfirmModal({
        title: 'Confirm AHP Criteria & Rankings',
        description: 'Are you sure you want to save and apply the analytical hierarchy process (AHP) criteria weights and supplier performance evaluations?',
        details: [
            { label: 'Price / Cost Weight', value: `${ahpWeights.value.price}%` },
            { label: 'Quality Weight', value: `${ahpWeights.value.quality}%` },
            { label: 'Delivery Reliability Weight', value: `${ahpWeights.value.delivery}%` },
        ],
        confirmText: 'Save Rankings',
        action: saveAhp,
    });
};

const saveAhp = () => {
    savingAhp.value = true;
    router.post(
        `/${teamSlug.value}/suppliers/ahp`,
        {
            criteria_weights: ahpWeights.value,
            supplier_scores: ahpSupplierScores.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                savingAhp.value = false;
                showAhpModal.value = false;
            },
        }
    );
};
</script>

<template>
    <Head title="Supplier Management" />

    <div class="workspace-page">
        <!-- Header -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-4">
            <div>
                <h1 class="page-title">Supplier Management</h1>
                <p class="page-subtitle">Supplier contacts, contracts and AHP evaluations.</p>
            </div>
            <div class="flex flex-wrap items-center justify-start lg:justify-end gap-2.5 w-full lg:w-auto">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="12"
                    @click="initAhp"
                >
                    <SlidersHorizontal class="w-4 h-4 mr-1.5 inline" />
                    Run AHP Evaluation
                </SpecularButton>
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="12"
                    @click="openAddModal"
                >
                    <Plus class="w-4 h-4 mr-1.5 inline" />
                    Add Supplier
                </SpecularButton>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <div class="relative flex-1 min-w-[240px] max-w-md">
                <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    @input="applySearch"
                    type="text"
                    placeholder="Search by supplier name, contact, phone..."
                    class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#F8B803]"
                />
            </div>

            <div class="w-40">
                <select
                    v-model="selectedStatus"
                    @change="applyFilter"
                    class="w-full py-2 px-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:outline-none focus:border-[#F8B803] cursor-pointer"
                >
                    <option value="All">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div class="w-48">
                <select
                    v-model="selectedSort"
                    @change="applyFilter"
                    class="w-full py-2 px-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:outline-none focus:border-[#F8B803] cursor-pointer"
                >
                    <option value="ahp_desc">Highest AHP Score</option>
                    <option value="name_asc">Name (A-Z)</option>
                    <option value="name_desc">Name (Z-A)</option>
                </select>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="flex-1 rounded-xl bg-white border border-gray-200 p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 text-sm">
                            <th class="pb-3 font-medium">Company Name</th>
                            <th class="pb-3 font-medium">Contact Person</th>
                            <th class="pb-3 font-medium">Phone & Email</th>
                            <th class="pb-3 font-medium">AHP Score</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr v-if="!suppliers.data.length">
                            <td colspan="6" class="py-8 text-center text-gray-500">
                                No suppliers found. Click "+ Add Supplier" to register your first supplier.
                            </td>
                        </tr>
                        <tr v-for="supplier in suppliers.data" :key="supplier.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                            <td class="py-4 text-gray-900 font-semibold flex items-center gap-2">
                                <Building2 class="w-4 h-4 text-gray-400" />
                                {{ supplier.name }}
                            </td>
                            <td class="py-4 text-gray-600">{{ supplier.contact_person || '—' }}</td>
                            <td class="py-4 text-gray-600 text-xs space-y-0.5">
                                <div v-if="supplier.phone" class="flex items-center gap-1.5">
                                    <Phone class="w-3.5 h-3.5 text-gray-400" />
                                    {{ supplier.phone }}
                                </div>
                                <div v-if="supplier.email" class="flex items-center gap-1.5">
                                    <Mail class="w-3.5 h-3.5 text-gray-400" />
                                    {{ supplier.email }}
                                </div>
                            </td>
                            <td class="py-4">
                                <span v-if="supplier.ahp_score !== null && supplier.ahp_score !== undefined" class="text-[#F8B803] font-semibold text-base flex items-center gap-1">
                                    <Award class="w-4 h-4 text-[#F8B803]" />
                                    {{ Number(supplier.ahp_score).toFixed(3) }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">Unranked</span>
                            </td>
                            <td class="py-4">
                                <StatusBadge :status="supplier.status" size="sm" />
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <SpecularButton
                                        variant="secondary"
                                        size="sm"
                                        :radius="8"
                                        @click="openEditModal(supplier)"
                                    >
                                        Edit
                                    </SpecularButton>
                                    <SpecularButton
                                        v-if="canDelete"
                                        variant="danger"
                                        size="sm"
                                        :radius="8"
                                        @click="openDeleteModal(supplier)"
                                    >
                                        Delete
                                    </SpecularButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="suppliers.total > suppliers.data.length" class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4">
                <span class="text-xs text-gray-500">
                    Showing {{ suppliers.data.length }} of {{ suppliers.total }} suppliers
                </span>
                <div class="flex gap-1">
                    <button
                        v-for="(link, i) in suppliers.links"
                        :key="i"
                        :disabled="!link.url || link.active"
                        @click="link.url && router.visit(link.url, { preserveScroll: true })"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 text-xs rounded-lg border transition-colors',
                            link.active ? 'bg-[#F8B803] text-white border-[#F8B803] font-semibold' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Add / Edit Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-lg shadow-xl">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-semibold text-gray-900">{{ showEditModal ? 'Edit Supplier' : 'Add Supplier' }}</h3>
                <button @click="showAddModal = false; showEditModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <form @submit.prevent="promptSaveSupplier" class="space-y-4">
                <div class="relative">
                    <input
                        id="supplier-name"
                        v-model="form.name"
                        type="text"
                        required
                        placeholder=" "
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                        :class="form.errors.name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                    />
                    <label
                        for="supplier-name"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                        :class="form.name ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                    >
                        Company Name
                    </label>
                    <span v-if="form.errors.name" class="text-xs text-red-500 mt-1 block">{{ form.errors.name }}</span>
                </div>

                <div class="relative">
                    <input
                        id="supplier-contact"
                        v-model="form.contact_person"
                        type="text"
                        placeholder=" "
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                    />
                    <label
                        for="supplier-contact"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                        :class="form.contact_person ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                    >
                        Contact Person
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="relative">
                        <input
                            id="supplier-phone"
                            v-model="form.phone"
                            type="text"
                            placeholder=" "
                            class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                        />
                        <label
                            for="supplier-phone"
                            class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                            :class="form.phone ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                        >
                            Phone
                        </label>
                    </div>
                    <div class="relative">
                        <input
                            id="supplier-email"
                            v-model="form.email"
                            type="email"
                            placeholder=" "
                            class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                        />
                        <label
                            for="supplier-email"
                            class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                            :class="form.email ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                        >
                            Email
                        </label>
                    </div>
                </div>

                <div class="relative">
                    <textarea
                        id="supplier-address"
                        v-model="form.address"
                        rows="2"
                        placeholder=" "
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                    ></textarea>
                    <label
                        for="supplier-address"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                        :class="form.address ? '-translate-y-2.5 scale-75 text-[#4b5320]' : ''"
                    >
                        Address / Notes
                    </label>
                </div>

                <div class="relative">
                    <select
                        id="supplier-status"
                        v-model="form.status"
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                    >
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                    <label
                        for="supplier-status"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-[#4b5320]"
                    >
                        Status
                    </label>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-2 border-t border-gray-100">
                    <SpecularButton
                        type="button"
                        variant="secondary"
                        size="md"
                        :radius="10"
                        @click="showAddModal = false; showEditModal = false"
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
                        {{ form.processing ? 'Saving...' : 'Save Supplier' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-md shadow-xl">
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Confirm Delete</h3>
            <p class="text-gray-600 text-sm mb-4">Are you sure you want to delete <span class="font-semibold text-gray-900">{{ selectedSupplier?.name }}</span>? This will permanently remove the supplier record.</p>
            
            <div class="mt-4 mb-6">
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                    Type <span class="font-mono font-semibold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200">YES</span> if you wish to delete:
                </label>
                <input
                    v-model="deleteConfirmInput"
                    type="text"
                    placeholder="Type YES here"
                    class="w-full px-3 py-2 text-sm font-mono rounded-xl border border-gray-300 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                />
            </div>

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
                    :disabled="deleteConfirmInput.trim().toUpperCase() !== 'YES'"
                >
                    Delete Supplier
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- AHP Evaluation Modal -->
    <div v-if="showAhpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-2xl font-semibold text-gray-900 tracking-tight">Analytic Hierarchy Process (AHP)</h3>
                    <p class="text-gray-500 text-sm">Tune criteria weights and evaluate supplier metrics to calculate optimal vendor rankings across the 3 decision criteria.</p>
                </div>
                <button @click="showAhpModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <!-- Criteria Weights -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                    <h4 class="text-gray-900 font-semibold mb-3 border-b border-gray-200 pb-2 text-sm">Criteria Relative Weights (%)</h4>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-semibold text-gray-800">Price / Cost ({{ ahpWeights.price }}%)</span>
                            </div>
                            <p class="text-[11px] text-gray-500 mb-1.5">(price competitiveness of the supplier)</p>
                            <input v-model.number="ahpWeights.price" type="range" min="1" max="100" class="w-full accent-[#F8B803]" />
                        </div>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-semibold text-gray-800">Quality ({{ ahpWeights.quality }}%)</span>
                            </div>
                            <p class="text-[11px] text-gray-500 mb-1.5">(quality of product of the supplier evaluated by the staffs)</p>
                            <input v-model.number="ahpWeights.quality" type="range" min="1" max="100" class="w-full accent-[#F8B803]" />
                        </div>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-semibold text-gray-800">Delivery Reliability ({{ ahpWeights.delivery }}%)</span>
                            </div>
                            <p class="text-[11px] text-gray-500 mb-1.5">(reliability of supplier in terms on delivery to MR Pride)</p>
                            <input v-model.number="ahpWeights.delivery" type="range" min="1" max="100" class="w-full accent-[#F8B803]" />
                        </div>
                    </div>
                </div>

                <!-- Calculated Rankings -->
                <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                    <h4 class="text-gray-900 font-semibold mb-3 border-b border-gray-200 pb-2 text-sm">Calculated Rankings</h4>
                    <div class="space-y-2.5 max-h-[220px] overflow-y-auto pr-1">
                        <div v-for="(sup, idx) in calculatedRankings" :key="sup.id" class="flex items-center justify-between p-2 rounded-lg bg-white border border-gray-200 text-sm">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-full bg-[#F8B803]/20 text-[#92400e] flex items-center justify-center text-xs font-semibold">{{ idx + 1 }}</span>
                                <span class="font-medium text-gray-900">{{ sup.name }}</span>
                            </div>
                            <span class="font-semibold text-[#F8B803]">{{ sup.calculatedScore.toFixed(3) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Performance Matrix -->
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 mb-6">
                <h4 class="text-gray-900 font-semibold mb-3 text-sm">Rate Supplier Performance (1 = Poor, 10 = Excellent)</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="text-xs text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="pb-2">Supplier</th>
                                <th class="pb-2 text-center">Price / Cost (1-10)</th>
                                <th class="pb-2 text-center">Quality (1-10)</th>
                                <th class="pb-2 text-center">Delivery Reliability (1-10)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="sup in ahpSupplierScores" :key="sup.id">
                                <td class="py-3 text-gray-900 font-semibold">{{ sup.name }}</td>
                                <td class="py-3 text-center">
                                    <input v-model.number="sup.price" type="number" min="1" max="10" class="w-16 text-center bg-white border border-gray-200 rounded-lg py-1 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803]" />
                                </td>
                                <td class="py-3 text-center">
                                    <input v-model.number="sup.quality" type="number" min="1" max="10" class="w-16 text-center bg-white border border-gray-200 rounded-lg py-1 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803]" />
                                </td>
                                <td class="py-3 text-center">
                                    <input v-model.number="sup.delivery" type="number" min="1" max="10" class="w-16 text-center bg-white border border-gray-200 rounded-lg py-1 text-sm text-gray-900 focus:outline-none focus:border-[#F8B803]" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <SpecularButton
                    variant="secondary"
                    size="md"
                    :radius="10"
                    @click="showAhpModal = false"
                >
                    Cancel
                </SpecularButton>
                <SpecularButton
                    variant="brand"
                    size="md"
                    :radius="10"
                    :disabled="savingAhp"
                    @click="promptSaveAhp"
                >
                    {{ savingAhp ? 'Saving...' : 'Apply & Save Rankings' }}
                </SpecularButton>
            </div>
        </div>
    </div>

    <!-- Action Confirmation Modal -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-full max-w-md shadow-xl animate-in fade-in duration-150">
            <div class="w-11 h-11 rounded-full bg-amber-50 text-[#F8B803] flex items-center justify-center mb-4 border border-amber-100">
                <CheckCircle2 class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-1.5">{{ confirmOptions.title }}</h3>
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
                    <Check class="w-4 h-4 mr-1.5" />
                    <span>{{ confirmOptions.confirmText || 'Confirm & Save' }}</span>
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
