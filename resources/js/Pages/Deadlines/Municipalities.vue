<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

// Props
const props = defineProps({
    municipalities: Array,
    allCompanies: Array,
    selectedMunicipality: Object,
    selectedDate: String,
    deadlines: Object,
    users: Array,
    assignments: Array,
    flash: Object,
});

// Add this watcher to update when assignments change
watch(() => props.assignments, () => {
    // Update local state if needed
}, { deep: true });
const page = usePage();

/* -----------------
   State
-------------------*/

const form = ref({
    municipality_id: props.selectedMunicipality?.id || '',
    deadline_date: props.selectedDate || '',
    notes: '',
    assigned_user_id: '',
    company_ids: [],
});

const currentDate = ref(props.selectedDate ? new Date(props.selectedDate) : new Date());
const selectedDeadline = ref(null);
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');
const showAssignmentModal = ref(false);
const showDeadlineModal = ref(false);
const selectedDateAssignments = ref([]);
const selectedDateDeadline = ref(null);
const allCompaniesSelected = ref(false);
const assignmentSearch = ref('');
const assignmentModalDate = ref('');
const companySearch = ref('');

/* -----------------
   Helpers
-------------------*/

const formatDateISO = (date) => {
    if (!date) return '';
    if (!(date instanceof Date)) date = new Date(date);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const isPastDate = (date) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return new Date(date) < today;
};

/* -----------------
   Toast Notifications
-------------------*/

const showNotification = (message, type = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    showToast.value = true;

    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

watch(
    () => page.props.flash,
    (newFlash) => {
        if (newFlash?.success) showNotification(newFlash.success, 'success');
        if (newFlash?.error) showNotification(newFlash.error, 'error');
    },
    { immediate: true },
);

/* -----------------
   Calendar
-------------------*/

const daysInMonth = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const days = [];

    for (let i = 0; i < firstDay.getDay(); i++) {
        days.push({ day: null, date: null });
    }

    for (let i = 1; i <= lastDay.getDate(); i++) {
        const date = new Date(year, month, i);
        const dateString = formatDateISO(date);
        const deadline = props.deadlines?.[dateString] || null;
        const assignments = props.assignments?.filter((a) => a.deadline_date === dateString) || [];

        days.push({
            day: i,
            date: dateString,
            hasDeadline: !!deadline,
            deadline,
            assignments,
            assignmentCount: assignments.length,
            isToday: formatDateISO(new Date()) === dateString,
            isSelected: form.value.deadline_date === dateString,
            isPast: isPastDate(dateString),
        });
    }

    return days;
});

const monthYear = computed(() => {
    return currentDate.value.toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    });
});

/* -----------------
   Navigation
-------------------*/

const prevMonth = () => {
    currentDate.value = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth() - 1,
        1,
    );
    updateUrl();
};

const nextMonth = () => {
    currentDate.value = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth() + 1,
        1,
    );
    updateUrl();
};

const goToToday = () => {
    currentDate.value = new Date();
    form.value.deadline_date = formatDateISO(new Date());
    updateUrl();
};

const updateUrl = () => {
    const params = {
        date: formatDateISO(currentDate.value),
    };

    if (form.value.municipality_id) {
        params.municipality_id = form.value.municipality_id;
    }

    // Use router.visit instead of router.get with route()
    router.visit('/deadlines/municipalities', {
        data: params,
        preserveState: true,
        replace: true,
    });
};

/* -----------------
   Assignment Functions
-------------------*/

const unassignedCompanies = computed(() => {
    if (!props.selectedMunicipality || !form.value.deadline_date) return [];

    const assignedCompanyIds = props.assignments
        .filter(
            (a) =>
                a.municipality_id === props.selectedMunicipality.id &&
                a.deadline_date === form.value.deadline_date,
        )
        .map((a) => a.company_id);

    // Every company submits to every municipality, so use the full list.
    let companies = (props.allCompanies || []).filter(
        (company) => !assignedCompanyIds.includes(company.id),
    );

    // Apply search filter.
    const q = (companySearch.value || '').trim().toLowerCase();
    if (q) {
        companies = companies.filter((c) => c.name.toLowerCase().includes(q));
    }

    return companies;
});

// Filtered list for the left-hand company status panel (same search term).
const filteredPanelCompanies = computed(() => {
    const all = props.allCompanies || [];
    const q = (companySearch.value || '').trim().toLowerCase();
    if (!q) return all;
    return all.filter((c) => c.name.toLowerCase().includes(q));
});

const toggleAllCompanies = () => {
    if (allCompaniesSelected.value) {
        // If already selected, clear selection
        form.value.company_ids = [];
    } else {
        // Select all unassigned companies
        form.value.company_ids = unassignedCompanies.value.map((company) => company.id);
    }
};

const deleteAssignment = (id) => {
    if (confirm('Are you sure you want to delete this assignment?')) {
        router.delete(`/deadlines/assignments/${id}`, {
            onSuccess: () => {
                showNotification('Assignment deleted successfully!', 'success');
                router.reload({ only: ['assignments'] });
            },
            onError: () => {
                showNotification('Failed to delete assignment', 'error');
            },
        });
    }
};

/* -----------------
   Deadline Functions
-------------------*/

const selectDate = (date) => {
    if (!date || isPastDate(date)) return;
    form.value.deadline_date = date;
    selectedDeadline.value = props.deadlines?.[date] || null;
    form.value.notes = selectedDeadline.value?.notes || '';
};
const createDeadlineWithAssignments = () => {
    if (!form.value.municipality_id) {
        showNotification('Please select a municipality first', 'error');
        return;
    }
    if (!form.value.deadline_date) {
        showNotification('Please select a date', 'error');
        return;
    }
    if (isPastDate(form.value.deadline_date)) {
        showNotification('Cannot create deadlines for past dates', 'error');
        return;
    }
    if (!form.value.assigned_user_id) {
        showNotification('Please select a user to assign', 'error');
        return;
    }
    if (form.value.company_ids.length === 0) {
        showNotification('Please select at least one company', 'error');
        return;
    }

    // Use direct URL instead of route() function
    router.post(
        '/deadlines/create-with-assignments',
        {
            municipality_id: form.value.municipality_id,
            deadline_date: form.value.deadline_date,
            notes: form.value.notes,
            assigned_user_id: form.value.assigned_user_id,
            company_ids: form.value.company_ids,
        },
        {
            onSuccess: () => {
                form.value.notes = '';
                form.value.assigned_user_id = '';
                form.value.company_ids = [];
                allCompaniesSelected.value = false;
                showDeadlineModal.value = false;
                showNotification('Deadline and assignments created successfully!', 'success');
                router.reload({ only: ['deadlines', 'assignments'] });
            },
            onError: (errors) => {
                showNotification(
                    'Failed to create deadline: ' + Object.values(errors).join(', '),
                    'error',
                );
            },
        },
    );
};
/* -----------------
   Municipality Functions
-------------------*/

const municipalityChanged = () => {
    if (!form.value.municipality_id) return;

    const params = new URLSearchParams();
    params.append('municipality_id', form.value.municipality_id);
    params.append('date', formatDateISO(currentDate.value));

    router.visit(`/deadlines/municipalities?${params.toString()}`, {
        preserveState: true,
        replace: true,
    });
};

const clearMunicipality = () => {
    form.value.municipality_id = '';
    form.value.deadline_date = '';
    form.value.notes = '';
    form.value.assigned_user_id = '';
    form.value.company_ids = [];
    selectedDeadline.value = null;
    allCompaniesSelected.value = false;

    const params = new URLSearchParams();
    params.append('date', formatDateISO(currentDate.value));

    router.visit(`/deadlines/municipalities?${params.toString()}`, {
        preserveState: true,
        replace: true,
    });
};

/* -----------------
   Assignment Modal
-------------------*/

const openAssignmentModal = (date) => {
    if (!date) return;
    assignmentModalDate.value = date;
    assignmentSearch.value = '';
    selectedDateAssignments.value =
        props.assignments?.filter((a) => a.deadline_date === date) || [];
    selectedDateDeadline.value = props.deadlines?.[date] || null;
    showAssignmentModal.value = true;
};

const closeAssignmentModal = () => {
    showAssignmentModal.value = false;
    assignmentSearch.value = '';
    assignmentModalDate.value = '';
    selectedDateAssignments.value = [];
    selectedDateDeadline.value = null;
};

const filteredDateAssignments = computed(() => {
    const term = assignmentSearch.value.trim().toLowerCase();
    if (!term) return selectedDateAssignments.value;

    return selectedDateAssignments.value.filter((assignment) => {
        const haystack = [
            assignment.company_name,
            assignment.user_name,
            assignment.municipality_name,
            assignment.notes,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(term);
    });
});

const openDeadlineModal = () => {
    if (!form.value.municipality_id) {
        showNotification('Please select a municipality first', 'error');
        return;
    }
    showDeadlineModal.value = true;
};

const closeDeadlineModal = () => {
    showDeadlineModal.value = false;
    form.value.assigned_user_id = '';
    form.value.company_ids = [];
    form.value.notes = '';
    allCompaniesSelected.value = false;
};

/* -----------------
   Company Status
-------------------*/

const companySubmissionMap = computed(() => {
    const map = {};
    if (!props.selectedMunicipality) return map;

    (props.allCompanies || []).forEach((company) => {
        const isAssigned = props.assignments.some(
            (a) =>
                a.company_id === company.id && a.municipality_id === props.selectedMunicipality.id,
        );

        map[company.id] = { isAssigned };
    });

    return map;
});

const getCompanyStatus = (companyId) => {
    const status = companySubmissionMap.value[companyId];
    return status?.isAssigned ? 'assigned' : 'unassigned';
};

/* -----------------
   Watchers
-------------------*/

watch(
    () => props.selectedMunicipality,
    (newVal) => {
        if (newVal) form.value.municipality_id = newVal.id;
    },
);

watch(
    () => props.selectedDate,
    (newVal) => {
        if (newVal) {
            form.value.deadline_date = newVal;
            currentDate.value = new Date(newVal);
        }
    },
);

watch(
    () => form.value.company_ids,
    (newVal) => {
        allCompaniesSelected.value =
            newVal.length === unassignedCompanies.value.length &&
            unassignedCompanies.value.length > 0;
    },
    { deep: true },
);

watch(
    () => unassignedCompanies.value,
    () => {
        // Reset select all when unassigned companies change
        allCompaniesSelected.value = false;
        form.value.company_ids = [];
    },
    { deep: true },
);

onMounted(() => {
    if (props.selectedDate) {
        selectDate(props.selectedDate);
    }
});
</script>

<template>
    <AppLayout>
        <!-- Toast Notification -->
        <div
            v-if="showToast"
            :class="[
                'fixed top-4 right-4 z-50 rounded-lg border-l-4 px-6 py-4 shadow-lg transition-all duration-300',
                toastType === 'success'
                    ? 'border-green-500 bg-green-50 text-green-800'
                    : toastType === 'error'
                      ? 'border-red-500 bg-red-50 text-red-800'
                      : 'border-yellow-500 bg-yellow-50 text-yellow-800',
            ]"
        >
            <div class="flex items-center">
                <span class="font-medium">{{ toastMessage }}</span>
                <button @click="showToast = false" class="ml-4 text-gray-500 hover:text-gray-700">
                    ×
                </button>
            </div>
        </div>

        <!-- Assignment Modal -->
        <div
            v-if="showAssignmentModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/20 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[80vh] w-full max-w-4xl overflow-hidden rounded-xl bg-white shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-gray-200 p-6">
                    <h3 class="text-xl font-semibold text-gray-800">
                        Assignments for {{ formatDate(assignmentModalDate || form.deadline_date) }}
                    </h3>
                    <button @click="closeAssignmentModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto p-6">
                    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            <span class="font-medium text-gray-700">Total assignments:</span>
                            <span class="ml-1 text-gray-900">{{ selectedDateAssignments.length }}</span>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            <span class="font-medium text-gray-700">Filtered:</span>
                            <span class="ml-1 text-gray-900">{{ filteredDateAssignments.length }}</span>
                        </div>
                        <div>
                            <input
                                v-model="assignmentSearch"
                                type="text"
                                placeholder="Search company, user, municipality..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            />
                        </div>
                    </div>

                    <!-- Deadline Info -->
                    <div
                        v-if="selectedDateDeadline"
                        class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4"
                    >
                        <h4 class="mb-2 font-semibold text-blue-800">Deadline Information</h4>
                        <p class="text-sm text-blue-700">{{ selectedDateDeadline.notes }}</p>
                    </div>

                    <div v-if="selectedDateAssignments.length > 0" class="max-h-[46vh] overflow-y-auto pr-1">
                        <div class="space-y-3">
                        <div
                            v-for="assignment in filteredDateAssignments"
                            :key="assignment.id"
                            class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-gray-800">
                                        {{ assignment.company_name }}
                                    </h4>
                                    <p class="mt-1 text-sm text-gray-600">
                                        <span class="font-medium">Assigned to:</span>
                                        {{ assignment.user_name }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-600">
                                        <span class="font-medium">Municipality:</span>
                                        {{ assignment.municipality_name }}
                                    </p>
                                    <p v-if="assignment.notes" class="mt-2 text-sm text-gray-500">
                                        <span class="font-medium">Notes:</span>
                                        {{ assignment.notes }}
                                    </p>
                                </div>
                                <button
                                    @click="deleteAssignment(assignment.id)"
                                    class="ml-4 rounded-lg p-2 text-red-600 transition-colors hover:bg-red-50"
                                    title="Remove assignment"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-gray-500">
                        <div class="mb-4 text-4xl">📋</div>
                        <p class="text-lg">No assignments for this date</p>
                    </div>
                    <div v-if="selectedDateAssignments.length > 0 && filteredDateAssignments.length === 0" class="py-8 text-center text-gray-500">
                        <div class="mb-2 text-2xl">🔎</div>
                        <p>No results for "{{ assignmentSearch }}"</p>
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 p-6">
                    <button
                        @click="closeAssignmentModal"
                        class="rounded-lg bg-gray-600 px-6 py-2 text-white transition-colors hover:bg-gray-700"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Deadline Creation Modal -->
        <div
            v-if="showDeadlineModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/20 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-xl bg-white shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-gray-200 p-6">
                    <h3 class="text-xl font-semibold text-gray-800">
                        Create Deadline and Assignments
                    </h3>
                    <button @click="closeDeadlineModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto p-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Left Column -->
                        <div>
                            <div class="mb-4">
                                <label class="mb-2 block text-sm font-medium text-gray-700"
                                >Municipality</label
                                >
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                    <p class="font-medium text-gray-800">
                                        {{ selectedMunicipality.name }}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        {{ selectedMunicipality.code }}
                                    </p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="mb-2 block text-sm font-medium text-gray-700"
                                >Deadline Date</label
                                >
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                    <p class="font-medium text-gray-800">
                                        {{ formatDate(form.deadline_date) }}
                                    </p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="mb-2 block text-sm font-medium text-gray-700"
                                >Assign to User</label
                                >
                                <select
                                    v-model="form.assigned_user_id"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                    required
                                >
                                    <option value="">-- Select User --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700"
                                >Deadline Notes</label
                                >
                                <textarea
                                    v-model="form.notes"
                                    placeholder="Add deadline notes..."
                                    class="w-full resize-none rounded-lg border border-gray-300 px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                    rows="3"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700"
                            >Select Companies</label
                            >

                            <!-- Search + select-all bar -->
                            <input
                                v-model="companySearch"
                                type="text"
                                placeholder="Search companies..."
                                class="mb-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                            />
                            <div class="mb-3 flex items-center">
                                <input
                                    type="checkbox"
                                    :checked="allCompaniesSelected"
                                    @change="toggleAllCompanies"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :disabled="unassignedCompanies.length === 0"
                                />
                                <span class="ml-2 text-sm text-gray-700">Select all</span>
                                <span class="ml-auto text-sm text-gray-500">
                                    {{ form.company_ids.length }} of
                                    {{ unassignedCompanies.length }} selected
                                </span>
                            </div>

                            <div
                                class="max-h-64 overflow-y-auto rounded-lg border border-gray-300 bg-gray-50 p-4"
                            >
                                <div
                                    v-if="unassignedCompanies.length === 0"
                                    class="py-4 text-center text-gray-500"
                                >
                                    {{ companySearch ? 'No companies match your search' : 'All companies are already assigned for this date' }}
                                </div>
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="company in unassignedCompanies"
                                        :key="company.id"
                                        class="flex items-center"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="company.id"
                                            v-model="form.company_ids"
                                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="ml-3 text-sm text-gray-700"
                                        >{{ company.name }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 border-t border-gray-200 p-6">
                    <button
                        @click="closeDeadlineModal"
                        class="rounded-lg bg-gray-600 px-6 py-2 text-white transition-colors hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        @click="createDeadlineWithAssignments"
                        class="rounded-lg bg-blue-600 px-6 py-2 font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!form.assigned_user_id || form.company_ids.length === 0"
                    >
                        Create Deadline & Assignments
                    </button>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Municipality Management</h1>
                    <p class="mt-2 text-gray-600">
                        Manage deadlines and assignments for municipalities
                    </p>
                </div>
                <button
                    @click="goToToday"
                    class="rounded-lg bg-blue-600 px-6 py-3 font-medium text-white transition-colors hover:bg-blue-700"
                >
                    Today
                </button>
            </div>

            <!-- Main Content -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                <div class="p-8">
                    <!-- Municipality Selection -->
                    <div class="mb-8">
                        <label class="mb-3 block text-sm font-medium text-gray-700"
                        >Select Municipality</label
                        >
                        <div class="flex items-center space-x-4">
                            <select
                                v-model="form.municipality_id"
                                @change="municipalityChanged"
                                class="flex-1 rounded-lg border border-gray-300 px-4 py-3 text-sm transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="">-- Select Municipality --</option>
                                <option
                                    v-for="municipality in municipalities"
                                    :key="municipality.id"
                                    :value="municipality.id"
                                >
                                    {{ municipality.name }} ({{ municipality.code }})
                                </option>
                            </select>
                            <button
                                v-if="form.municipality_id"
                                @click="clearMunicipality"
                                class="rounded-lg border border-gray-300 px-4 py-3 text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-800"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="form.municipality_id && props.selectedMunicipality"
                        class="grid grid-cols-1 gap-8 lg:grid-cols-3"
                    >
                        <!-- Left Panel - Companies -->
                        <div class="lg:col-span-1">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-6">
                                <h3 class="mb-3 text-lg font-semibold text-gray-800">
                                    Companies
                                    <span class="text-sm text-gray-500"
                                    >({{ (props.allCompanies || []).length }})</span
                                    >
                                </h3>

                                <!-- Search -->
                                <input
                                    v-model="companySearch"
                                    type="text"
                                    placeholder="Search companies..."
                                    class="mb-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                />

                                <div class="max-h-96 space-y-3 overflow-y-auto">
                                    <div
                                        v-for="company in filteredPanelCompanies"
                                        :key="company.id"
                                        :class="[
                                            'rounded-lg border p-4 transition-colors',
                                            getCompanyStatus(company.id) === 'assigned'
                                                ? 'border-green-200 bg-green-50'
                                                : 'border-gray-200 bg-white',
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="font-medium text-gray-800">
                                                    {{ company.name }}
                                                </h4>
                                                <p class="text-sm text-gray-600">
                                                    {{ company.code }}
                                                </p>
                                            </div>
                                            <span
                                                :class="[
                                                    'rounded-full px-2 py-1 text-xs font-medium',
                                                    getCompanyStatus(company.id) === 'assigned'
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-gray-100 text-gray-800',
                                                ]"
                                            >
                                                {{ getCompanyStatus(company.id) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel - Calendar -->
                        <div class="lg:col-span-2">
                            <div class="rounded-lg border border-gray-200 bg-white p-6">
                                <!-- Calendar Header -->
                                <div class="mb-6 flex items-center justify-between">
                                    <button
                                        @click="prevMonth"
                                        class="rounded-lg p-2 transition-colors hover:bg-gray-100"
                                    >
                                        <svg
                                            class="h-5 w-5 text-gray-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 19l-7-7 7-7"
                                            />
                                        </svg>
                                    </button>

                                    <h2 class="text-xl font-semibold text-gray-800">
                                        {{ monthYear }}
                                    </h2>

                                    <button
                                        @click="nextMonth"
                                        class="rounded-lg p-2 transition-colors hover:bg-gray-100"
                                    >
                                        <svg
                                            class="h-5 w-5 text-gray-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Calendar Grid -->
                                <div class="mb-2 grid grid-cols-7 gap-2">
                                    <div
                                        v-for="day in [
                                            'Sun',
                                            'Mon',
                                            'Tue',
                                            'Wed',
                                            'Thu',
                                            'Fri',
                                            'Sat',
                                        ]"
                                        :key="day"
                                        class="py-2 text-center text-sm font-medium text-gray-500"
                                    >
                                        {{ day }}
                                    </div>
                                </div>

                                <div class="grid grid-cols-7 gap-2">
                                    <div
                                        v-for="(day, index) in daysInMonth"
                                        :key="index"
                                        :class="[
                                            'relative h-24 cursor-pointer overflow-hidden rounded-lg border p-2 transition-colors',
                                            day.isToday
                                                ? 'border-blue-300 bg-blue-50'
                                                : 'border-gray-200',
                                            day.isSelected ? 'ring-2 ring-blue-500 ring-inset' : '',
                                            day.isPast
                                                ? 'cursor-not-allowed opacity-50'
                                                : 'hover:bg-gray-50',
                                        ]"
                                        @click="!day.isPast && selectDate(day.date)"
                                        @dblclick="!day.isPast && openAssignmentModal(day.date)"
                                    >
                                        <!-- Day Number -->
                                        <div class="mb-1 flex items-center justify-between">
                                            <span
                                                :class="[
                                                    'text-sm font-medium',
                                                    day.isToday ? 'text-blue-600' : 'text-gray-700',
                                                ]"
                                            >
                                                {{ day.day }}
                                            </span>
                                            <span
                                                v-if="day.isToday"
                                                class="h-2 w-2 rounded-full bg-blue-600"
                                            ></span>
                                        </div>

                                        <!-- Deadline Indicator -->
                                        <div v-if="day.hasDeadline" class="mb-1">
                                            <span
                                                class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800"
                                            >
                                                Deadline
                                            </span>
                                        </div>

                                        <!-- Assignments -->
                                        <div v-if="day.assignmentCount > 0" class="space-y-1">
                                            <div class="flex items-center text-xs text-green-600">
                                                <svg
                                                    class="mr-1 h-3 w-3"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                    />
                                                </svg>
                                                {{ day.assignmentCount }} assignment{{
                                                    day.assignmentCount > 1 ? 's' : ''
                                                }}
                                            </div>
                                        </div>

                                        <!-- Hover Actions -->
                                        <div
                                            v-if="!day.isPast && day.date"
                                            class="bg-opacity-0 hover:bg-opacity-10 absolute inset-0 flex items-center justify-center bg-black opacity-0 transition-all hover:opacity-100"
                                        >
                                            <button
                                                @click.stop="openAssignmentModal(day.date)"
                                                class="rounded bg-white p-1 text-xs text-gray-600 shadow-sm hover:text-gray-800"
                                            >
                                                View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div
                                    class="mt-6 flex justify-end space-x-4 border-t border-gray-200 pt-6"
                                >
                                    <button
                                        @click="openDeadlineModal"
                                        class="rounded-lg bg-blue-600 px-6 py-2 font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            !form.deadline_date || isPastDate(form.deadline_date)
                                        "
                                    >
                                        Create Deadline & Assign
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="py-12 text-center text-gray-500">
                        <div class="mb-4 text-6xl">🏢</div>
                        <p class="text-lg">
                            Please select a municipality to view deadlines and assignments
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
