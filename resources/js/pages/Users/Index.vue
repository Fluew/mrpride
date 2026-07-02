<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const users = ref([
    { id: 1, name: 'John Doe', email: 'admin@mrp.com', role: 'Admin', status: 'Active' },
    { id: 2, name: 'Jane Smith', email: 'staff1@mrp.com', role: 'Staff', status: 'Active' },
    { id: 3, name: 'Bob Johnson', email: 'staff2@mrp.com', role: 'Staff', status: 'Inactive' },
    { id: 4, name: 'Alice Williams', email: 'alice.w@mrp.com', role: 'Admin', status: 'Active' },
    { id: 5, name: 'Charlie Brown', email: 'charlie.b@mrp.com', role: 'Staff', status: 'Active' },
]);

const searchQuery = ref('');
const selectedRole = ref('All Roles');

const filteredUsers = computed(() => {
    return users.value.filter(u => {
        const matchesSearch = u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                              u.email.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesRole = selectedRole.value === 'All Roles' || u.role === selectedRole.value;
        return matchesSearch && matchesRole;
    });
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedUser = ref<any>(null);

const openAddModal = () => {
    showAddModal.value = true;
};

const openEditModal = (user: any) => {
    selectedUser.value = { ...user };
    showEditModal.value = true;
};

const openDeleteModal = (user: any) => {
    selectedUser.value = user;
    showDeleteModal.value = true;
};

const saveUser = () => {
    alert('User saved (Prototype)');
    showAddModal.value = false;
    showEditModal.value = false;
};

const deleteUser = () => {
    alert('User deleted (Prototype)');
    showDeleteModal.value = false;
};
</script>

<template>
    <Head title="User Management" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto rounded-xl p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">User Management</h1>
                <p class="text-gray-500 mt-1">Manage system administrators and staff accounts.</p>
            </div>
            <button @click="openAddModal" class="w-full md:w-auto px-5 py-2.5 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all duration-300">
                + Add User
            </button>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 mb-2">
            <input v-model="searchQuery" type="text" placeholder="Search by name or email..." class="bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none w-full sm:w-1/3 shadow-sm placeholder-gray-400" />
            <select v-model="selectedRole" class="bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none w-full sm:w-1/4 shadow-sm">
                <option>All Roles</option>
                <option>Admin</option>
                <option>Staff</option>
            </select>
        </div>

        <div class="flex-1 rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 text-sm">
                            <th class="pb-3 font-medium">Name</th>
                            <th class="pb-3 font-medium">Email</th>
                            <th class="pb-3 font-medium">Role</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr v-for="user in filteredUsers" :key="user.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                            <td class="py-4 text-gray-900 font-medium">{{ user.name }}</td>
                            <td class="py-4 text-gray-600">{{ user.email }}</td>
                            <td class="py-4">
                                <span :class="user.role === 'Admin' ? 'bg-[#F8B803]/10 text-[#F8B803] border-[#F8B803]/20' : 'bg-blue-50 text-blue-600 border-blue-200'" class="px-2.5 py-1 rounded-full text-xs font-medium border">
                                    {{ user.role }}
                                </span>
                            </td>
                            <td class="py-4">
                                <span :class="user.status === 'Active' ? 'text-green-600' : 'text-gray-500'">{{ user.status }}</span>
                            </td>
                            <td class="py-4 text-right">
                                <button @click="openEditModal(user)" class="text-gray-500 hover:text-gray-900 mr-3">Edit</button>
                                <button @click="openDeleteModal(user)" class="text-red-500 hover:text-red-700">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-[400px] shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-4">{{ showEditModal ? 'Edit User' : 'Add User' }}</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Name</label>
                    <input type="text" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" :value="selectedUser?.name || ''" />
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Email</label>
                    <input type="email" class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm" :value="selectedUser?.email || ''" />
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-1">Role</label>
                    <select class="w-full bg-white border border-gray-200 rounded-lg p-2 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm">
                        <option>Admin</option>
                        <option>Staff</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button @click="showAddModal = false; showEditModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-900">Cancel</button>
                <button @click="saveUser" class="px-4 py-2 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-white font-bold rounded-lg hover:shadow-lg transition-all">Save</button>
            </div>
        </div>
    </div>

    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white border border-gray-200 p-6 rounded-xl w-[400px] shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Delete</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete {{ selectedUser?.name }}? This action cannot be undone.</p>
            <div class="flex justify-end gap-3">
                <button @click="showDeleteModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-900">Cancel</button>
                <button @click="deleteUser" class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>
</template>
