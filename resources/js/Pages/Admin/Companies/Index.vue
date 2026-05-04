<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, reactive, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import {
    PencilIcon,
    TrashIcon,
    PlusIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    companies: Object,
    filters: Object,
    municipalities: {
        type: Array,
        default: () => [],
    },
});

const form = reactive({
    search: props.filters.search || '',
    per_page: props.filters.per_page || 10,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedCompany = ref(null);

const createForm = reactive({
    name: '',
    registration_number: '',
    municipality_id: '',
    status: 'active',
    contact_email: '',
    errors: {},
});

const editForm = reactive({
    name: '',
    registration_number: '',
    municipality_id: '',
    status: 'active',
    contact_email: '',
    errors: {},
});

const performSearch = debounce(() => {
    router.get(
        '/admin/companies',
        {
            search: form.search,
            per_page: form.per_page,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}, 300);

watch(() => form.search, () => {
    performSearch();
});

const search = () => {
    router.get(
        '/admin/companies',
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
        '/admin/companies',
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
};

const openCreateModal = () => {
    createForm.name = '';
    createForm.registration_number = '';
    createForm.municipality_id = '';
    createForm.status = 'active';
    createForm.contact_email = '';
    createForm.errors = {};
    showCreateModal.value = true;
};

const createCompany = () => {
    router.post('/admin/companies', createForm, {
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

const openEditModal = (company) => {
    selectedCompany.value = company;
    editForm.name = company.name;
    editForm.registration_number = company.registration_number || '';
    editForm.municipality_id = company.municipality_id || '';
    editForm.status = company.status || 'active';
    editForm.contact_email = company.contact_email || '';
    editForm.errors = {};
    showEditModal.value = true;
};

const updateCompany = () => {
    router.put(`/admin/companies/${selectedCompany.value.id}`, editForm, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedCompany.value = null;
            editForm.errors = {};
        },
        onError: (errors) => {
            editForm.errors = errors;
        },
    });
};

const openDeleteModal = (company) => {
    selectedCompany.value = company;
    showDeleteModal.value = true;
};

const deleteCompany = () => {
    router.delete(`/admin/companies/${selectedCompany.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedCompany.value = null;
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
                    <h1 class="text-3xl font-bold text-gray-900">Companies Management</h1>
                    <p class="mt-2 text-gray-600">Manage all companies in the system</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                >
                    <PlusIcon class="mr-2 h-5 w-5" />
                    Add Company
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
                                placeholder="Search companies..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 pl-10 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
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
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
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
                            class="inline-flex flex-1 items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
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

            <!-- Companies List -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <!-- Header -->
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">
                            Showing {{ companies.from }} to {{ companies.to }} of
                            {{ companies.total }} companies
                        </span>
                        <div class="text-sm text-gray-500">
                            Page {{ companies.current_page }} of {{ companies.last_page }}
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
                                    Company
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Municipality
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Contact Email
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                >
                                    Status
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
                                v-for="company in companies.data"
                                :key="company.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100"
                                        >
                                            <span class="font-semibold text-blue-600">{{
                                                company.name.charAt(0)
                                            }}</span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ company.name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Reg#: {{ company.registration_number || 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ company.municipality?.name || 'N/A' }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ company.municipality?.code || '' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" v-if="company.contact_email">
                                        {{ company.contact_email }}
                                    </div>
                                    <div class="text-sm text-gray-400" v-else>No email</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="company.status === 'active'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-800'"
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    >
                                        {{ company.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500">
                                    {{ new Date(company.created_at).toLocaleDateString() }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            @click="openEditModal(company)"
                                            class="rounded-lg p-2 text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-900"
                                            title="Edit company"
                                        >
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openDeleteModal(company)"
                                            class="rounded-lg p-2 text-red-600 transition-colors hover:bg-red-50 hover:text-red-900"
                                            title="Delete company"
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
                <div v-if="companies.data.length === 0" class="py-12 text-center">
                    <div class="mb-4 text-6xl">🏢</div>
                    <h3 class="mb-2 text-lg font-medium text-gray-900">No companies found</h3>
                    <p class="text-gray-500">
                        Try adjusting your search criteria or add a new company.
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="companies.last_page > 1"
                    class="border-t border-gray-200 bg-gray-50 px-6 py-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ companies.from }} to {{ companies.to }} of
                            {{ companies.total }} results
                        </div>
                        <div class="flex space-x-2">
                            <Link
                                v-if="companies.prev_page_url"
                                :href="companies.prev_page_url"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="companies.next_page_url"
                                :href="companies.next_page_url"
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
                    <h3 class="text-lg font-medium text-gray-900">Add New Company</h3>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Company Name *</label
                        >
                        <input
                            type="text"
                            v-model="createForm.name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            placeholder="Enter company name"
                        />
                        <div v-if="createForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ createForm.errors.name }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Registration Number</label
                        >
                        <input
                            type="text"
                            v-model="createForm.registration_number"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            placeholder="Enter registration number"
                        />
                        <div
                            v-if="createForm.errors.registration_number"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ createForm.errors.registration_number }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Municipality *</label
                        >
                        <select
                            v-model="createForm.municipality_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                            <option value="" disabled>Select municipality</option>
                            <option
                                v-for="municipality in municipalities"
                                :key="municipality.id"
                                :value="municipality.id"
                            >
                                {{ municipality.name }} ({{ municipality.code }})
                            </option>
                        </select>
                        <div
                            v-if="createForm.errors.municipality_id"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ createForm.errors.municipality_id }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status *</label>
                        <select
                            v-model="createForm.status"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                        <div v-if="createForm.errors.status" class="mt-1 text-xs text-red-500">
                            {{ createForm.errors.status }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Registration Number</label
                        >
                        <input
                            type="text"
                            v-model="editForm.registration_number"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        />
                        <div v-if="editForm.errors.registration_number" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.registration_number }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Municipality *</label
                        >
                        <select
                            v-model="editForm.municipality_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                            <option value="" disabled>Select municipality</option>
                            <option
                                v-for="municipality in municipalities"
                                :key="municipality.id"
                                :value="municipality.id"
                            >
                                {{ municipality.name }} ({{ municipality.code }})
                            </option>
                        </select>
                        <div v-if="editForm.errors.municipality_id" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.municipality_id }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status *</label>
                        <select
                            v-model="editForm.status"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                        <div v-if="editForm.errors.status" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.status }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Contact Email</label
                        >
                        <input
                            type="email"
                            v-model="createForm.contact_email"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            placeholder="Enter contact email"
                        />
                        <div
                            v-if="createForm.errors.contact_email"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ createForm.errors.contact_email }}
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
                        @click="createCompany"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                    >
                        Create Company
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
                    <h3 class="text-lg font-medium text-gray-900">Edit Company</h3>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Company Name *</label
                        >
                        <input
                            type="text"
                            v-model="editForm.name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        />
                        <div v-if="editForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.name }}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700"
                            >Contact Email</label
                        >
                        <input
                            type="email"
                            v-model="editForm.contact_email"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        />
                        <div v-if="editForm.errors.contact_email" class="mt-1 text-xs text-red-500">
                            {{ editForm.errors.contact_email }}
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
                        @click="updateCompany"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                    >
                        Update Company
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
                    <h3 class="text-lg font-medium text-gray-900">Delete Company</h3>
                </div>
                <div class="px-6 py-4">
                    <p class="text-gray-700">
                        Are you sure you want to delete <strong>{{ selectedCompany?.name }}</strong
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
                        @click="deleteCompany"
                        class="rounded-lg bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
                    >
                        Delete Company
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
