<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const products = ref([
    { id: 1, name: 'Industrial Circuit Breaker', sku: 'BRK-IND-001', category: 'Breakers', price: '₱120.00', stock: 45, status: 'In Stock' },
    { id: 2, name: 'Heavy Duty Copper Wire (100m)', sku: 'WR-CU-100', category: 'Wiring', price: '₱85.00', stock: 8, status: 'Low Stock' },
    { id: 3, name: 'LED Floodlight 200W', sku: 'LGT-FLD-200', category: 'Lighting', price: '₱45.00', stock: 0, status: 'Out of Stock' },
    { id: 4, name: 'Magnetic Contactor 220V', sku: 'MC-220V-01', category: 'Controls', price: '₱35.00', stock: 112, status: 'In Stock' },
    { id: 5, name: 'Digital Multimeter PRO', sku: 'TLS-DMM-PRO', category: 'Tools', price: '₱210.00', stock: 15, status: 'In Stock' },
    { id: 6, name: 'Thermal Overload Relay', sku: 'REL-THM-50', category: 'Controls', price: '₱45.50', stock: 30, status: 'In Stock' },
    { id: 7, name: 'PVC Electrical Conduit (10ft)', sku: 'PVC-CND-10', category: 'Wiring', price: '₱12.00', stock: 200, status: 'In Stock' },
    { id: 8, name: 'Crimping Tool Heavy Duty', sku: 'TLS-CRM-HD', category: 'Tools', price: '₱65.00', stock: 4, status: 'Low Stock' },
    { id: 9, name: 'Distribution Panel 12-Way', sku: 'PNL-DST-12', category: 'Breakers', price: '₱150.00', stock: 25, status: 'In Stock' },
    { id: 10, name: 'Industrial Socket 32A', sku: 'SCK-IND-32', category: 'Wiring', price: '₱28.00', stock: 60, status: 'In Stock' },
]);

const searchQuery = ref('');
const selectedCategory = ref('All Categories');

const filteredProducts = computed(() => {
    return products.value.filter(p => {
        const matchesSearch = p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || p.sku.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCategory = selectedCategory.value === 'All Categories' || p.category === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedProduct = ref<any>(null);

const openAddModal = () => showAddModal.value = true;
const openEditModal = (product: any) => { selectedProduct.value = { ...product }; showEditModal.value = true; };
const openDeleteModal = (product: any) => { selectedProduct.value = product; showDeleteModal.value = true; };

const saveProduct = () => { alert('Product saved (Prototype)'); showAddModal.value = false; showEditModal.value = false; };
const deleteProduct = () => { alert('Product deleted (Prototype)'); showDeleteModal.value = false; };

const getStatusClass = (status: string) => {
    if (status === 'In Stock') return 'bg-green-500/10 text-green-500 border-green-500/20';
    if (status === 'Low Stock') return 'bg-[#F8B803]/10 text-[#F8B803] border-[#F8B803]/20';
    return 'bg-red-500/10 text-red-500 border-red-500/20';
};
</script>

<template>
    <Head title="Inventory Management" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Inventory Management</h1>
                <p class="text-gray-500 mt-1">Track and manage electrical supplies and products.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <button onclick="alert('Importing CSV... (Prototype)')" class="w-full sm:w-auto px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-medium rounded-lg hover:border-[#F8B803] hover:text-gray-900 transition-all duration-300 shadow-sm">
                    Import CSV
                </button>
                <button @click="openAddModal" class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all duration-300">
                    + Add Product
                </button>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 mb-4">
            <input v-model="searchQuery" type="text" placeholder="Search by name or SKU..." class="bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none w-full sm:w-1/3 shadow-sm" />
            <select v-model="selectedCategory" class="bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none w-full sm:w-1/4 shadow-sm">
                <option>All Categories</option>
                <option>Breakers</option>
                <option>Wiring</option>
                <option>Lighting</option>
                <option>Controls</option>
                <option>Tools</option>
            </select>
        </div>

        <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 text-sm">
                            <th class="pb-3 font-medium">SKU</th>
                            <th class="pb-3 font-medium">Product Name</th>
                            <th class="pb-3 font-medium">Category</th>
                            <th class="pb-3 font-medium">Price</th>
                            <th class="pb-3 font-medium text-center">Stock</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr v-for="product in filteredProducts" :key="product.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                            <td class="py-4 text-gray-500 font-mono text-xs">{{ product.sku }}</td>
                            <td class="py-4 text-gray-900 font-medium">{{ product.name }}</td>
                            <td class="py-4 text-gray-600">{{ product.category }}</td>
                            <td class="py-4 text-gray-600">{{ product.price }}</td>
                            <td class="py-4 text-center text-gray-900 font-bold">{{ product.stock }}</td>
                            <td class="py-4">
                                <span :class="getStatusClass(product.status)" class="px-2.5 py-1 rounded-full text-xs font-medium border">
                                    {{ product.status }}
                                </span>
                            </td>
                            <td class="py-4 text-right">
                                <button @click="openEditModal(product)" class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                <button @click="openDeleteModal(product)" class="text-red-500 hover:text-red-700">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-between items-center text-sm text-gray-500">
                <span>Showing {{ filteredProducts.length }} of {{ products.length }} entries</span>
                <div class="flex gap-2">
                    <button class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-50 disabled:opacity-50" disabled>Previous</button>
                    <button class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-50 disabled:opacity-50" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-[500px] shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-4">{{ showEditModal ? 'Edit Product' : 'Add Product' }}</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-gray-700 text-sm mb-1">Product Name</label>
                    <input type="text" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none" :value="selectedProduct?.name || ''" />
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">SKU</label>
                    <input type="text" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none" :value="selectedProduct?.sku || ''" />
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Category</label>
                    <select class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none">
                        <option>Breakers</option>
                        <option>Wiring</option>
                        <option>Lighting</option>
                        <option>Controls</option>
                        <option>Tools</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Price</label>
                    <input type="text" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none" :value="selectedProduct?.price || ''" />
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Stock Quantity</label>
                    <input type="number" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none" :value="selectedProduct?.stock || 0" />
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button @click="showAddModal = false; showEditModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                <button @click="saveProduct" class="px-4 py-2 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-white font-bold rounded-lg shadow-md">Save</button>
            </div>
        </div>
    </div>

    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-[400px] shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Delete</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete {{ selectedProduct?.name }}? This action cannot be undone.</p>
            <div class="flex justify-end gap-3">
                <button @click="showDeleteModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                <button @click="deleteProduct" class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 shadow-sm">Delete</button>
            </div>
        </div>
    </div>
</template>
