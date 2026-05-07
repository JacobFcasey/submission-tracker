<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    stats: Object,
    upcomingDeadlines: Array,
    allMunicipalityDeadlines: Array,
    overdueAssignments: Array,
    assignmentsByMunicipality: Array,
    recentActivity: Array,
    progress: Object,
    isAdmin: Boolean,
    isSuperAdmin: Boolean,
    showAllData: Boolean,
    users: Array,
    selectedUserId: String,
    currentUserName: String,
    capsSync: Object,
    filters: Object,
});

// Local state
ref('');
const selectedFilter = ref(props.filters?.user_id || '');
const loading = ref(false);
const showAssignmentNotification = ref(false);
const assignmentNotification = ref(null);
const notificationTimeout = ref(null);

// Pagination states
const upcomingDeadlinesPage = ref(1);
const allDeadlinesPage = ref(1);
const overdueAssignmentsPage = ref(1);
const recentActivityPage = ref(1);
const municipalityPage = ref(1);
const itemsPerPage = 5;

// Paginated data
const paginatedUpcomingDeadlines = computed(() => {
    const start = (upcomingDeadlinesPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.upcomingDeadlines.slice(start, end);
});

const paginatedAllDeadlines = computed(() => {
    const start = (allDeadlinesPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return (props.allMunicipalityDeadlines || []).slice(start, end);
});

const allDeadlinesPages = computed(() =>
    Math.ceil((props.allMunicipalityDeadlines || []).length / itemsPerPage)
);

const paginatedOverdueAssignments = computed(() => {
    const start = (overdueAssignmentsPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.overdueAssignments.slice(start, end);
});

const paginatedRecentActivity = computed(() => {
    const start = (recentActivityPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.recentActivity.slice(start, end);
});

const paginatedMunicipalities = computed(() => {
    const start = (municipalityPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.assignmentsByMunicipality.slice(start, end);
});

// Pagination metadata
const upcomingDeadlinesPages = computed(() =>
    Math.ceil(props.upcomingDeadlines.length / itemsPerPage)
);

const overdueAssignmentsPages = computed(() =>
    Math.ceil(props.overdueAssignments.length / itemsPerPage)
);

const recentActivityPages = computed(() =>
    Math.ceil(props.recentActivity.length / itemsPerPage)
);

const municipalityPages = computed(() =>
    Math.ceil(props.assignmentsByMunicipality.length / itemsPerPage)
);

// Format date
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

// Format relative time
const formatRelativeTime = (dateString) => {
    if (!dateString) return '';

    // Normalize Laravel timestamps (no timezone suffix) to UTC
    let normalized = dateString;
    if (!/[TZ+\-]/.test(dateString)) {
        normalized = dateString.replace(' ', 'T') + 'Z';
    }

    const date = new Date(normalized);
    if (isNaN(date.getTime())) return dateString;

    const now = new Date();
    const diffMs = now - date;
    if (diffMs < 0) return 'Just now';

    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHr  = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHr / 24);

    if (diffSec < 60) {
        return 'Just now';
    } else if (diffMin < 60) {
        return `${diffMin} min${diffMin !== 1 ? 's' : ''} ago`;
    } else if (diffHr < 24) {
        return `${diffHr} hour${diffHr !== 1 ? 's' : ''} ago`;
    } else if (diffDay === 1) {
        return 'Yesterday';
    } else if (diffDay < 7) {
        return `${diffDay} days ago`;
    } else if (diffDay < 30) {
        const weeks = Math.floor(diffDay / 7);
        return `${weeks} week${weeks !== 1 ? 's' : ''} ago`;
    } else if (diffDay < 365) {
        const months = Math.floor(diffDay / 30);
        return `${months} month${months !== 1 ? 's' : ''} ago`;
    } else {
        return formatDate(dateString);
    }
};

// Handle user filter change - only for super-admin
const filterUser = () => {
    const params = {};
    if (selectedFilter.value) {
        params.user_id = selectedFilter.value;
    }
    router.get('/dashboard', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Clear filter
const clearFilter = () => {
    selectedFilter.value = '';
    router.get('/dashboard', {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Handle click on assignment card
const handleAssignmentClick = (assignment) => {
    // Navigate to deadlines page with filter for this company/municipality
    const params = {};
    if (assignment.company_id) {
        params.company_id = assignment.company_id;
    }
    if (assignment.municipality_id) {
        params.municipality_id = assignment.municipality_id;
    }

    router.visit('/deadlines/municipalities', {
        data: params,
        preserveScroll: true
    });
};

// Handle click on municipality card
const handleMunicipalityClick = (municipality) => {
    router.visit('/deadlines/municipalities', {
        data: { municipality: municipality.municipality },
        preserveScroll: true
    });
};

// Check for new assignments periodically
const checkForNewAssignments = () => {
    if (page.props.flash?.assignment_created) {
        showNewAssignmentNotification(page.props.flash.assignment_created);
    }
};

// Show new assignment notification
const showNewAssignmentNotification = (data) => {
    showAssignmentNotification.value = true;
    assignmentNotification.value = {
        message: data.message || 'New assignment created',
        user: data.user_name,
        municipality: data.municipality_name,
        companyCount: data.company_count || 1,
        timestamp: new Date().toISOString()
    };

    clearTimeout(notificationTimeout.value);
    notificationTimeout.value = setTimeout(() => {
        showAssignmentNotification.value = false;
    }, 5000);
};

// Dismiss notification
const dismissNotification = () => {
    showAssignmentNotification.value = false;
    clearTimeout(notificationTimeout.value);
};

// View assignment details
const viewAssignments = () => {
    if (assignmentNotification.value) {
        router.visit('/deadlines/municipalities');
    }
    dismissNotification();
};

// CAPS data sync
const capsSyncing = ref(false);
const capsSyncMessage = ref('');
const capsSyncError = ref(false);

const triggerCapsSync = async () => {
    capsSyncing.value = true;
    capsSyncMessage.value = '';
    capsSyncError.value = false;

    try {
        const xsrfCookie = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
        const csrfToken = xsrfCookie
            ? decodeURIComponent(xsrfCookie.split('=')[1])
            : (page.props.csrf_token || '');

        const res = await fetch('/admin/caps-sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const data = await res.json();
        capsSyncMessage.value = data.message || (data.ok ? 'Sync complete' : 'Sync failed');
        capsSyncError.value = !data.ok;

        if (data.ok) {
            // Reload dashboard to reflect new data
            router.reload({ preserveScroll: true });
        }
    } catch (e) {
        capsSyncMessage.value = 'Failed to reach sync endpoint: ' + (e.message || 'unknown error');
        capsSyncError.value = true;
    } finally {
        capsSyncing.value = false;
    }
};

// Refresh dashboard data
const refreshDashboardData = () => {
    loading.value = true;
    router.reload({
        only: ['stats', 'upcomingDeadlines', 'allMunicipalityDeadlines', 'overdueAssignments', 'recentActivity', 'assignmentsByMunicipality', 'progress', 'capsSync'],
        preserveScroll: true,
        onFinish: () => {
            loading.value = false;
        }
    });
};

// Watch for page flash messages
watch(() => page.props.flash, (newFlash) => {
    if (newFlash?.assignment_created) {
        showNewAssignmentNotification(newFlash.assignment_created);
    }
}, { immediate: true });

// Setup periodic check
onMounted(() => {
    checkForNewAssignments();
    const interval = setInterval(checkForNewAssignments, 30000);
    return () => {
        clearInterval(interval);
        clearTimeout(notificationTimeout.value);
    };
});
</script>

<template>
    <AppLayout>
        <!-- Assignment Notification -->
        <div v-if="showAssignmentNotification && assignmentNotification"
             class="fixed top-4 right-4 z-50 max-w-sm animate-fade-in">
            <div class="bg-white rounded-lg shadow-lg border border-blue-200 overflow-hidden">
                <div class="px-4 py-3 bg-blue-50 border-b border-blue-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-blue-800">New Assignment</h3>
                        <button @click="dismissNotification"
                                class="text-blue-400 hover:text-blue-600 focus:outline-none">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-sm text-gray-800 mb-2">{{ assignmentNotification.message }}</p>
                    <div class="text-xs text-gray-600 space-y-1">
                        <div class="flex items-center">
                            <svg class="h-3 w-3 mr-1 text-gray-400" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>User: {{ assignmentNotification.user }}</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="h-3 w-3 mr-1 text-gray-400" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>Companies: {{ assignmentNotification.companyCount }}</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="h-3 w-3 mr-1 text-gray-400" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ formatRelativeTime(assignmentNotification.timestamp) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end space-x-2">
                        <button @click="refreshDashboardData"
                                class="text-xs bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-1">
                            Refresh
                        </button>
                        <button @click="viewAssignments"
                                class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                            View Assignments
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <!-- Header with User Filter (Super-Admin only) -->
            <div class="mb-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
                        <p class="mt-2 text-gray-600">
                            Welcome back! Here's your overview of assignments and deadlines.
                            <span v-if="isSuperAdmin && currentUserName" class="font-medium text-blue-600">
          {{ showAllData ? ' (All Users)' : ` (${currentUserName})` }}
        </span>
                        </p>
                    </div>

                    <!-- For all users, show refresh button aligned properly -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                        <!-- User Filter (Super-Admin only) -->
                        <div v-if="isSuperAdmin" class="w-full sm:w-64">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by User</label>

                            <div class="flex gap-2 items-center">
                                <select
                                    v-model="selectedFilter"
                                    @change="filterUser"
                                    class="block w-full h-10 rounded-md bg-white
                   border border-gray-400 hover:border-gray-500
                   py-2 pl-3 pr-10 text-sm text-gray-900 shadow-sm
                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-indigo-500"
                                >
                                    <option value="">All Users</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>

                                <button
                                    v-if="selectedFilter"
                                    @click="clearFilter"
                                    class="h-10 px-3 text-sm text-gray-600 hover:text-gray-900
                   border border-transparent hover:border-gray-300 rounded-md
                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <!-- Refresh button for all users -->
                        <div class="flex-shrink-0">
                            <button
                                @click="refreshDashboardData"
                                :disabled="loading"
                                class="inline-flex items-center px-4 py-2 h-10
                 border border-transparent text-sm font-medium rounded-md shadow-sm
                 text-white bg-indigo-600 hover:bg-indigo-700
                 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                 disabled:opacity-50"
                            >
                                <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ loading ? 'Refreshing...' : 'Refresh' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>



            <!-- CAPS Data Sync Panel -->
            <div v-if="capsSync" class="mb-4">

                <!-- ── Empty state: no CAPS data yet ── -->
                <div v-if="!capsSync.hasData"
                     class="relative overflow-hidden rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50 shadow-sm">
                    <div class="absolute top-0 right-0 h-full w-1/3 opacity-[0.04]">
                        <svg viewBox="0 0 200 200" fill="currentColor" class="h-full w-full text-amber-900">
                            <path d="M100 20 L180 80 L160 170 L40 170 L20 80 Z" />
                            <circle cx="100" cy="90" r="25" />
                            <rect x="85" y="120" width="30" height="8" rx="4" />
                        </svg>
                    </div>
                    <div class="relative px-6 py-5">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-md shadow-amber-200/50">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900">CAPS reference data required</h3>
                                <p class="mt-1 text-sm text-gray-600 leading-relaxed">
                                    Municipalities and companies haven't been synced from CAPS yet. Pull the latest data to activate uploads, deadlines, and assignments.
                                </p>
                            </div>
                            <button @click="triggerCapsSync" :disabled="capsSyncing"
                                    class="flex-shrink-0 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-amber-200/40 transition-all hover:from-amber-600 hover:to-orange-600 hover:shadow-lg hover:shadow-amber-200/60 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:shadow-md">
                                <svg v-if="capsSyncing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {{ capsSyncing ? 'Syncing...' : 'Sync from CAPS' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Has data: compact status strip ── -->
                <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="flex items-center gap-5">
                            <!-- Status indicator -->
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                </span>
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">CAPS Data</span>
                            </div>

                            <!-- Stat pills -->
                            <div class="hidden sm:flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-200/60">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                    {{ capsSync.municipalities }} municipalities
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200/60">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                    {{ capsSync.companies }} companies
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-200/60">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                    {{ capsSync.members?.toLocaleString() || 0 }} members
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-200/60">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    {{ capsSync.policies?.toLocaleString() || 0 }} policies
                                </span>
                            </div>

                            <!-- Last sync timestamp -->
                            <span v-if="capsSync.lastSync" class="hidden lg:inline-flex items-center gap-1 text-xs text-gray-400">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ new Date(capsSync.lastSync).toLocaleString() }}
                            </span>
                        </div>

                        <!-- Sync button -->
                        <button @click="triggerCapsSync" :disabled="capsSyncing"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 hover:border-gray-300 hover:shadow focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg v-if="capsSyncing" class="h-3.5 w-3.5 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                            </svg>
                            {{ capsSyncing ? 'Syncing...' : 'Refresh Data' }}
                        </button>
                    </div>

                    <!-- Sync result feedback -->
                    <Transition
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-active-class="transition duration-200 ease-out"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-from-class="opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-to-class="opacity-0">
                        <div v-if="capsSyncMessage"
                             class="border-t px-5 py-2.5 text-xs font-medium"
                             :class="capsSyncError
                                 ? 'border-red-100 bg-red-50 text-red-700'
                                 : 'border-emerald-100 bg-emerald-50 text-emerald-700'">
                            <div class="flex items-center gap-2">
                                <svg v-if="!capsSyncError" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                                {{ capsSyncMessage }}
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <!-- Total Assignments -->
                <Link href="/deadlines/municipalities"
                      class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-lg hover:border-blue-200 hover:-translate-y-0.5 cursor-pointer">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50/80 to-transparent opacity-0 transition-opacity group-hover:opacity-100"></div>
                    <div class="relative flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 shadow-md shadow-blue-200/50">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Assignments</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.total_assignments || 0 }}</p>
                        </div>
                    </div>
                </Link>

                <!-- Overdue -->
                <Link href="/deadlines/municipalities?status=overdue"
                      class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-lg hover:border-red-200 hover:-translate-y-0.5 cursor-pointer">
                    <div class="absolute inset-0 bg-gradient-to-br from-red-50/80 to-transparent opacity-0 transition-opacity group-hover:opacity-100"></div>
                    <div class="relative flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-rose-600 shadow-md shadow-red-200/50">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Overdue</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.overdue || 0 }}</p>
                        </div>
                    </div>
                </Link>

                <!-- Due This Week -->
                <Link href="/deadlines/municipalities?status=week"
                      class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-lg hover:border-amber-200 hover:-translate-y-0.5 cursor-pointer">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-50/80 to-transparent opacity-0 transition-opacity group-hover:opacity-100"></div>
                    <div class="relative flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-md shadow-amber-200/50">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Due This Week</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.due_this_week || 0 }}</p>
                        </div>
                    </div>
                </Link>

                <!-- Completion Rate -->
                <div class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-lg hover:border-emerald-200 hover:-translate-y-0.5">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/80 to-transparent opacity-0 transition-opacity group-hover:opacity-100"></div>
                    <div class="relative flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-md shadow-emerald-200/50">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Completion Rate</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.completion_rate || 0 }}%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <!-- Left Column -->
                <div class="space-y-4">
                    <!-- Upcoming Deadlines -->
                    <div class="dashboard-panel overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">Upcoming Deadlines</h3>
                                <span class="text-sm text-gray-600">{{ upcomingDeadlines.length }} total</span>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <div v-if="paginatedUpcomingDeadlines.length === 0" class="px-6 py-8 text-center">
                                <p class="text-gray-500">No upcoming deadlines</p>
                            </div>
                            <div v-for="deadline in paginatedUpcomingDeadlines" :key="deadline.id"
                                 @click="handleAssignmentClick(deadline)"
                                 class="px-6 py-4 hover:bg-gray-50 transition-colors cursor-pointer hover:shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ deadline.company_name }}</h4>
                                        <p class="text-sm text-gray-600">{{ deadline.municipality_name }}</p>
                                    </div>
                                    <div class="text-right">
                                        <span :class="[
                                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
                                            deadline.is_today ? 'bg-red-100 text-red-800' :
                                            deadline.is_tomorrow ? 'bg-amber-100 text-amber-800' :
                                            'bg-blue-100 text-blue-800'
                                        ]">
                                            {{ deadline.is_today ? 'Today' :
                                            deadline.is_tomorrow ? 'Tomorrow' :
                                                `${deadline.days_until} days` }}
                                        </span>
                                        <p class="mt-1 text-sm text-gray-500">{{ formatDate(deadline.deadline_date)
                                            }}</p>
                                    </div>
                                </div>
                                <p v-if="deadline.notes" class="mt-2 text-sm text-gray-600">{{ deadline.notes }}</p>
                            </div>
                        </div>
                        <!-- Pagination for Upcoming Deadlines -->
                        <div v-if="upcomingDeadlinesPages > 1" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600">
                                    Page {{ upcomingDeadlinesPage }} of {{ upcomingDeadlinesPages }}
                                </div>
                                <div class="flex gap-1">
                                    <button @click="upcomingDeadlinesPage = Math.max(1, upcomingDeadlinesPage - 1)"
                                            :disabled="upcomingDeadlinesPage === 1"
                                            class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Previous
                                    </button>
                                    <button
                                        @click="upcomingDeadlinesPage = Math.min(upcomingDeadlinesPages, upcomingDeadlinesPage + 1)"
                                        :disabled="upcomingDeadlinesPage === upcomingDeadlinesPages"
                                        class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <Link href="/deadlines/municipalities"
                                  class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                View all deadlines →
                            </Link>
                        </div>
                    </div>

                    <!-- All Municipality Deadlines — visible to everyone -->
                    <div class="dashboard-panel overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-white px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900">All Municipality Deadlines</h3>
                                </div>
                                <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
                                    {{ (allMunicipalityDeadlines || []).length }} active
                                </span>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <div v-if="paginatedAllDeadlines.length === 0" class="px-6 py-8 text-center">
                                <p class="text-gray-500">No active deadlines</p>
                            </div>
                            <div v-for="dl in paginatedAllDeadlines" :key="dl.id"
                                 @click="router.visit('/deadlines/municipalities', { data: { municipality_id: dl.municipality_id, date: dl.deadline_date } })"
                                 class="px-6 py-4 hover:bg-gray-50 transition-colors cursor-pointer">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ dl.municipality_name }}</h4>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                            <span v-if="dl.assigned_companies_count > 0">
                                                {{ dl.assigned_companies_count }} {{ dl.assigned_companies_count === 1 ? 'company' : 'companies' }}
                                            </span>
                                            <span v-if="dl.assigned_users?.length" class="flex items-center gap-1">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                {{ dl.assigned_users.join(', ') }}
                                            </span>
                                            <span v-else class="text-amber-600">No one assigned</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span :class="[
                                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
                                            dl.is_today ? 'bg-red-100 text-red-800' :
                                            dl.is_tomorrow ? 'bg-amber-100 text-amber-800' :
                                            dl.is_this_week ? 'bg-blue-100 text-blue-800' :
                                            'bg-gray-100 text-gray-700'
                                        ]">
                                            {{ dl.is_today ? 'Today' :
                                               dl.is_tomorrow ? 'Tomorrow' :
                                               `${dl.days_until} days` }}
                                        </span>
                                        <p class="mt-1 text-sm text-gray-500">{{ formatDate(dl.deadline_date) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="allDeadlinesPages > 1" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600">
                                    Page {{ allDeadlinesPage }} of {{ allDeadlinesPages }}
                                </div>
                                <div class="flex gap-1">
                                    <button @click="allDeadlinesPage = Math.max(1, allDeadlinesPage - 1)"
                                            :disabled="allDeadlinesPage === 1"
                                            class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Previous
                                    </button>
                                    <button @click="allDeadlinesPage = Math.min(allDeadlinesPages, allDeadlinesPage + 1)"
                                            :disabled="allDeadlinesPage === allDeadlinesPages"
                                            class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <Link href="/deadlines/municipalities"
                                  class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                Manage deadlines →
                            </Link>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="dashboard-panel overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Recent Activity</h3>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <div v-if="paginatedRecentActivity.length === 0" class="px-6 py-8 text-center">
                                <p class="text-gray-500">No recent activity</p>
                            </div>
                            <div v-for="activity in paginatedRecentActivity" :key="activity.id"
                                 @click="handleAssignmentClick(activity)"
                                 class="px-6 py-4 hover:bg-gray-50 transition-colors cursor-pointer hover:shadow-sm">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                            <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor"
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-900">
                                            <span class="font-medium">{{ activity.company_name }}</span> in
                                            {{ activity.municipality_name }}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Deadline: {{ formatDate(activity.deadline_date) }}
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">{{ activity.created_at }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Pagination for Recent Activity -->
                        <div v-if="recentActivityPages > 1" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600">
                                    Page {{ recentActivityPage }} of {{ recentActivityPages }}
                                </div>
                                <div class="flex gap-1">
                                    <button @click="recentActivityPage = Math.max(1, recentActivityPage - 1)"
                                            :disabled="recentActivityPage === 1"
                                            class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Previous
                                    </button>
                                    <button
                                        @click="recentActivityPage = Math.min(recentActivityPages, recentActivityPage + 1)"
                                        :disabled="recentActivityPage === recentActivityPages"
                                        class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <!-- Overdue Assignments -->
                    <div class="dashboard-panel overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-red-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-red-900">Overdue Assignments</h3>
                                <span class="text-sm font-medium text-red-800">{{ overdueAssignments.length
                                    }} overdue</span>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <div v-if="paginatedOverdueAssignments.length === 0" class="px-6 py-8 text-center">
                                <p class="text-gray-500">No overdue assignments</p>
                            </div>
                            <div v-for="assignment in paginatedOverdueAssignments" :key="assignment.id"
                                 @click="handleAssignmentClick(assignment)"
                                 class="px-6 py-4 hover:bg-red-50 transition-colors cursor-pointer hover:shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ assignment.company_name }}</h4>
                                        <p class="text-sm text-gray-600">{{ assignment.municipality_name }}</p>
                                    </div>
                                    <div class="text-right">
                                        <span
                                            class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-800">
                                            {{ assignment.days_overdue }} day{{ assignment.days_overdue !== 1 ? 's' : ''
                                            }} overdue
                                        </span>
                                        <p class="mt-1 text-sm text-gray-500">{{ formatDate(assignment.deadline_date)
                                            }}</p>
                                    </div>
                                </div>
                                <p v-if="assignment.notes" class="mt-2 text-sm text-gray-600">{{ assignment.notes }}</p>
                            </div>
                        </div>
                        <!-- Pagination for Overdue Assignments -->
                        <div v-if="overdueAssignmentsPages > 1" class="border-t border-gray-200 bg-red-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-red-700">
                                    Page {{ overdueAssignmentsPage }} of {{ overdueAssignmentsPages }}
                                </div>
                                <div class="flex gap-1">
                                    <button @click="overdueAssignmentsPage = Math.max(1, overdueAssignmentsPage - 1)"
                                            :disabled="overdueAssignmentsPage === 1"
                                            class="px-3 py-1 text-sm font-medium rounded-md border border-red-300 bg-white text-red-700 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Previous
                                    </button>
                                    <button
                                        @click="overdueAssignmentsPage = Math.min(overdueAssignmentsPages, overdueAssignmentsPage + 1)"
                                        :disabled="overdueAssignmentsPage === overdueAssignmentsPages"
                                        class="px-3 py-1 text-sm font-medium rounded-md border border-red-300 bg-white text-red-700 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments by Municipality -->
                    <div class="dashboard-panel overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">Assignments by Municipality</h3>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <div v-if="paginatedMunicipalities.length === 0" class="px-6 py-8 text-center">
                                <p class="text-gray-500">No assignments by municipality</p>
                            </div>
                            <div v-for="item in paginatedMunicipalities" :key="item.municipality"
                                 @click="handleMunicipalityClick(item)"
                                 class="px-6 py-4 hover:bg-gray-50 transition-colors cursor-pointer hover:shadow-sm">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-gray-900">{{ item.municipality }}</span>
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">
                                        {{ item.count }} assignment{{ item.count !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- Pagination for Municipalities -->
                        <div v-if="municipalityPages > 1" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600">
                                    Page {{ municipalityPage }} of {{ municipalityPages }}
                                </div>
                                <div class="flex gap-1">
                                    <button @click="municipalityPage = Math.max(1, municipalityPage - 1)"
                                            :disabled="municipalityPage === 1"
                                            class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Previous
                                    </button>
                                    <button
                                        @click="municipalityPage = Math.min(municipalityPages, municipalityPage + 1)"
                                        :disabled="municipalityPage === municipalityPages"
                                        class="px-3 py-1 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Link href="/uploads"
                              class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:shadow-lg hover:border-indigo-200 hover:-translate-y-0.5">
                            <div class="flex items-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-md shadow-indigo-200/40">
                                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-semibold text-gray-900">New Submission</p>
                                    <p class="text-xs text-gray-500">Upload files</p>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-gray-300 transition-transform group-hover:translate-x-0.5 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>

                        <Link href="/deadlines/municipalities"
                              class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:shadow-lg hover:border-emerald-200 hover:-translate-y-0.5">
                            <div class="flex items-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-md shadow-emerald-200/40">
                                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-semibold text-gray-900">View Deadlines</p>
                                    <p class="text-xs text-gray-500">Manage assignments</p>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-gray-300 transition-transform group-hover:translate-x-0.5 group-hover:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style src="@/../css/Centralized/Pages/Dashboard.css"></style>

