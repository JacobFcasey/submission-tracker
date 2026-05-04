<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import {
    PencilIcon,
    TrashIcon,
    PlusIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ArrowPathIcon,
    BuildingLibraryIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    municipalities: Object,
    filters: Object,
});

const form = reactive({
    search: props.filters.search || '',
    per_page: props.filters.per_page || 10,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedMunicipality = ref(null);

const createForm = reactive({
    name: '',
    code: '',
    region: '',
    province: '',
    errors: {},
});

const editForm = reactive({
    name: '',
    code: '',
    region: '',
    province: '',
    errors: {},
});

const search = () => {
    router.get(
        '/admin/municipalities',
        {
            search: form.search,
            per_page: form.per_page,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const resetSearch = () => {
    form.search = '';
    form.per_page = 10;
    router.get(
        '/admin/municipalities',
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
};

const openCreateModal = () => {
    createForm.name = '';
    createForm.code = '';
    createForm.region = '';
    createForm.province = '';
    createForm.errors = {};
    showCreateModal.value = true;
};

const createMunicipality = () => {
    router.post('/admin/municipalities', createForm, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.errors = {};
        },
        onError: (errors) => {
            createForm.errors = errors;
        },
    });
};

const openEditModal = (municipality) => {
    selectedMunicipality.value = municipality;
    editForm.name = municipality.name;
    editForm.code = municipality.code || '';
    editForm.region = municipality.region || '';
    editForm.province = municipality.province || '';
    editForm.errors = {};
    showEditModal.value = true;
};

const updateMunicipality = () => {
    router.put(`/admin/municipalities/${selectedMunicipality.value.id}`, editForm, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedMunicipality.value = null;
            editForm.errors = {};
        },
        onError: (errors) => {
            editForm.errors = errors;
        },
    });
};

const openDeleteModal = (municipality) => {
    selectedMunicipality.value = municipality;
    showDeleteModal.value = true;
};

const deleteMunicipality = () => {
    router.delete(`/admin/municipalities/${selectedMunicipality.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedMunicipality.value = null;
        },
    });
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Municipalities Management</h1>
                    <p class="mt-2 text-gray-600">Manage all municipalities in the system</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-white transition-colors hover:bg-green-700"
                >
                    <PlusIcon class="mr-2 h-5 w-5" />
                    Add Municipality
                </button>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Search</label>
                        <div class="relative">
                            <input
                                type="text"
                                v-model="form.search"
                                @keyup.enter="search"
                                placeholder="Search municipalities..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 pl-10 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                            />
                            <MagnifyingGlassIcon
                                class="absolute top-2.5 left-3 h-5 w-5 text-gray-400"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700"
                            >Items per page</label
                        >
                        <select
                            v-model="form.per_page"
                            @change="search"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                        >
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-3">
                        <button
                            @click="search"
                            class="inline-flex flex-1 items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-white transition-colors hover:bg-green-700"
                        >
                            <FunnelIcon class="mr-2 h-4 w-4" />
                            Filter
                        </button>
                        <button
                            @click="resetSearch"
                            class="inline-flex items-center justify-center rounded-lg bg-gray-600 px-4 py-2 text-white transition-colors hover:bg-gray-700"
                        >
                            <ArrowPathIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Municipalities List -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <!-- Header -->
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">
                            Showing {{ municipalities.from }} to {{ municipalities.to }} of
                            {{ municipalities.total }} municipalities
                        </span>
                        <div class="text-sm text-gray-500">
                            Page {{ municipalities.current_page }} of {{ municipalities.last_page }}
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Municipality
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Code
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Region
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Province
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Created
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="municipality in municipalities.data"
                                :key="municipality.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-green-100"
                                        >
                                            <BuildingLibraryIcon class="h-5 w-5 text-green-600" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ municipality.name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                ID: {{ municipality.id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800"
                                        v-if="municipality.code"
                                    >
                                        {{ municipality.code }}
                                    </span>
                                    <span class="text-sm text-gray-400" v-else>No code</span>
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900">
                                    {{ municipality.region || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900">
                                    {{ municipality.province || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                    {{ new Date(municipality.created_at).toLocaleDateString() }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            @click="openEditModal(municipality)"
                                            class="rounded-lg p-2 text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-900"
                                            title="Edit municipality"
                                        >
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openDeleteModal(municipality)"
                                            class="rounded-lg p-2 text-red-600 transition-colors hover:bg-red-50 hover:text-red-900"
                                            title="Delete municipality"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="municipalities.data.length === 0" class="py-12 text-center">
                    <div class="mb-4 text-6xl">🏛️</div>
                    <h3 class="mb-2 text-lg font-medium text-gray-900">No municipalities found</h3>
                    <p class="text-gray-500">
                        Try adjusting your search criteria or add a new municipality.
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="municipalities.last_page > 1"
                    class="border-t border-gray-200 bg-gray-50 px-6 py-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ municipalities.from }} to {{ municipalities.to }} of
                            {{ municipalities.total }} results
                        </div>
                        <div class="flex space-x-2">
                            <Link
                                v-if="municipalities.prev_page_url"
                                :href="municipalities.prev_page_url"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="municipalities.next_page_url"
                                :href="municipalities.next_page_url"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div
            v-if="showCreateModal"
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black p-4"
        >
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Add New Municipality</h3>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Municipality Name *</label
                        >
                        <input
                            type="text"
                            v-model="createForm.name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                            placeholder="Enter municipality name"
                        />
                        <div v-if="createForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ createForm.errors.name }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Municipality Code</label
                        >
                        <input
                            type="text"
                            v-model="createForm.code"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                            placeholder="Enter municipality code"
                        />
                        <div v-if="createForm.errors.code" class="mt-1 text-xs text-red-500">
                            {{ createForm.errors.code }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Region</label>
                        <input
                            type="text"
                            v-model="createForm.region"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                            placeholder="Enter region"
                        />
                        <div v-if="createForm.errors.region" class="mt-1 text-xs text-red-500">
                            {{ createForm.errors.region }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Province</label>
                        <input
                            type="text"
                            v-model="createForm.province"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                            placeholder="Enter province"
                        />
                        <div v-if="createForm.errors.province" class="mt-1 text-xs text-red-500">
                            {{ createForm.errors.province }}
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 border-t border-gray-200 px-6 py-4">
                    <button
                        @click="showCreateModal = false"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="createMunicipality"
                        class="rounded-lg bg-green-600 px-4 py-2 text-white transition-colors hover:bg-green-700"
                    >
                        Create Municipality
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div
            v-if="showEditModal"
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black p-4"
        >
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Municipality</h3>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Municipality Name *</label
                        >
                        <input
                            type="text"
                            v-model="editForm.name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                        />
                        <div v-if="editForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.name }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Municipality Code</label
                        >
                        <input
                            type="text"
                            v-model="editForm.code"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                        />
                        <div v-if="editForm.errors.code" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.code }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Region</label>
                        <input
                            type="text"
                            v-model="editForm.region"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                        />
                        <div v-if="editForm.errors.region" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.region }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Province</label>
                        <input
                            type="text"
                            v-model="editForm.province"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200"
                        />
                        <div v-if="editForm.errors.province" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.province }}
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 border-t border-gray-200 px-6 py-4">
                    <button
                        @click="showEditModal = false"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="updateMunicipality"
                        class="rounded-lg bg-green-600 px-4 py-2 text-white transition-colors hover:bg-green-700"
                    >
                        Update Municipality
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div
            v-if="showDeleteModal"
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black p-4"
        >
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">Delete Municipality</h3>
                </div>
                <div class="px-6 py-4">
                    <p class="text-gray-700">
                        Are you sure you want to delete
                        <strong>{{ selectedMunicipality?.name }}</strong
                        >? This action cannot be undone.
                    </p>
                </div>
                <div class="flex justify-end space-x-3 border-t border-gray-200 px-6 py-4">
                    <button
                        @click="showDeleteModal = false"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteMunicipality"
                        class="rounded-lg bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
                    >
                        Delete Municipality
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
