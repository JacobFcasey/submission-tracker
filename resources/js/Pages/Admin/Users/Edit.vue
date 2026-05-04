<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3'; // Add this import

const page = usePage();

const props = defineProps({
    user: Object,
    roles: Array,
    municipalities: Array,
    companies: Array,
    userRoles: Array,
    userAssignments: Array,
    existingDeadlines: Object,
});

const userForm = useForm({
    name: props.user.name,
    email: props.user.email,
    employee_number: props.user.employee_number,
    roles: props.userRoles,
    password: '',
    password_confirmation: '',
});

const assignmentForm = useForm({
    municipality_id: '',
    company_ids: [],
    deadline_date: '',
    notes: '',
});

// State for UI
const selectedMunicipality = ref(null);
const availableCompanies = ref([]);
const allCompaniesSelected = ref(false);
const showSuccess = ref(false);
const successMessage = ref('');
const municipalityDeadlines = ref([]);
const showError = ref(false);
const errorMessage = ref('');
const assignmentTab = ref('create');
const assignmentSearch = ref('');
const assignmentPage = ref(1);
const assignmentPerPage = ref(8);

const filteredAssignments = computed(() => {
    const q = assignmentSearch.value.trim().toLowerCase();
    const rows = props.userAssignments || [];
    if (!q) return rows;

    return rows.filter((assignment) => {
        const municipality = assignment?.municipality?.name || '';
        const company = assignment?.company?.name || '';
        const notes = assignment?.notes || '';
        return [municipality, company, notes].some((value) =>
            value.toLowerCase().includes(q)
        );
    });
});

const totalAssignmentPages = computed(() => {
    return Math.max(1, Math.ceil(filteredAssignments.value.length / assignmentPerPage.value));
});

const pagedAssignments = computed(() => {
    const start = (assignmentPage.value - 1) * assignmentPerPage.value;
    return filteredAssignments.value.slice(start, start + assignmentPerPage.value);
});

watch([assignmentSearch, assignmentPerPage], () => {
    assignmentPage.value = 1;
});

watch(filteredAssignments, () => {
    if (assignmentPage.value > totalAssignmentPages.value) {
        assignmentPage.value = totalAssignmentPages.value;
    }
});

// Get existing deadlines for the selected municipality
const getMunicipalityDeadlines = (municipalityId) => {
    if (!municipalityId) return [];

    const deadlines = props.existingDeadlines?.[municipalityId] || [];
    municipalityDeadlines.value = deadlines;

    // Set the earliest deadline date as default
    if (deadlines.length > 0) {
        const earliestDeadline = deadlines.sort((a, b) => new Date(a.deadline_date) - new Date(b.deadline_date))[0];
        assignmentForm.deadline_date = earliestDeadline.deadline_date;
    } else {
        assignmentForm.deadline_date = '';
    }

    return deadlines;
};

// Filter companies by municipality
const filterCompaniesByMunicipality = (municipalityId) => {
    if (!municipalityId) {
        availableCompanies.value = [];
        return;
    }

    selectedMunicipality.value = props.municipalities.find(m => m.id === municipalityId);
    availableCompanies.value = props.companies.filter(company =>
        company.municipality_id === municipalityId
    );

    // Get deadlines for this municipality
    getMunicipalityDeadlines(municipalityId);

    // Reset company selection
    assignmentForm.company_ids = [];
    allCompaniesSelected.value = false;
};

// Toggle all companies selection
const toggleAllCompanies = () => {
    if (allCompaniesSelected.value) {
        assignmentForm.company_ids = [];
    } else {
        // Only select companies that aren't already assigned
        const unassignedCompanies = availableCompanies.value.filter(
            company => !isCompanyAlreadyAssigned(company.id)
        );
        assignmentForm.company_ids = unassignedCompanies.map(company => company.id);
    }
    allCompaniesSelected.value = !allCompaniesSelected.value;
};

// Watch for municipality changes
watch(() => assignmentForm.municipality_id, (newVal) => {
    if (newVal) {
        filterCompaniesByMunicipality(newVal);
    } else {
        availableCompanies.value = [];
        selectedMunicipality.value = null;
        municipalityDeadlines.value = [];
        assignmentForm.deadline_date = '';
        assignmentForm.company_ids = [];
        allCompaniesSelected.value = false;
    }
});

// Watch company selection
watch(() => assignmentForm.company_ids, (newVal) => {
    const totalUnassigned = availableCompanies.value.filter(
        company => !isCompanyAlreadyAssigned(company.id)
    ).length;

    allCompaniesSelected.value = newVal.length === totalUnassigned && totalUnassigned > 0;
}, { deep: true });

// Check if company is already assigned to user for selected municipality
const isCompanyAlreadyAssigned = (companyId) => {
    if (!selectedMunicipality.value) return false;

    return props.userAssignments.some(assignment =>
        assignment.municipality_id == selectedMunicipality.value.id &&
        assignment.company_id == companyId
    );
};

// Get companies that are already assigned
const getAlreadyAssignedCompanies = computed(() => {
    if (!selectedMunicipality.value) return [];

    return props.userAssignments
        .filter(assignment => assignment.municipality_id == selectedMunicipality.value.id)
        .map(assignment => assignment.company_id);
});

function updateUser() {
    userForm.put(`/admin/users/${props.user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess.value = true;
            successMessage.value = 'User updated successfully';
            setTimeout(() => {
                showSuccess.value = false;
            }, 3000);
        },
    });
}

function addAssignment() {
    // Clear previous errors
    showError.value = false;
    errorMessage.value = '';

    // Validation
    if (!assignmentForm.municipality_id) {
        showError.value = true;
        errorMessage.value = 'Please select a municipality';
        return;
    }

    if (assignmentForm.company_ids.length === 0) {
        showError.value = true;
        errorMessage.value = 'Please select at least one company';
        return;
    }

    // Check if municipality has existing deadlines
    if (municipalityDeadlines.value.length === 0 && !assignmentForm.deadline_date) {
        showError.value = true;
        errorMessage.value = 'This municipality has no existing deadlines. Please enter a deadline date.';
        return;
    }

    // Filter out companies that are already assigned
    const alreadyAssigned = getAlreadyAssignedCompanies.value;
    const newCompanyIds = assignmentForm.company_ids.filter(id => !alreadyAssigned.includes(id));

    if (newCompanyIds.length === 0) {
        showError.value = true;
        errorMessage.value = 'All selected companies are already assigned to this user';
        return;
    }

    // Update the form with filtered company IDs
    const originalCompanyIds = [...assignmentForm.company_ids];
    assignmentForm.company_ids = newCompanyIds;

    assignmentForm.post(`/admin/users/${props.user.id}/assignments`, {
        preserveScroll: true,
        onSuccess: () => {
            // Reset form
            assignmentForm.municipality_id = '';
            assignmentForm.company_ids = [];
            assignmentForm.deadline_date = '';
            assignmentForm.notes = '';
            availableCompanies.value = [];
            selectedMunicipality.value = null;
            municipalityDeadlines.value = [];
            allCompaniesSelected.value = false;

            // Show success
            showSuccess.value = true;
            successMessage.value = `${newCompanyIds.length} assignment(s) created successfully`;

            // Reload the page to refresh assignments list
            router.reload({
                only: ['userAssignments'],
                preserveScroll: true,
                onFinish: () => {
                    // Reset success after reload
                    setTimeout(() => {
                        showSuccess.value = false;
                    }, 3000);
                }
            });
        },
        onError: (errors) => {
            // Restore original company IDs
            assignmentForm.company_ids = originalCompanyIds;

            if (errors.deadline_date) {
                showError.value = true;
                errorMessage.value = errors.deadline_date;
            } else if (errors.message) {
                showError.value = true;
                errorMessage.value = errors.message;
            } else {
                showError.value = true;
                errorMessage.value = 'Failed to create assignments. Please try again.';
            }
        }
    });
}

function removeAssignment(assignmentId) {
    if (confirm('Are you sure you want to remove this assignment?')) {
        router.delete(`/admin/users/${props.user.id}/assignments/${assignmentId}`, {
            preserveScroll: true,
            onSuccess: () => {
                showSuccess.value = true;
                successMessage.value = 'Assignment removed successfully';
                setTimeout(() => {
                    showSuccess.value = false;
                }, 3000);

                // Reload assignments
                router.reload({
                    only: ['userAssignments'],
                    preserveScroll: true
                });
            },
        });
    }
}

// Format date for display
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

// Format time ago
const formatTimeAgo = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / (1000 * 60));

    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''} ago`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;

    const diffInDays = Math.floor(diffInHours / 24);
    return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
};

// Listen for page flash messages
onMounted(() => {
    // Check for flash messages from Inertia
    if (page.props.flash?.success) {
        showSuccess.value = true;
        successMessage.value = page.props.flash.success;
        setTimeout(() => {
            showSuccess.value = false;
        }, 3000);
    }

    if (page.props.flash?.error) {
        showError.value = true;
        errorMessage.value = page.props.flash.error;
        setTimeout(() => {
            showError.value = false;
        }, 5000);
    }
});
</script>

<template>
    <AppLayout title="Edit User">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <Link href="/admin/users" class="mr-4 text-gray-600 hover:text-gray-900">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="text-xl leading-tight font-semibold text-gray-800">
                        Edit User: {{ user.name }}
                    </h2>
                </div>
            </div>
        </template>

        <!-- Success Notification -->
        <div v-if="showSuccess" class="fixed top-4 right-4 z-50 animate-fade-in">
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-lg max-w-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{{ successMessage }}</p>
                    </div>
                    <button @click="showSuccess = false" class="ml-auto pl-3">
                        <svg class="h-4 w-4 text-green-400 hover:text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Error Notification -->
        <div v-if="showError" class="fixed top-4 right-4 z-50 animate-fade-in">
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-lg max-w-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ errorMessage }}</p>
                    </div>
                    <button @click="showError = false" class="ml-auto pl-3">
                        <svg class="h-4 w-4 text-red-400 hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Link href="/admin/users" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Users List
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- User Information -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 bg-white p-6">
                            <h3 class="mb-4 text-lg font-medium">User Information</h3>

                            <form @submit.prevent="updateUser">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700"
                                        >Name</label
                                        >
                                        <input
                                            v-model="userForm.name"
                                            type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div
                                            v-if="userForm.errors.name"
                                            class="text-sm text-red-600"
                                        >
                                            {{ userForm.errors.name }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700"
                                        >Email</label
                                        >
                                        <input
                                            v-model="userForm.email"
                                            type="email"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div
                                            v-if="userForm.errors.email"
                                            class="text-sm text-red-600"
                                        >
                                            {{ userForm.errors.email }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700"
                                        >Employee Number</label
                                        >
                                        <input
                                            v-model="userForm.employee_number"
                                            type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div
                                            v-if="userForm.errors.employee_number"
                                            class="text-sm text-red-600"
                                        >
                                            {{ userForm.errors.employee_number }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700"
                                        >Roles</label
                                        >
                                        <select
                                            v-model="userForm.roles"
                                            multiple
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option v-for="role in roles" :key="role" :value="role">
                                                {{ role }}
                                            </option>
                                        </select>
                                        <div
                                            v-if="userForm.errors.roles"
                                            class="text-sm text-red-600"
                                        >
                                            {{ userForm.errors.roles }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700"
                                        >New Password</label
                                        >
                                        <input
                                            v-model="userForm.password"
                                            type="password"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div
                                            v-if="userForm.errors.password"
                                            class="text-sm text-red-600"
                                        >
                                            {{ userForm.errors.password }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700"
                                        >Confirm Password</label
                                        >
                                        <input
                                            v-model="userForm.password_confirmation"
                                            type="password"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>

                                    <div>
                                        <button
                                            type="submit"
                                            :disabled="userForm.processing"
                                            :class="[
                                                'rounded-md px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-offset-2',
                                                userForm.processing
                                                    ? 'bg-indigo-400 cursor-not-allowed'
                                                    : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500'
                                            ]"
                                        >
                                            <span v-if="userForm.processing">Updating...</span>
                                            <span v-else>Update User</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Assignments -->
                    <div class="space-y-6">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="border-b border-gray-200 bg-white p-4">
                                <div class="flex gap-2">
                                    <button
                                        @click="assignmentTab = 'create'"
                                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                                        :class="assignmentTab === 'create' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100'"
                                    >
                                        Add Assignment(s)
                                    </button>
                                    <button
                                        @click="assignmentTab = 'current'"
                                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                                        :class="assignmentTab === 'current' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100'"
                                    >
                                        Current Assignments
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Add Assignment -->
                        <div v-if="assignmentTab === 'create'" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="border-b border-gray-200 bg-white p-6">
                                <h3 class="mb-4 text-lg font-medium">Add Assignment(s)</h3>

                                <form @submit.prevent="addAssignment">
                                    <div class="grid grid-cols-1 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700"
                                            >Municipality *</label
                                            >
                                            <select
                                                v-model="assignmentForm.municipality_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                required
                                                :disabled="assignmentForm.processing"
                                            >
                                                <option value="">Select Municipality</option>
                                                <option
                                                    v-for="municipality in municipalities"
                                                    :key="municipality.id"
                                                    :value="municipality.id"
                                                >
                                                    {{ municipality.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Municipality Deadlines Info -->
                                        <div v-if="municipalityDeadlines.length > 0" class="rounded-lg bg-blue-50 p-3">
                                            <h4 class="text-sm font-medium text-blue-800 mb-1">
                                                Existing Deadlines for {{ selectedMunicipality?.name }}
                                            </h4>
                                            <div class="space-y-1">
                                                <div v-for="deadline in municipalityDeadlines" :key="deadline.id"
                                                     class="text-xs text-blue-700">
                                                    • {{ formatDate(deadline.deadline_date) }}
                                                    <span v-if="deadline.notes" class="text-blue-600"> - {{ deadline.notes }}</span>
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Deadline Date Input (only if no existing deadlines) -->
                                        <div v-if="municipalityDeadlines.length === 0">
                                            <label class="block text-sm font-medium text-gray-700"
                                            >Deadline Date *</label
                                            >
                                            <input
                                                v-model="assignmentForm.deadline_date"
                                                type="date"
                                                :min="new Date().toISOString().split('T')[0]"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                required
                                                :disabled="assignmentForm.processing"
                                            />
                                            <p class="mt-1 text-xs text-gray-500">
                                                * Required for municipalities without existing deadlines
                                            </p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700"
                                            >Select Companies *</label
                                            >

                                            <!-- Select All Checkbox -->
                                            <div v-if="availableCompanies.length > 0" class="mb-2">
                                                <label class="inline-flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        v-model="allCompaniesSelected"
                                                        @change="toggleAllCompanies"
                                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        :disabled="assignmentForm.processing"
                                                    />
                                                    <span class="ml-2 text-sm text-gray-700">Select all unassigned companies</span>
                                                    <span class="ml-auto text-sm text-gray-500">
                                                        {{ assignmentForm.company_ids.length }} of {{ availableCompanies.filter(c => !isCompanyAlreadyAssigned(c.id)).length }} selected
                                                    </span>
                                                </label>
                                            </div>

                                            <!-- Companies List -->
                                            <div v-if="availableCompanies.length > 0"
                                                 class="max-h-60 overflow-y-auto rounded-md border border-gray-300 p-2">
                                                <div class="space-y-2">
                                                    <div v-for="company in availableCompanies"
                                                         :key="company.id"
                                                         :class="[
                                                             'flex items-center p-2 rounded transition-colors',
                                                             isCompanyAlreadyAssigned(company.id)
                                                                ? 'bg-yellow-50 cursor-not-allowed'
                                                                : 'hover:bg-gray-50'
                                                         ]">
                                                        <input
                                                            type="checkbox"
                                                            :value="company.id"
                                                            v-model="assignmentForm.company_ids"
                                                            :disabled="isCompanyAlreadyAssigned(company.id) || assignmentForm.processing"
                                                            :class="[
                                                                'h-4 w-4 rounded focus:ring-indigo-500',
                                                                isCompanyAlreadyAssigned(company.id)
                                                                    ? 'border-yellow-300 text-yellow-600'
                                                                    : 'border-gray-300 text-indigo-600'
                                                            ]"
                                                        />
                                                        <span :class="[
                                                            'ml-3 text-sm',
                                                            isCompanyAlreadyAssigned(company.id)
                                                                ? 'text-yellow-700'
                                                                : 'text-gray-700'
                                                        ]">
                                                            {{ company.name }}
                                                            <span v-if="company.code" class="text-gray-500"> ({{ company.code }})</span>
                                                            <span v-if="isCompanyAlreadyAssigned(company.id)"
                                                                  class="ml-2 text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full">
                                                                Already assigned
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-else-if="assignmentForm.municipality_id"
                                                 class="rounded-md border border-gray-300 p-4 text-center">
                                                <p class="text-sm text-gray-500">No companies available for this municipality</p>
                                            </div>

                                            <div v-else
                                                 class="rounded-md border border-gray-300 p-4 text-center">
                                                <p class="text-sm text-gray-500">Select a municipality to view companies</p>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700"
                                            >Notes (Optional)</label
                                            >
                                            <textarea
                                                v-model="assignmentForm.notes"
                                                rows="2"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Add any notes about this assignment..."
                                                :disabled="assignmentForm.processing"
                                            ></textarea>
                                        </div>

                                        <div class="pt-2">
                                            <button
                                                type="submit"
                                                :disabled="assignmentForm.processing || !assignmentForm.municipality_id || assignmentForm.company_ids.length === 0"
                                                :class="[
                                                    'w-full rounded-md px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors',
                                                    assignmentForm.processing || !assignmentForm.municipality_id || assignmentForm.company_ids.length === 0
                                                        ? 'bg-green-400 cursor-not-allowed'
                                                        : 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
                                                ]"
                                            >
                                                <span v-if="assignmentForm.processing">
                                                    <svg class="animate-spin h-4 w-4 inline mr-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Creating...
                                                </span>
                                                <span v-else>
                                                    Add {{ assignmentForm.company_ids.length }} Assignment(s)
                                                </span>
                                            </button>
                                            <p v-if="getAlreadyAssignedCompanies.length > 0" class="mt-2 text-xs text-yellow-600">
                                                Note: {{ getAlreadyAssignedCompanies.length }} company(s) already assigned to this user in this municipality
                                            </p>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Current Assignments -->
                        <div v-if="assignmentTab === 'current'" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="border-b border-gray-200 bg-white p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-medium">Current Assignments</h3>
                                    <span class="text-sm text-gray-500">{{ filteredAssignments.length }} total</span>
                                </div>

                                <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <input
                                        v-model="assignmentSearch"
                                        type="text"
                                        placeholder="Search municipality/company/notes..."
                                        class="sm:col-span-2 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <select
                                        v-model.number="assignmentPerPage"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option :value="8">8 per page</option>
                                        <option :value="15">15 per page</option>
                                        <option :value="25">25 per page</option>
                                    </select>
                                </div>

                                <div
                                    v-if="filteredAssignments.length === 0"
                                    class="py-4 text-center text-gray-500"
                                >
                                    No assignments found
                                </div>

                                <div v-else class="space-y-3">
                                    <div
                                        v-for="assignment in pagedAssignments"
                                        :key="assignment.id"
                                        class="rounded-lg border border-gray-200 p-3 hover:bg-gray-50 transition-colors"
                                    >
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-1">
                                                    <div class="font-medium text-gray-900">
                                                        {{ assignment.municipality.name }}
                                                    </div>
                                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">
                                                        {{ formatDate(assignment.deadline_date) }}
                                                    </span>
                                                </div>
                                                <div
                                                    v-if="assignment.company"
                                                    class="text-sm text-gray-600 mb-1"
                                                >
                                                    Company: {{ assignment.company.name }}
                                                </div>
                                                <div
                                                    v-if="assignment.notes"
                                                    class="text-sm text-gray-500 mb-2"
                                                >
                                                    Notes: {{ assignment.notes }}
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    Created {{ formatTimeAgo(assignment.created_at) }}
                                                </div>
                                            </div>
                                            <button
                                                @click="removeAssignment(assignment.id)"
                                                class="ml-3 text-red-600 hover:text-red-900 focus:outline-none"
                                                :disabled="assignmentForm.processing"
                                            >
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 011.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between border-t border-gray-200 pt-3 text-sm">
                                        <div class="text-gray-500">
                                            Showing {{ (assignmentPage - 1) * assignmentPerPage + 1 }}
                                            to {{ Math.min(assignmentPage * assignmentPerPage, filteredAssignments.length) }}
                                            of {{ filteredAssignments.length }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button
                                                class="rounded-md border px-3 py-1 text-gray-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="assignmentPage <= 1"
                                                @click="assignmentPage--"
                                            >
                                                Previous
                                            </button>
                                            <span class="text-gray-700">Page {{ assignmentPage }} / {{ totalAssignmentPages }}</span>
                                            <button
                                                class="rounded-md border px-3 py-1 text-gray-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="assignmentPage >= totalAssignmentPages"
                                                @click="assignmentPage++"
                                            >
                                                Next
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style src="@/../css/Centralized/Pages/Admin/Users/Edit.css"></style>

