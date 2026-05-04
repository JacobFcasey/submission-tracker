<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';

const props = defineProps({
    roles: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const searchQuery = ref(props.filters.search || '');
const permissionSearch = ref('');

const performSearch = debounce(() => {
    const params = {};
    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }
    router.get('/admin/roles', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['roles', 'filters'],
    });
}, 300);

watch(searchQuery, () => {
    performSearch();
});

// Format date
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

// Drag & drop
const draggingPermission = ref(null);
const targetRole = ref(null);

const permissionGroups = computed(() => {
    const groups = {};
    props.permissions.forEach((permission) => {
        // Group by second word if exists, otherwise "general"
        const groupName = permission.name.split(' ')[1] || 'general';
        if (!groups[groupName]) {
            groups[groupName] = [];
        }
        groups[groupName].push(permission);
    });
    return groups;
});

const filteredPermissionGroups = computed(() => {
    const term = permissionSearch.value.trim().toLowerCase();
    if (!term) return permissionGroups.value;

    const filtered = {};
    Object.entries(permissionGroups.value).forEach(([groupName, permissions]) => {
        const hits = permissions.filter((permission) =>
            permission.name.toLowerCase().includes(term)
        );
        if (hits.length) {
            filtered[groupName] = hits;
        }
    });
    return filtered;
});

const startDrag = (event, permission) => {
    draggingPermission.value = permission;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', permission.id);
};

const onDragOver = (event, role) => {
    event.preventDefault();
    targetRole.value = role;
};

const onDragLeave = () => {
    targetRole.value = null;
};

const onDrop = (event, role) => {
    event.preventDefault();

    if (!draggingPermission.value) return;

    const form = useForm({
        permission_id: draggingPermission.value.id,
        action: 'attach',
    });

    form.post(`/admin/roles/${role.id}/permissions`, {
        preserveScroll: true,
        onSuccess: () => {
            draggingPermission.value = null;
            targetRole.value = null;
        },
    });
};

const removePermission = (role, permission) => {
    if (confirm(`Remove "${permission.name}" from ${role.name}?`)) {
        const form = useForm({
            permission_id: permission.id,
            action: 'detach',
        });

        form.post(`/admin/roles/${role.id}/permissions`, {
            preserveScroll: true,
        });
    }
};

// Create role modal
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedRole = ref(null);
const newRoleForm = useForm({
    name: '',
});
const editRoleForm = useForm({
    name: '',
});

const createRole = () => {
    newRoleForm.post('/admin/roles', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            newRoleForm.reset();
        },
    });
};

const openEditModal = (role) => {
    selectedRole.value = role;
    editRoleForm.name = role.name;
    editRoleForm.clearErrors();
    showEditModal.value = true;
};

const updateRole = () => {
    if (!selectedRole.value) return;
    editRoleForm.put(`/admin/roles/${selectedRole.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedRole.value = null;
        },
    });
};

const openDeleteModal = (role) => {
    if (isProtectedRole(role.name)) return;
    selectedRole.value = role;
    showDeleteModal.value = true;
};

const deleteRole = () => {
    if (!selectedRole.value) return;
    router.delete(`/admin/roles/${selectedRole.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedRole.value = null;
        },
    });
};

const isProtectedRole = (roleName) => ['super-admin', 'admin', 'superadmin'].includes((roleName || '').toLowerCase());
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Roles & Permissions</h1>
                    <p class="mt-2 text-gray-600">
                        Manage user roles and permissions with drag & drop
                    </p>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    New Role
                </button>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700"
                            >Search Roles</label
                        >
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search by role name..."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        />
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Permissions Panel -->
                <div class="lg:col-span-1">
                    <div class="rounded-lg border border-gray-200 bg-white shadow">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Available Permissions</h3>
                            <p class="text-sm text-gray-600">Drag permissions to roles</p>
                            <input
                                v-model="permissionSearch"
                                type="text"
                                placeholder="Search permissions..."
                                class="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            />
                        </div>
                        <div class="max-h-96 overflow-y-auto p-4">
                            <div
                                v-for="(groupPermissions, groupName) in filteredPermissionGroups"
                                :key="groupName"
                                class="mb-4"
                            >
                                <h4 class="mb-2 text-sm font-medium text-gray-900 capitalize">
                                    {{ groupName.replace('_', ' ') }}
                                </h4>
                                <div class="space-y-1">
                                    <div
                                        v-for="permission in groupPermissions"
                                        :key="permission.id"
                                        draggable="true"
                                        @dragstart="startDrag($event, permission)"
                                        class="cursor-move rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm transition-colors hover:bg-gray-100"
                                    >
                                        {{ permission.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roles Panel -->
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                        <!-- Header -->
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">
                                    Showing {{ roles.data.length }} of {{ roles.total }} roles
                                </span>
                            </div>
                        </div>

                        <!-- Roles -->
                        <div v-if="roles.data.length > 0" class="divide-y divide-gray-200">
                            <div
                                v-for="role in roles.data"
                                :key="role.id"
                                class="p-6 transition-colors"
                                :class="
                                    targetRole?.id === role.id ? 'bg-blue-50' : 'hover:bg-gray-50'
                                "
                                @dragover="onDragOver($event, role)"
                                @dragleave="onDragLeave"
                                @drop="onDrop($event, role)"
                            >
                                <div class="mb-4 flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-blue-500 to-purple-600"
                                        >
                                            <span class="text-sm font-bold text-white">
                                                {{ role.name.substring(0, 2).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-900">
                                                {{ role.name }}
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                Created: {{ formatDate(role.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span
                                            class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800"
                                        >
                                            {{ role.permissions?.length || 0 }} permissions
                                        </span>
                                        <div class="mt-2 flex justify-end gap-2">
                                            <button
                                                @click.stop="openEditModal(role)"
                                                class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                @click.stop="openDeleteModal(role)"
                                                :disabled="isProtectedRole(role.name)"
                                                class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Permissions List -->
                                <div>
                                    <div class="mb-2 flex items-center justify-between">
                                        <h4 class="text-sm font-medium text-gray-700">
                                            Assigned Permissions:
                                        </h4>
                                        <span class="text-xs text-gray-500">Click to remove</span>
                                    </div>

                                    <div
                                        v-if="role.permissions && role.permissions.length > 0"
                                        class="flex flex-wrap gap-2"
                                    >
                                        <span
                                            v-for="permission in role.permissions"
                                            :key="permission.id"
                                            @click="removePermission(role, permission)"
                                            class="inline-flex cursor-pointer items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 transition-colors hover:bg-green-200"
                                            title="Click to remove"
                                        >
                                            {{ permission.name }}
                                            <svg
                                                class="ml-1 h-3 w-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>
                                        </span>
                                    </div>
                                    <div
                                        v-else
                                        class="rounded-lg border-2 border-dashed border-gray-300 py-4 text-center"
                                    >
                                        <p class="text-sm text-gray-500">
                                            Drag permissions here or click to assign
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="py-12 text-center">
                            <div class="mb-4 flex justify-center">
                                <svg class="h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h3 class="mb-2 text-lg font-medium text-gray-900">No roles found</h3>
                            <p class="text-gray-500">Create your first role to get started.</p>
                        </div>

                        <div v-if="roles.last_page > 1" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <Link
                                    v-for="page in (roles.links || []).slice(1, -1)"
                                    :key="page.label"
                                    :href="page.url || '#'"
                                    :class="[
                                        'rounded-md border px-3 py-1.5 text-sm',
                                        page.active
                                            ? 'border-blue-600 bg-blue-600 text-white'
                                            : page.url
                                                ? 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                                : 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-400'
                                    ]"
                                >
                                    <span v-html="page.label" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Role Modal -->
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/20 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Create New Role</h3>
                </div>
                <div class="p-6">
                    <form @submit.prevent="createRole">
                        <div class="space-y-4">
                            <div>
                                <label
                                    for="roleName"
                                    class="mb-2 block text-sm font-medium text-gray-700"
                                    >Role Name</label
                                >
                                <input
                                    v-model="newRoleForm.name"
                                    type="text"
                                    id="roleName"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                    placeholder="Enter role name..."
                                    required
                                />
                                <div
                                    v-if="newRoleForm.errors.name"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ newRoleForm.errors.name }}
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="newRoleForm.processing"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                            >
                                Create Role
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Role Modal -->
        <div
            v-if="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/20 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Role</h3>
                </div>
                <div class="p-6">
                    <form @submit.prevent="updateRole">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Role Name</label>
                            <input
                                v-model="editRoleForm.name"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                required
                            />
                            <div v-if="editRoleForm.errors.name" class="mt-1 text-sm text-red-600">
                                {{ editRoleForm.errors.name }}
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showEditModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="editRoleForm.processing"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Role Modal -->
        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/20 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Delete Role</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-700">
                        Delete role <span class="font-semibold">{{ selectedRole?.name }}</span>?
                        This action cannot be undone.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showDeleteModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="deleteRole"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
