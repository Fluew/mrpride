<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import { Users, UserPlus, Shield, Mail, Key, Trash2, Edit2, AlertCircle } from '@lucide/vue';

interface UserItem {
    id: number;
    name: string;
    email: string;
    role: string;
    raw_role: string;
    status: string;
    created_at: string;
    is_self: boolean;
}

const props = defineProps<{
    users: UserItem[];
    filters?: {
        search?: string;
        role?: string;
    };
    roles?: string[];
}>();

const page = usePage();
const teamSlug = computed(() => (page.props as any).currentTeam?.slug || '');

const searchQuery = ref(props.filters?.search || '');
const selectedRole = ref(props.filters?.role || 'All Roles');

const filteredUsers = computed(() => {
    return (props.users || []).filter(u => {
        const matchesSearch = u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                              u.email.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesRole = selectedRole.value === 'All Roles' || u.role === selectedRole.value;
        return matchesSearch && matchesRole;
    });
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedUser = ref<UserItem | null>(null);

const addForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'Staff',
    status: 'Active',
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'Staff',
    status: 'Active',
});

const openAddModal = () => {
    addForm.reset();
    addForm.clearErrors();
    showAddModal.value = true;
};

const openEditModal = (user: UserItem) => {
    selectedUser.value = user;
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.status = user.status;
    showEditModal.value = true;
};

const openDeleteModal = (user: UserItem) => {
    selectedUser.value = user;
    showDeleteModal.value = true;
};

const submitAddUser = () => {
    addForm.post(`/${teamSlug.value}/users`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const submitEditUser = () => {
    if (!selectedUser.value) return;
    editForm.put(`/${teamSlug.value}/users/${selectedUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedUser.value = null;
        },
    });
};

const submitDeleteUser = () => {
    if (!selectedUser.value) return;
    router.delete(`/${teamSlug.value}/users/${selectedUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedUser.value = null;
        },
    });
};
</script>

<template>
    <Head title="User Management" />

    <div class="workspace-page p-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                    <Users class="w-8 h-8 text-[#4b5320]" />
                    <span>User Management</span>
                </h1>
                <p class="text-gray-500 mt-1">Manage system administrators, staff accounts, and role permissions.</p>
            </div>
            <SpecularButton
                variant="brand"
                size="md"
                :radius="10"
                class="flex items-center gap-2"
                @click="openAddModal"
            >
                <UserPlus class="w-4 h-4" />
                <span>+ Add User</span>
            </SpecularButton>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <input 
                v-model="searchQuery" 
                type="text" 
                placeholder="Search by name or email..." 
                class="bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] focus:outline-none w-full sm:w-1/3 shadow-sm placeholder-gray-400" 
            />
            <select 
                v-model="selectedRole" 
                class="bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] focus:outline-none w-full sm:w-1/4 shadow-sm"
            >
                <option>All Roles</option>
                <option>Admin</option>
                <option>Staff</option>
                <option>Member</option>
            </select>
        </div>

        <!-- User Table -->
        <div class="rounded-2xl bg-white border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/70 text-gray-600 text-xs font-semibold uppercase tracking-wider">
                            <th class="py-3.5 px-6">Name</th>
                            <th class="py-3.5 px-6">Email</th>
                            <th class="py-3.5 px-6">Role</th>
                            <th class="py-3.5 px-6">Status</th>
                            <th class="py-3.5 px-6">Created Date</th>
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <tr v-if="filteredUsers.length === 0">
                            <td colspan="6" class="text-center py-10 text-gray-500">
                                No users found matching your search.
                            </td>
                        </tr>
                        <tr 
                            v-for="user in filteredUsers" 
                            :key="user.id" 
                            class="hover:bg-gray-50/80 transition-colors"
                        >
                            <td class="py-4 px-6 text-gray-900 font-semibold flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-[#4b5320]/10 text-[#4b5320] font-bold flex items-center justify-center text-sm">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span>{{ user.name }}</span>
                                        <span v-if="user.is_self" class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-normal">You</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-gray-600">{{ user.email }}</td>
                            <td class="py-4 px-6">
                                <span 
                                    :class="user.role === 'Admin' || user.raw_role === 'owner' 
                                        ? 'bg-[#F8B803]/15 text-[#946c00] border-[#F8B803]/30' 
                                        : 'bg-blue-50 text-blue-700 border-blue-200'" 
                                    class="px-2.5 py-1 rounded-full text-xs font-semibold border inline-flex items-center gap-1.5"
                                >
                                    <Shield class="w-3 h-3" />
                                    <span>{{ user.role }}</span>
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span 
                                    :class="user.status === 'Active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'" 
                                    class="px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                >
                                    {{ user.status }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-gray-500 text-xs">{{ user.created_at }}</td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <button 
                                    @click="openEditModal(user)" 
                                    class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                                    title="Edit User"
                                >
                                    <Edit2 class="w-4 h-4 inline" />
                                </button>
                                <button 
                                    v-if="!user.is_self"
                                    @click="openDeleteModal(user)" 
                                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Delete User"
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

    <!-- Add User Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-1">Add New Workspace User</h3>
            <p class="text-sm text-gray-500 mb-5">Create a login account with designated role privileges.</p>

            <form @submit.prevent="submitAddUser" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Full Name</label>
                    <input 
                        v-model="addForm.name" 
                        type="text" 
                        required 
                        placeholder="e.g. Maria Santos"
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                    <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-600">{{ addForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Email Address</label>
                    <input 
                        v-model="addForm.email" 
                        type="email" 
                        required 
                        placeholder="name@mrpride.com"
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                    <p v-if="addForm.errors.email" class="mt-1 text-xs text-red-600">{{ addForm.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Initial Password</label>
                    <input 
                        v-model="addForm.password" 
                        type="password" 
                        required 
                        minlength="8"
                        placeholder="At least 8 characters"
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                    <p v-if="addForm.errors.password" class="mt-1 text-xs text-red-600">{{ addForm.errors.password }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Role</label>
                        <select 
                            v-model="addForm.role" 
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm"
                        >
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                            <option value="Member">Member</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Status</label>
                        <select 
                            v-model="addForm.status" 
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm"
                        >
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <SpecularButton
                        variant="secondary"
                        size="md"
                        :radius="10"
                        type="button"
                        @click="showAddModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        type="submit"
                        :disabled="addForm.processing"
                    >
                        {{ addForm.processing ? 'Creating...' : 'Create Account' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-1">Edit User</h3>
            <p class="text-sm text-gray-500 mb-5">Update account details, role permissions, or reset password.</p>

            <form @submit.prevent="submitEditUser" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Full Name</label>
                    <input 
                        v-model="editForm.name" 
                        type="text" 
                        required 
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                    <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Email Address</label>
                    <input 
                        v-model="editForm.email" 
                        type="email" 
                        required 
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                    <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Change Password (leave blank to keep current)</label>
                    <input 
                        v-model="editForm.password" 
                        type="password" 
                        placeholder="••••••••"
                        class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm" 
                    />
                    <p v-if="editForm.errors.password" class="mt-1 text-xs text-red-600">{{ editForm.errors.password }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Role</label>
                        <select 
                            v-model="editForm.role" 
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm"
                        >
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                            <option value="Member">Member</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-1">Status</label>
                        <select 
                            v-model="editForm.status" 
                            class="w-full bg-white border border-gray-200 rounded-lg p-2.5 text-gray-900 focus:border-[#F8B803] focus:outline-none shadow-sm text-sm"
                        >
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <SpecularButton
                        variant="secondary"
                        size="md"
                        :radius="10"
                        type="button"
                        @click="showEditModal = false"
                    >
                        Cancel
                    </SpecularButton>
                    <SpecularButton
                        variant="brand"
                        size="md"
                        :radius="10"
                        type="submit"
                        :disabled="editForm.processing"
                    >
                        {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                    </SpecularButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="w-12 h-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center mb-4">
                <AlertCircle class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Remove User Account</h3>
            <p class="text-gray-600 text-sm mb-6">
                Are you sure you want to remove <span class="font-semibold text-gray-900">{{ selectedUser?.name }}</span> from this workspace? They will lose access immediately.
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
                    @click="submitDeleteUser"
                >
                    Yes, Remove User
                </SpecularButton>
            </div>
        </div>
    </div>
</template>
