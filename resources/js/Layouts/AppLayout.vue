<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

const page = usePage();
const csrf = page.props.csrf_token;

/* ===================== SSO Session Polling ===================== */
let ssoTimer = null;
let ssoFailCount = 0;

const startSsoPolling = () => {
    const sso = page.props.sso;
    const user = page.props.auth?.user;
    if (!sso?.enabled || !sso?.serviceUrl || !user?.employee_number) return;

    ssoFailCount = 0;
    ssoTimer = setInterval(async () => {
        try {
            const res = await fetch(
                `${sso.serviceUrl}/sessions/${encodeURIComponent(user.employee_number)}`,
                { headers: { 'X-SSO-Key': sso.apiSecret || '' } }
            );
            if (res.status === 404) {
                clearInterval(ssoTimer);
                ssoTimer = null;
                window.location.href = '/sso-logout';
            }
            ssoFailCount = 0; // Reset on success
        } catch {
            ssoFailCount++;
            // Stop polling after 20 consecutive failures (microservice likely down)
            if (ssoFailCount >= 20) {
                clearInterval(ssoTimer);
                ssoTimer = null;
            }
        }
    }, 5000);
};

onMounted(() => { startSsoPolling(); });
onUnmounted(() => { if (ssoTimer) { clearInterval(ssoTimer); ssoTimer = null; } });

// Fire the logout via Inertia so it sends the live XSRF-TOKEN cookie as the
// X-XSRF-TOKEN header. A native <form> submit captures the token once at page
// render, which produces a 419 "Page Expired" whenever Laravel has rotated
// that token since (e.g. after the CAPS SSO round-trip, after idle session
// regeneration, or after any response that reset the CSRF cookie).
const logout = () => {
    router.post('/logout');
};

// Calendar state
const showCalendar = ref(false);
const calendarApi = ref(null);
const selectedDate = ref(null);
const dateAssignments = ref([]);
const showDateAssignments = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const isLoading = ref(false);

// Calendar options
const calendarOptions = ref({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    // Use function to load events
    events: function(info, successCallback, failureCallback) {
        loadCalendarEvents(info.start, info.end, successCallback, failureCallback);
    },
    eventClick: handleEventClick,
    dateClick: handleDateClick,
    eventDisplay: 'block',
    eventColor: '#059669',
    eventTextColor: '#ffffff',
    eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        meridiem: false
    },
    weekends: true,
    editable: false,
    selectable: false,
    selectMirror: true,
    dayMaxEvents: true,
    dayPopoverFormat: { month: 'long', day: 'numeric', year: 'numeric' },
    height: 'auto',
    contentHeight: 'auto',
    aspectRatio: 1.5,
    displayEventTime: false,
    allDayText: 'All day',
    /*loading: function() {
        // You could show/hide a loading indicator here if needed
    }*/
});

// Load calendar events via API with search
async function loadCalendarEvents(start, end, successCallback, failureCallback) {
    isLoading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('start', start.toISOString().split('T')[0]);
        params.append('end', end.toISOString().split('T')[0]);

        if (searchQuery.value) {
            params.append('search', searchQuery.value);
        }

        const response = await fetch(`/calendar/events?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const events = await response.json();

        // Track results so the search strip can show the count.
        searchResults.value = searchQuery.value ? events : [];

        successCallback(events);
    } catch (error) {
        console.error('Failed to load calendar events:', error);
        searchResults.value = [];
        failureCallback(error);
        successCallback([]);
    } finally {
        isLoading.value = false;
    }
}

// Search calendar events — just refetch. The function-based event source
// in calendarOptions already reads searchQuery.value and passes it to the
// API, so a refetch is all that's needed.
function searchCalendarEvents() {
    if (!calendarApi.value) return;
    calendarApi.value.refetchEvents();
}

// Clear search
function clearSearch() {
    searchQuery.value = '';
    searchResults.value = [];
    if (calendarApi.value) {
        calendarApi.value.refetchEvents();
    }
}

// UPDATED: Calendar event handlers - Now shows companies for municipality
function handleEventClick(info) {
    const event = info.event;
    const props = event.extendedProps;

    selectedDate.value = {
        date: event.startStr,
        event: props
    };

    // Prepare assignments data showing companies for this municipality
    dateAssignments.value = [{
        id: event.id,
        municipality_name: props.municipality_name,
        municipality_code: props.municipality_code || '',
        deadline_date: event.startStr,
        notes: props.notes || '',
        is_overdue: props.is_overdue,
        is_today: props.is_today,
        is_tomorrow: props.is_tomorrow,
        days_until: props.days_until,
        status: props.status || 'Deadline',
        company_count: props.company_count || 0,
        user_count: props.user_count || 0,
        // Include all companies for this municipality
        companies: props.companies || [],
        // Include assignment details if available
        assignments: props.assignments || [],
        users: props.users || [],
        all_same_user: props.all_same_user || false,
        primary_user: props.primary_user || null
    }];

    showDateAssignments.value = true;
}

function handleDateClick(info) {
    selectedDate.value = { date: info.dateStr };

    // Get all events for this date from the calendar
    if (calendarApi.value) {
        const events = calendarApi.value.getEvents();
        const eventsForDate = events.filter(event =>
            event.startStr === info.dateStr
        );

        if (eventsForDate.length > 0) {
            dateAssignments.value = eventsForDate.map(event => {
                const props = event.extendedProps;
                return {
                    id: event.id,
                    municipality_name: props.municipality_name,
                    municipality_code: props.municipality_code || '',
                    deadline_date: event.startStr,
                    notes: props.notes || '',
                    is_overdue: props.is_overdue,
                    is_today: props.is_today,
                    is_tomorrow: props.is_tomorrow,
                    days_until: props.days_until,
                    status: props.status || 'Deadline',
                    company_count: props.company_count || 0,
                    user_count: props.user_count || 0,
                    companies: props.companies || [],
                    assignments: props.assignments || [],
                    users: props.users || [],
                    all_same_user: props.all_same_user || false,
                    primary_user: props.primary_user || null
                };
            });
            showDateAssignments.value = true;
        } else {
            // Show empty state for dates with no deadlines
            dateAssignments.value = [];
            showDateAssignments.value = true;
        }
    }
}

// UPDATED: Format calendar date
const formatCalendarDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

// Format days display - show days + hours without decimals
const formatDaysDisplay = (daysUntil) => {
    if (daysUntil === null || daysUntil === undefined || Number.isNaN(Number(daysUntil))) {
        return '';
    }

    const numeric = Number(daysUntil);
    const absHours = Math.round(Math.abs(numeric) * 24);
    const days = Math.floor(absHours / 24);
    const hours = absHours % 24;

    if (numeric === 0) {
        return 'Today';
    }

    const parts = [];
    if (days > 0) {
        parts.push(`${days} day${days !== 1 ? 's' : ''}`);
    }
    if (hours > 0 || parts.length === 0) {
        parts.push(`${hours} hour${hours !== 1 ? 's' : ''}`);
    }

    const timeText = parts.join(' ');
    return numeric < 0 ? `${timeText} overdue` : `${timeText} remaining`;
};

// Close calendar popup
const closeCalendar = () => {
    showCalendar.value = false;
    searchQuery.value = '';
    searchResults.value = [];
};

// Open calendar popup - SIMPLIFIED
const openCalendar = () => {
    showCalendar.value = true;
};

// Close date assignments popup
const closeDateAssignments = () => {
    showDateAssignments.value = false;
    dateAssignments.value = [];
};

// View user details - navigate to user page
const viewUser = (userId) => {
    if (userId) {
        window.location.href = `/admin/users/${userId}`;
    }
};

// Handle calendar mounted
const handleCalendarMounted = (calendarInfo) => {
    calendarApi.value = calendarInfo;
};

// Enhanced permission check
function hasPermission(permission) {
    return page.props.auth?.user?.permissions?.includes(permission) || false;
}

function hasRole(role) {
    return page.props.auth?.user?.roles?.includes(role) || false;
}

// Check if user is authenticated
const isAuthenticated = computed(() => {
    return page.props.auth?.user !== null;
});

// Check if user has any of the upload permissions
const canViewUploads = computed(() => {
    return hasPermission('view uploads') || hasPermission('create upload');
});

// Check if user has any deadline permissions
const canViewDeadlines = computed(() => {
    return hasPermission('view deadlines') || hasPermission('manage deadlines');
});

// Check if user has any notification permissions - ALL AUTHENTICATED USERS CAN VIEW NOTIFICATIONS
const canViewNotifications = computed(() => {
    return isAuthenticated.value;
});

// Check if user has any admin permissions
const canViewAdmin = computed(() => {
    const adminPermissions = [
        'manage users', 'manage roles', 'manage permissions',
        'view companies', 'view municipalities', 'view reports',
        'view audits', 'export uploads'
    ];
    return adminPermissions.some(permission => hasPermission(permission)) || hasRole('admin');
});

const sidebarOpen = ref(false);

function isActive(path) {
    return page.url === path || page.url.startsWith(path + '/');
}

// Deadlines dropdown - only show if user has permission
const deadlinesOpen = ref(isActive('/deadlines/municipalities') || isActive('/deadlines/companies'));
watch(() => isActive('/deadlines/municipalities') || isActive('/deadlines/companies'), (val) => {
    if (val) deadlinesOpen.value = true;
});

// Admin dropdown - only show if user has permission
const adminOpen = ref(
    isActive('/admin/users') ||
    isActive('/admin/companies') ||
    isActive('/admin/municipalities') ||
    isActive('/admin/roles') ||
    isActive('/admin/reports') ||
    isActive('/admin/audits')
);
watch(() =>
        isActive('/admin/users') ||
        isActive('/admin/companies') ||
        isActive('/admin/municipalities') ||
        isActive('/admin/roles') ||
        isActive('/admin/reports') ||
        isActive('/admin/audits'),
    (val) => {
        if (val) adminOpen.value = true;
    });
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- ===== Calendar Modal ===== -->
        <Teleport to="body">
        <div v-if="showCalendar" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 transition-all">
            <div class="flex w-full max-w-6xl max-h-[92vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <!-- Header -->
                <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100">
                            <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-slate-800">Deadline Calendar</h3>
                            <p class="text-xs text-slate-500">Click any date or event to view details</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-5">
                        <!-- Legend -->
                        <div class="hidden items-center gap-4 text-[11px] font-medium text-slate-500 sm:flex">
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Overdue</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Today</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-yellow-400"></span>Tomorrow</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Upcoming</span>
                        </div>
                        <button @click="closeCalendar" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Search strip -->
                <div class="flex shrink-0 items-center gap-2 border-b border-slate-100 bg-white px-6 py-2.5">
                    <div class="relative flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input v-model="searchQuery" @keyup.enter="searchCalendarEvents" type="search"
                               placeholder="Search municipalities or users..."
                               class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-8 text-sm text-slate-700 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400">
                        <button v-if="searchQuery" @click="clearSearch" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <button @click="searchCalendarEvents" :disabled="isLoading"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50">
                        <svg v-if="isLoading" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                        <span v-else>Search</span>
                    </button>
                    <span v-if="searchResults.length" class="text-xs text-slate-500">{{ searchResults.length }} results</span>
                </div>

                <!-- Calendar body -->
                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <FullCalendar v-if="showCalendar" :options="calendarOptions"
                        @eventClick="handleEventClick" @dateClick="handleDateClick"
                        @mounted="handleCalendarMounted" class="calendar-container"/>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ===== Date / Event Detail Modal ===== -->
        <Teleport to="body">
        <div v-if="showDateAssignments" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="flex w-full max-w-3xl max-h-[88vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <!-- Header -->
                <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">
                            {{ selectedDate.event?.municipality_name || formatCalendarDate(selectedDate.date) }}
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <template v-if="dateAssignments.length">
                                {{ dateAssignments[0].company_count }} compan{{ dateAssignments[0].company_count !== 1 ? 'ies' : 'y' }},
                                {{ dateAssignments[0].user_count }} user{{ dateAssignments[0].user_count !== 1 ? 's' : '' }}
                            </template>
                            <template v-else>{{ formatCalendarDate(selectedDate.date) }}</template>
                        </p>
                    </div>
                    <button @click="closeDateAssignments" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <!-- Empty -->
                    <div v-if="!dateAssignments.length" class="py-12 text-center">
                        <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="mt-3 text-sm text-slate-500">No deadlines or assignments for this date.</p>
                    </div>

                    <!-- Assignments list -->
                    <div v-else class="space-y-5">
                        <div v-for="a in dateAssignments" :key="a.id"
                             class="overflow-hidden rounded-xl border"
                             :class="a.is_overdue ? 'border-rose-200' : a.is_today ? 'border-amber-200' : a.is_tomorrow ? 'border-yellow-200' : 'border-emerald-200'">

                            <!-- Card header -->
                            <div class="flex items-center justify-between px-5 py-3"
                                 :class="a.is_overdue ? 'bg-rose-50' : a.is_today ? 'bg-amber-50' : a.is_tomorrow ? 'bg-yellow-50' : 'bg-emerald-50'">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">{{ a.municipality_code || a.municipality_name }}</h4>
                                    <p class="text-xs text-slate-500">{{ formatCalendarDate(a.deadline_date) }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                      :class="a.is_overdue ? 'bg-rose-100 text-rose-700' : a.is_today ? 'bg-amber-100 text-amber-700' : a.is_tomorrow ? 'bg-yellow-100 text-yellow-700' : 'bg-emerald-100 text-emerald-700'">
                                    {{ formatDaysDisplay(a.days_until) }}
                                </span>
                            </div>

                            <div class="divide-y divide-slate-100 bg-white">
                                <!-- Users -->
                                <div v-if="a.users?.length" class="px-5 py-3">
                                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Assigned Users</p>
                                    <div v-if="a.all_same_user && a.primary_user" class="flex items-center gap-2.5">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ a.primary_user.name?.charAt(0) || 'U' }}</div>
                                        <div class="text-sm"><span class="font-medium text-slate-700">{{ a.primary_user.name }}</span> <span class="text-slate-400">(all {{ a.company_count }} companies)</span></div>
                                    </div>
                                    <div v-else class="flex flex-wrap gap-2">
                                        <div v-for="u in a.users" :key="u.id" class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-600">{{ u.name?.charAt(0) || 'U' }}</div>
                                            <span class="text-xs font-medium text-slate-700">{{ u.name }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Companies -->
                                <div v-if="a.companies?.length" class="px-5 py-3">
                                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Companies ({{ a.company_count }})</p>
                                    <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                        <div v-for="c in a.companies" :key="c.id" class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-sm hover:bg-slate-50">
                                            <span class="font-medium text-slate-700">{{ c.name }}</span>
                                            <template v-if="a.assignments?.length">
                                                <template v-for="ass in a.assignments" :key="ass.id">
                                                    <span v-if="ass.company_id === c.id" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ ass.user_name }}</span>
                                                </template>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="px-5 py-4 text-center text-xs text-slate-400">No companies for this municipality.</div>

                                <!-- Notes -->
                                <div v-if="a.notes" class="px-5 py-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Notes</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ a.notes }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex shrink-0 justify-end border-t border-slate-100 bg-slate-50 px-6 py-3">
                    <button @click="closeDateAssignments" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-200">Close</button>
                </div>
            </div>
        </div>
        </Teleport>

        <div class="flex">
            <!-- Sidebar -->
            <aside
                class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-slate-300 bg-white shadow-md transition-transform duration-300 ease-in-out md:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            >
                <div class="sidebar-surface sticky top-0 flex h-screen w-full flex-col p-6">
                    <!-- Mobile header -->
                    <div class="mb-4 flex items-center justify-between md:hidden">
                        <img
                            :src="'/images/casey_logo.png'"
                            alt="Casey & Associates"
                            class="max-h-12 w-40 object-contain"
                        />
                        <button class="rounded-lg p-2 text-slate-700 hover:bg-slate-100" @click="sidebarOpen = false">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Desktop logo -->
                    <div class="hidden items-center justify-center md:flex">
                        <img
                            :src="'/images/casey_logo.png'"
                            alt="Casey & Associates"
                            class="max-h-14 w-full object-contain"
                        />
                    </div>

                    <!-- Navigation -->
                    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto">
                        <!-- Dashboard -->
                        <Link
                            href="/dashboard"
                            :class="[
                                'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition nav-outline',
                                isActive('/dashboard')
                                    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-300'
                                    : 'text-slate-700 hover:bg-slate-100',
                            ]"
                        >
                            <!-- left green rail -->
                            <span
                                v-if="isActive('/dashboard')"
                                class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-green-600"
                            />
                            <!-- icon tile -->
                            <span
                                class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 transition group-hover:bg-slate-200"
                                :class="isActive('/dashboard') ? 'bg-green-50 text-green-700 ring-green-200' : ''"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6" />
                                </svg>
                            </span>
                            Dashboard
                        </Link>

                        <!-- Uploads - Only show if user has permission -->
                        <template v-if="canViewUploads">
                            <Link
                                href="/uploads"
                                :class="[
                                    'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition nav-outline',
                                    page.url === '/uploads'
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-300'
                                        : 'text-slate-700 hover:bg-slate-100',
                                ]"
                            >
                                <span v-if="page.url === '/uploads'"
                                      class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-green-600" />
                                <span
                                    class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 transition group-hover:bg-slate-200"
                                    :class="page.url === '/uploads' ? 'bg-green-50 text-green-700 ring-green-200' : ''"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5m0 0l5 5m-5-5v12" />
                                    </svg>
                                </span>
                                Uploads
                            </Link>

                            <Link
                                href="/uploads/history"
                                :class="[
                                    'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition nav-outline',
                                    page.url === '/uploads/history'
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-300'
                                        : 'text-slate-700 hover:bg-slate-100',
                                ]"
                            >
                                <span v-if="page.url === '/uploads/history'"
                                      class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-green-600" />
                                <span
                                    class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 transition group-hover:bg-slate-200"
                                    :class="page.url === '/uploads/history' ? 'bg-green-50 text-green-700 ring-green-200' : ''"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                Upload History
                            </Link>
                        </template>

                        <!-- Deadlines Dropdown - Only show if user has permission -->
                        <div v-if="canViewDeadlines">
                            <button
                                @click="deadlinesOpen = !deadlinesOpen"
                                :class="[
                                    'group relative flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition nav-outline',
                                    (isActive('/deadlines/municipalities') || isActive('/deadlines/companies'))
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-300'
                                        : 'text-slate-700 hover:bg-slate-100',
                                ]"
                            >
                                <span
                                    v-if="(isActive('/deadlines/municipalities') || isActive('/deadlines/companies'))"
                                    class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-green-600"
                                />
                                <span class="flex items-center gap-3">
                                    <span
                                        class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 transition group-hover:bg-slate-200"
                                        :class="(isActive('/deadlines/municipalities') || isActive('/deadlines/companies')) ? 'bg-green-50 text-green-700 ring-green-200' : ''"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 7V3m8 4V3M5 11h14M5 7h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z" />
                                        </svg>
                                    </span>
                                    <span>Deadlines</span>
                                </span>

                                <svg
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="{ 'rotate-180': deadlinesOpen }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 -translate-y-2"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 -translate-y-2"
                            >
                                <div v-if="deadlinesOpen" class="mt-1 ml-12 space-y-1">
                                    <Link
                                        href="/deadlines/municipalities"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/deadlines/municipalities')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Municipalities
                                    </Link>
                                    <Link
                                        href="/deadlines/companies"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/deadlines/companies')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Companies
                                    </Link>
                                </div>
                            </transition>
                        </div>

                        <!-- Notifications - Show for ALL authenticated users -->
                        <Link
                            v-if="canViewNotifications"
                            href="/notifications"
                            :class="[
                                'group relative flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition nav-outline',
                                isActive('/notifications')
                                    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-300'
                                    : 'text-slate-700 hover:bg-slate-100',
                            ]"
                        >
                            <span v-if="isActive('/notifications')"
                                  class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-green-600" />
                            <span class="flex items-center gap-3">
                                <span
                                    class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 transition group-hover:bg-slate-200"
                                    :class="isActive('/notifications') ? 'bg-green-50 text-green-700 ring-green-200' : ''"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                </span>
                                <span>Notifications</span>
                            </span>

                            <span
                                v-if="page.props.notifications?.unread_count > 0"
                                class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-bold text-white ring-2 ring-white"
                            >
                                {{ page.props.notifications.unread_count > 99 ? '99+' : page.props.notifications.unread_count
                                }}
                            </span>
                        </Link>

                        <!-- Support Tickets - All authenticated users -->
                        <Link
                            href="/support"
                            :class="[
                                'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition nav-outline',
                                isActive('/support')
                                    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-300'
                                    : 'text-slate-700 hover:bg-slate-100',
                            ]"
                        >
                            <span v-if="isActive('/support')"
                                  class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-violet-600" />
                            <span class="flex items-center gap-3">
                                <span
                                    class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 transition group-hover:bg-slate-200"
                                    :class="isActive('/support') ? 'bg-violet-50 text-violet-700 ring-violet-200' : ''"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                    </svg>
                                </span>
                                <span>Support</span>
                            </span>
                        </Link>

                        <!-- Admin Dropdown - Only show if user has permission -->
                        <div v-if="canViewAdmin">
                            <button
                                @click="adminOpen = !adminOpen"
                                :class="[
                                    'group relative flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-[15px] font-medium transition nav-outline',
                                    (isActive('/admin/users') || isActive('/admin/companies') ||
                                     isActive('/admin/municipalities') || isActive('/admin/roles') ||
                                     isActive('/admin/reports') || isActive('/admin/audits'))
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-300'
                                        : 'text-slate-700 hover:bg-slate-100',
                                ]"
                            >
                                <span
                                    v-if="(isActive('/admin/users') || isActive('/admin/companies') ||
                                           isActive('/admin/municipalities') || isActive('/admin/roles') ||
                                           isActive('/admin/reports') || isActive('/admin/audits'))"
                                    class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-green-600"
                                />
                                <span class="flex items-center gap-3">
                                    <span
                                        class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 transition group-hover:bg-slate-200"
                                        :class="(isActive('/admin/users') || isActive('/admin/companies') || isActive('/admin/municipalities') || isActive('/admin/roles') || isActive('/admin/reports') || isActive('/admin/audits')) ? 'bg-green-50 text-green-700 ring-green-200' : ''"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35.91-.221 1.497-1.134 1.066-2.573-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </span>
                                    <span>Admin</span>
                                </span>

                                <svg
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="{ 'rotate-180': adminOpen }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 -translate-y-2"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 -translate-y-2"
                            >
                                <div v-if="adminOpen" class="mt-1 ml-12 space-y-1">
                                    <Link
                                        v-if="hasPermission('manage users')"
                                        href="/admin/users"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/admin/users')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Users
                                    </Link>
                                    <Link
                                        v-if="hasPermission('view companies')"
                                        href="/admin/companies"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/admin/companies')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Companies
                                    </Link>
                                    <Link
                                        v-if="hasPermission('view municipalities')"
                                        href="/admin/municipalities"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/admin/municipalities')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Municipalities
                                    </Link>
                                    <Link
                                        v-if="hasPermission('manage roles') || hasPermission('manage permissions')"
                                        href="/admin/roles"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/admin/roles')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Roles/Permissions
                                    </Link>
                                    <Link
                                        v-if="hasPermission('view reports')"
                                        href="/admin/reports"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/admin/reports')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Reports
                                    </Link>
                                    <Link
                                        v-if="hasPermission('view audits')"
                                        href="/admin/audits"
                                        :class="[
                                            'block rounded-lg px-3 py-1.5 text-sm transition ring-1',
                                            isActive('/admin/audits')
                                                ? 'bg-green-50 text-green-800 ring-green-200'
                                                : 'bg-white/70 text-slate-700 ring-slate-300 hover:bg-slate-100',
                                        ]"
                                    >
                                        Audits
                                    </Link>
                                </div>
                            </transition>
                        </div>
                    </nav>

                    <!-- User section -->
                    <div class="border-t border-slate-300/80 pt-4">
                        <div
                            class="mb-3 rounded-2xl border border-slate-300 bg-white p-4 shadow-sm transition hover:shadow">
                            <div class="flex items-center gap-3">
                                <div
                                    class="grid h-10 w-10 place-content-center rounded-xl bg-green-600 text-sm font-semibold text-white shadow-sm">
                                    {{
                                        (page.props.auth?.user?.name ?? 'User')
                                            .split(' ')
                                            .map((n) => n[0])
                                            .slice(0, 2)
                                            .join('')
                                    }}
                                </div>
                                <div class="min-w-0 flex-1 leading-tight">
                                    <p class="truncate font-semibold text-slate-900">
                                        {{ page.props.auth?.user?.name ?? 'Guest' }}
                                    </p>
                                    <p class="truncate text-xs text-slate-600">
                                        {{ page.props.auth?.user?.roles?.join(', ') || 'No roles' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="logout"
                            class="flex w-full items-center gap-3 rounded-xl border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H3" />
                            </svg>
                            Logout
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="max-w-full flex-1 overflow-x-hidden md:ml-64">
                <!-- Header -->
                <header class="sticky top-0 z-30 border-b border-slate-300 bg-white/95 shadow-sm backdrop-blur-sm">
                    <div class="flex w-full items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            <button
                                class="rounded-lg p-2 hover:bg-slate-100 md:hidden"
                                @click="sidebarOpen = !sidebarOpen"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>

                            <div>
                                <h1 class="text-lg leading-tight font-bold text-slate-900">
                                    Premium Submissions Platform
                                </h1>
                                <p class="text-xs text-slate-600">
                                    Manage bulk deductions & third-party collections
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <!-- Notification Bell in Header - Show for all users -->
                            <Link
                                v-if="canViewNotifications"
                                href="/notifications"
                                class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span
                                    v-if="page.props.notifications?.unread_count > 0"
                                    class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                                >
                                    {{ page.props.notifications.unread_count > 9 ? '9+' : page.props.notifications.unread_count
                                    }}
                                </span>
                            </Link>

                            <!-- Calendar Button -->
                            <button
                                @click="openCalendar"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                View Calendar
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <div class="max-w-full overflow-x-auto px-6 py-6">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped src="@/../css/Centralized/Layouts/AppLayout.css"></style>

