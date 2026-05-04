<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = defineProps({
    notifications: Object,
    unreadCount: Number,
    users: Array,
    selectedUserName: String,
    isAdmin: Boolean,
    filters: Object,
});

const filters = ref({
    type: props.filters?.type || '',
    search: props.filters?.search || '',
    user_id: props.filters?.user_id || '',
    per_page: Number(props.filters?.per_page || 20),
});

const perPageOptions = [10, 20, 50, 100];
const showFilters = ref(Boolean(props.filters?.type || props.filters?.search || props.filters?.user_id));
const expandedNotifications = ref(new Set());
const activeReadFilter = ref(props.filters?.read_status || 'all');

const notificationTypes = [
    { value: '', label: 'All Types' },
    { value: 'upload_created', label: 'Upload Created' },
    { value: 'deadline_created', label: 'Deadline Created' },
    { value: 'deadline_assigned', label: 'Assignment' },
    { value: 'deadline_updated', label: 'Deadline Updated' },
    { value: 'deadline_deleted', label: 'Deadline Deleted' },
    { value: 'assignment_removed', label: 'Assignment Removed' },
    { value: 'new_upload', label: 'New Upload' },
];

// Helper to properly access notification data
const getNotificationData = (notification) => {
    // Laravel stores notification data in the 'data' field
    if (notification.data && typeof notification.data === 'object') {
        return notification.data;
    }
    // If it's a string, parse it
    if (notification.data && typeof notification.data === 'string') {
        try {
            return JSON.parse(notification.data);
        } catch {
            return { message: notification.data };
        }
    }
    // Fallback to the notification itself
    return notification;
};

// Format days display - FIXED: Show whole days properly
const formatDaysDisplay = (daysUntil) => {
    if (daysUntil === undefined || daysUntil === null) return '';

    const wholeDays =
        daysUntil > 0
            ? Math.ceil(daysUntil)
            : Math.floor(daysUntil);

    if (wholeDays < 0) {
        const daysOverdue = Math.abs(wholeDays);
        return `${daysOverdue} day${daysOverdue !== 1 ? 's' : ''} overdue`;
    } else if (wholeDays === 0) {
        return 'Today';
    } else if (wholeDays === 1) {
        return 'Tomorrow';
    } else {
        return `${wholeDays} days`;
    }
};


// Get days status for styling
const getDaysStatus = (daysUntil) => {
    if (daysUntil === undefined || daysUntil === null) return 'neutral';

    if (daysUntil < 0) return 'overdue';
    if (daysUntil === 0) return 'today';
    if (daysUntil === 1) return 'tomorrow';
    if (daysUntil <= 3) return 'urgent';
    if (daysUntil <= 7) return 'upcoming';
    return 'future';
};

// Get days status color
const getDaysStatusColor = (daysUntil) => {
    const status = getDaysStatus(daysUntil);
    switch (status) {
        case 'overdue': return 'bg-red-100 text-red-800';
        case 'today': return 'bg-orange-100 text-orange-800';
        case 'tomorrow': return 'bg-yellow-100 text-yellow-800';
        case 'urgent': return 'bg-amber-100 text-amber-800';
        case 'upcoming': return 'bg-blue-100 text-blue-800';
        default: return 'bg-green-100 text-green-800';
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatNotificationDate = (dateString) => {
    if (!dateString) return '';

    // Laravel timestamps come as "YYYY-MM-DD HH:MM:SS" without a timezone
    // suffix. Append 'Z' if there's no timezone indicator so JS parses it
    // consistently as UTC (the server stores UTC).  If the string already
    // contains 'T', 'Z', or a +/- offset, leave it alone.
    let normalized = dateString;
    if (!/[TZ+\-]/.test(dateString)) {
        normalized = dateString.replace(' ', 'T') + 'Z';
    }

    const date = new Date(normalized);
    if (isNaN(date.getTime())) return dateString;

    const now = new Date();
    const diffMs = now - date;

    // Guard against future dates (clock skew)
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
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }
};

const getNotificationColor = (type) => {
    const colors = {
        upload_created: 'bg-green-100 text-green-800 border-green-200',
        deadline_created: 'bg-blue-100 text-blue-800 border-blue-200',
        deadline_assigned: 'bg-purple-100 text-purple-800 border-purple-200',
        deadline_updated: 'bg-amber-100 text-amber-800 border-amber-200',
        deadline_deleted: 'bg-red-100 text-red-800 border-red-200',
        assignment_removed: 'bg-gray-100 text-gray-800 border-gray-200',
        new_upload: 'bg-orange-100 text-orange-800 border-orange-200',
    };
    return colors[type] || 'bg-gray-100 text-gray-800 border-gray-200';
};

const getNotificationTypeLabel = (type) => {
    const labels = {
        'upload_created': 'Upload Created',
        'deadline_created': 'Deadline Created',
        'deadline_assigned': 'Assignment',
        'deadline_updated': 'Deadline Updated',
        'deadline_deleted': 'Deadline Deleted',
        'assignment_removed': 'Assignment Removed',
        'new_upload': 'New Upload',
    };
    return labels[type] || 'Notification';
};

const getNotificationIcon = (type) => {
    const icons = {
        'upload_created': `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        `,
        'deadline_created': `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        `,
        'deadline_assigned': `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-8.994L20 7m-7-4v4m0 4v4" />
        `,
        'deadline_updated': `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        `,
        'deadline_deleted': `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        `,
        'assignment_removed': `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197" />
        `,
        'new_upload': `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        `,
    };
    return icons[type] || `
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    `;
};

const getNotificationIconBg = (type) => {
    const colors = {
        'upload_created': 'bg-green-100',
        'deadline_created': 'bg-blue-100',
        'deadline_assigned': 'bg-purple-100',
        'deadline_updated': 'bg-amber-100',
        'deadline_deleted': 'bg-red-100',
        'assignment_removed': 'bg-gray-100',
        'new_upload': 'bg-orange-100',
    };
    return colors[type] || 'bg-gray-100';
};

const getNotificationIconColor = (type) => {
    const colors = {
        'upload_created': 'text-green-600',
        'deadline_created': 'text-blue-600',
        'deadline_assigned': 'text-purple-600',
        'deadline_updated': 'text-amber-600',
        'deadline_deleted': 'text-red-600',
        'assignment_removed': 'text-gray-600',
        'new_upload': 'text-orange-600',
    };
    return colors[type] || 'text-gray-600';
};

// Toast notifications
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');

const showNotification = (message, type = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

// Watch for flash messages
watch(
    () => page.props.flash,
    (newFlash) => {
        if (newFlash?.success) {
            showNotification(newFlash.success, 'success');
        }
        if (newFlash?.error) {
            showNotification(newFlash.error, 'error');
        }
    },
    { immediate: true }
);

// Navigate to user profile
const viewUserProfile = (userId) => {
    if (userId) {
        window.open(`/admin/users/${userId}`, '_blank');
    }
};

// Navigate to municipality deadlines
const viewMunicipalityDeadlines = (municipalityId, date = null) => {
    let url = '/deadlines/municipalities';
    const params = [];

    if (municipalityId) {
        params.push(`municipality_id=${municipalityId}`);
    }

    if (date) {
        params.push(`date=${date}`);
    }

    if (params.length > 0) {
        url += `?${params.join('&')}`;
    }

    router.visit(url);
};

// Navigate to company page
const viewCompany = (companyId) => {
    if (companyId) {
        router.visit(`/deadlines/companies?company_id=${companyId}`);
    }
};

const markAsRead = (id) => {
    router.post(
        `/notifications/${id}/mark-as-read`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showNotification('Notification marked as read', 'success');
                router.reload({ only: ['notifications', 'unreadCount'] });
            },
            onError: () => {
                showNotification('Failed to mark notification as read', 'error');
            },
        },
    );
};

const markAllAsRead = () => {
    if (unreadCount.value === 0) {
        showNotification('No unread notifications', 'info');
        return;
    }

    router.post(
        '/notifications/mark-all-as-read',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showNotification('All notifications marked as read', 'success');
                router.reload({ only: ['notifications', 'unreadCount'] });
            },
            onError: () => {
                showNotification('Failed to mark notifications as read', 'error');
            },
        },
    );
};

const deleteNotification = (id) => {
    if (confirm('Are you sure you want to delete this notification?')) {
        router.delete(`/notifications/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showNotification('Notification deleted', 'success');
                router.reload({ only: ['notifications', 'unreadCount'] });
            },
            onError: () => {
                showNotification('Failed to delete notification', 'error');
            },
        });
    }
};

const clearAll = () => {
    if (props.notifications.data.length === 0) {
        showNotification('No notifications to clear', 'info');
        return;
    }

    if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
        router.delete('/notifications/clear-all', {
            preserveScroll: true,
            onSuccess: () => {
                showNotification('All notifications cleared', 'success');
                router.reload({only: ['notifications', 'unreadCount']});
            },
            onError: () => {
                showNotification('Failed to clear notifications', 'error');
            },
        });
    }
};

const applyFilters = () => {
    router.get('/notifications', {
        ...filters.value,
        read_status: activeReadFilter.value !== 'all' ? activeReadFilter.value : '',
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filters.value = {
        type: '',
        search: '',
        user_id: '',
        per_page: 20,
    };
    activeReadFilter.value = 'all';
    applyFilters();
};

const formatNotificationMessage = (notification) => {
    const data = getNotificationData(notification);
    const message = data?.message || 'Notification';
    const decimalDaysPattern = /\((-?\d+(?:\.\d+)?)\s+days\s+remaining\)/i;
    const match = message.match(decimalDaysPattern);

    if (!match) {
        return message;
    }

    const value = Number(match[1]);
    if (Number.isNaN(value)) {
        return message;
    }

    const totalHours = Math.max(0, Math.round(Math.abs(value) * 24));
    const days = Math.floor(totalHours / 24);
    const hours = totalHours % 24;
    const dayLabel = `${days} day${days === 1 ? '' : 's'}`;
    const hourLabel = `${hours} hour${hours === 1 ? '' : 's'}`;
    const suffix = value < 0 ? 'overdue' : 'remaining';

    return message.replace(decimalDaysPattern, `(${dayLabel} and ${hourLabel} ${suffix})`);
};

const toggleNotificationDetails = (id) => {
    const next = new Set(expandedNotifications.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedNotifications.value = next;
};

const isNotificationExpanded = (id) => expandedNotifications.value.has(id);

const hasExtendedDetails = (notification) => {
    const data = getNotificationData(notification);
    return Boolean(
        data?.municipality_name ||
        data?.company_name ||
        data?.deadline_date ||
        data?.assignment_summary ||
        data?.company_count ||
        data?.total_count > 1 ||
        data?.reference
    );
};

const notificationsData = computed(() => props.notifications?.data || []);

const unreadCount = computed(() => {
    return props.unreadCount || 0;
});

// Auto-mark as read when page loads with parameter
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('mark_as_read')) {
        markAllAsRead();
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
                      : toastType === 'info'
                        ? 'border-blue-500 bg-blue-50 text-blue-800'
                        : 'border-yellow-500 bg-yellow-50 text-yellow-800',
            ]"
        >
            <div class="flex items-center">
                <svg v-if="toastType === 'success'" class="h-5 w-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else-if="toastType === 'error'" class="h-5 w-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <svg v-else class="h-5 w-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">{{ toastMessage }}</span>
                <button @click="showToast = false" class="ml-4 text-gray-500 hover:text-gray-700">
                    ×
                </button>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Notifications</h1>
                        <p class="mt-2 text-gray-600">Stay updated with recent activities and deadlines</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-600">
                            <span class="font-semibold">{{ unreadCount }}</span> unread notification{{ unreadCount !== 1 ? 's' : '' }}
                        </span>
                        <div class="flex items-center space-x-2">
                            <button
                                @click="markAllAsRead"
                                :disabled="unreadCount === 0"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Mark All Read
                            </button>
                            <button
                                @click="clearAll"
                                :disabled="notifications.data.length === 0"
                                class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Clear All
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Admin user selection -->
                <div v-if="isAdmin && users.length > 0" class="mt-4">
                    <div class="text-sm text-gray-600">
                        <span v-if="selectedUserName">
                            Viewing notifications for: <strong>{{ selectedUserName }}</strong>
                            <button @click="clearFilters" class="ml-2 text-blue-600 hover:text-blue-800 underline">
                                (Show all users)
                            </button>
                        </span>
                        <span v-else>Viewing notifications for all users</span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900">Filter Notifications</h3>
                    <button
                        @click="showFilters = !showFilters"
                        class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                    </button>
                </div>
                <div v-if="showFilters" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <!-- Type filter -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Filter by Type</label>
                        <select
                            v-model="filters.type"
                            @change="applyFilters"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                            <option
                                v-for="type in notificationTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                    </div>

                    <!-- User filter (admin only) -->
                    <div v-if="isAdmin">
                        <label class="mb-2 block text-sm font-medium text-gray-700">Filter by User</label>
                        <select
                            v-model="filters.user_id"
                            @change="applyFilters"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                            <option value="">All Users</option>
                            <option
                                v-for="user in users"
                                :key="user.id"
                                :value="user.id"
                            >
                                {{ user.name }} ({{ user.email }})
                            </option>
                        </select>
                    </div>

                    <!-- Search filter -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Search Notifications</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                v-model="filters.search"
                                @keyup.enter="applyFilters"
                                placeholder="Search by message, municipality, company, or user..."
                                class="w-full rounded-lg border border-gray-300 pl-10 pr-3 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Rows Per Page</label>
                        <select
                            v-model="filters.per_page"
                            @change="applyFilters"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                            <option v-for="opt in perPageOptions" :key="opt" :value="opt">
                                {{ opt }} rows
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Filter actions -->
                <div v-if="showFilters" class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="clearFilters"
                        class="rounded-lg bg-gray-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-gray-700"
                    >
                        Reset Filters
                    </button>
                    <button
                        @click="applyFilters"
                        class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Read/Unread Quick Toggle Tabs -->
            <div class="mb-4 flex items-center gap-2">
                <button
                    @click="activeReadFilter = 'all'; applyFilters()"
                    :class="[
                        'rounded-full px-4 py-1.5 text-sm font-medium transition-all',
                        activeReadFilter === 'all'
                            ? 'bg-gray-900 text-white shadow-sm'
                            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    All ({{ notifications.total || 0 }})
                </button>
                <button
                    @click="activeReadFilter = 'unread'; applyFilters()"
                    :class="[
                        'rounded-full px-4 py-1.5 text-sm font-medium transition-all',
                        activeReadFilter === 'unread'
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    <span class="flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75" v-if="unreadCount > 0"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                        </span>
                        Unread ({{ unreadCount }})
                    </span>
                </button>
                <button
                    @click="activeReadFilter = 'read'; applyFilters()"
                    :class="[
                        'rounded-full px-4 py-1.5 text-sm font-medium transition-all',
                        activeReadFilter === 'read'
                            ? 'bg-gray-600 text-white shadow-sm'
                            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Read
                </button>
            </div>

            <!-- Notifications List -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow">
                <!-- Header -->
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <span class="text-sm font-medium text-gray-700">
                            Showing {{ notificationsData.length }} of
                            {{ notifications.total || 0 }} notifications
                        </span>
                        <div class="text-sm text-gray-500">
                            Page {{ notifications.current_page || 1 }} of {{ notifications.last_page || 1 }}
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="notificationsData.length === 0" class="py-16 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-medium text-gray-900">No notifications found</h3>
                    <p class="mb-6 text-gray-500">
                        {{
                            filters.type || filters.search || filters.user_id
                                ? 'Try adjusting your filters'
                                : 'You\'re all caught up!'
                        }}
                    </p>
                    <button
                        v-if="filters.type || filters.search || filters.user_id"
                        @click="clearFilters"
                        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                    >
                        Clear Filters
                    </button>
                </div>

                <!-- Notifications -->
                <div v-else class="max-h-[68vh] overflow-y-auto divide-y divide-gray-100">
                    <div
                        v-for="notification in notificationsData"
                        :key="notification.id"
                        :class="[
                            'notification-item relative p-5 transition-all duration-200 cursor-pointer group',
                            notification.read_at
                                ? 'bg-white hover:bg-gray-50'
                                : 'bg-gradient-to-r from-blue-50 via-blue-50/60 to-white border-l-4 border-l-blue-500 hover:from-blue-100/80 hover:via-blue-50/80 hover:to-white',
                        ]"
                        @click="!notification.read_at && markAsRead(notification.id)"
                    >
                        <!-- Unread pulse indicator dot -->
                        <div
                            v-if="!notification.read_at"
                            class="absolute top-5 right-16 flex items-center"
                        >
                            <span class="relative flex h-3 w-3">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-blue-500"></span>
                            </span>
                        </div>

                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-1 items-start space-x-4">
                                <!-- Icon with read/unread ring -->
                                <div class="flex-shrink-0 relative">
                                    <div
                                        :class="[
                                            'h-11 w-11 rounded-full flex items-center justify-center transition-all duration-200',
                                            notification.read_at
                                                ? getNotificationIconBg(getNotificationData(notification).type) + ' opacity-60'
                                                : getNotificationIconBg(getNotificationData(notification).type) + ' ring-2 ring-offset-2 ring-blue-300 shadow-sm',
                                        ]"
                                    >
                                        <svg
                                            :class="[
                                                'h-5 w-5 transition-all',
                                                notification.read_at
                                                    ? getNotificationIconColor(getNotificationData(notification).type) + ' opacity-50'
                                                    : getNotificationIconColor(getNotificationData(notification).type),
                                            ]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <g v-html="getNotificationIcon(getNotificationData(notification).type)"></g>
                                        </svg>
                                    </div>
                                    <!-- Read checkmark overlay -->
                                    <div
                                        v-if="notification.read_at"
                                        class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-gray-400 ring-2 ring-white"
                                    >
                                        <svg class="h-2.5 w-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="min-w-0 flex-1">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                                getNotificationColor(getNotificationData(notification).type),
                                            ]"
                                        >
                                            {{ getNotificationTypeLabel(getNotificationData(notification).type) }}
                                        </span>
                                        <span
                                            v-if="!notification.read_at"
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-600 px-2.5 py-0.5 text-xs font-semibold text-white shadow-sm"
                                        >
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                                                <circle cx="4" cy="4" r="3" />
                                            </svg>
                                            NEW
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Read
                                        </span>
                                        <span
                                            v-if="getNotificationData(notification).user_name && isAdmin"
                                            class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800"
                                        >
                                            {{ getNotificationData(notification).user_name }}
                                        </span>
                                    </div>

                                    <p :class="[
                                        'mb-1 transition-colors',
                                        notification.read_at
                                            ? 'font-normal text-gray-500'
                                            : 'font-semibold text-gray-900',
                                    ]">
                                        {{ formatNotificationMessage(notification) }}
                                    </p>

                                    <!-- Additional data -->
                                    <div v-if="isNotificationExpanded(notification.id)" class="mt-3 space-y-2">
                                        <!-- Municipality - Clickable -->
                                        <div
                                            v-if="getNotificationData(notification).municipality_name"
                                            class="flex items-center text-sm text-gray-600"
                                        >
                                            <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            Municipality:
                                            <button
                                                v-if="getNotificationData(notification).municipality_id"
                                                @click="viewMunicipalityDeadlines(getNotificationData(notification).municipality_id, getNotificationData(notification).deadline_date)"
                                                class="ml-1 text-blue-600 hover:text-blue-800 hover:underline"
                                            >
                                                {{ getNotificationData(notification).municipality_name }}
                                            </button>
                                            <span v-else class="ml-1">{{ getNotificationData(notification).municipality_name }}</span>
                                        </div>

                                        <!-- Company - Clickable -->
                                        <div
                                            v-if="getNotificationData(notification).company_name"
                                            class="flex items-center text-sm text-gray-600"
                                        >
                                            <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            Company:
                                            <button
                                                v-if="getNotificationData(notification).company_id"
                                                @click="viewCompany(getNotificationData(notification).company_id)"
                                                class="ml-1 text-blue-600 hover:text-blue-800 hover:underline"
                                            >
                                                {{ getNotificationData(notification).company_name }}
                                            </button>
                                            <span v-else class="ml-1">{{ getNotificationData(notification).company_name }}</span>
                                        </div>

                                        <!-- Deadline with days display -->
                                        <div
                                            v-if="getNotificationData(notification).deadline_date"
                                            class="flex items-center text-sm text-gray-600"
                                        >
                                            <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Deadline: {{ formatDate(getNotificationData(notification).deadline_date) }}
                                            <span
                                                v-if="getNotificationData(notification).days_until_deadline !== undefined"
                                                :class="[
                                                    'ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                    getDaysStatusColor(getNotificationData(notification).days_until_deadline)
                                                ]"
                                            >
                                                {{ formatDaysDisplay(getNotificationData(notification).days_until_deadline) }}
                                            </span>
                                        </div>

                                        <!-- Assignment Information -->
                                        <div
                                            v-if="getNotificationData(notification).assignment_summary"
                                            class="flex items-center text-sm text-gray-600"
                                        >
                                            <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-8.994L20 7m-7-4v4m0 4v4" />
                                            </svg>
                                            {{ getNotificationData(notification).assignment_summary }}

                                            <!-- Show assigned user if available and clickable for admins -->
                                            <button
                                                v-if="getNotificationData(notification).assigned_user_name && getNotificationData(notification).assigned_user_id && isAdmin"
                                                @click="viewUserProfile(getNotificationData(notification).assigned_user_id)"
                                                class="ml-1 text-blue-600 hover:text-blue-800 hover:underline flex items-center"
                                            >
                                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                {{ getNotificationData(notification).assigned_user_name }}
                                            </button>
                                            <span
                                                v-else-if="getNotificationData(notification).assigned_user_name"
                                                class="ml-1 font-medium"
                                            >
                                                {{ getNotificationData(notification).assigned_user_name }}
                                            </span>
                                        </div>

                                        <!-- Company count for assignments -->
                                        <div
                                            v-if="getNotificationData(notification).company_count"
                                            class="flex items-center text-sm text-gray-600"
                                        >
                                            <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            {{ getNotificationData(notification).company_count }}
                                            company{{ getNotificationData(notification).company_count !== 1 ? 'ies' : '' }}
                                        </div>

                                        <!-- Total uploads count -->
                                        <div
                                            v-if="getNotificationData(notification).total_count > 1"
                                            class="flex items-center text-sm text-gray-600"
                                        >
                                            <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ getNotificationData(notification).total_count }} uploads submitted
                                        </div>

                                        <!-- Reference -->
                                        <div
                                            v-if="getNotificationData(notification).reference"
                                            class="flex items-center text-sm text-gray-600"
                                        >
                                            <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                            </svg>
                                            Reference: {{ getNotificationData(notification).reference }}
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="hasExtendedDetails(notification)"
                                                @click.stop="toggleNotificationDetails(notification.id)"
                                                class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                            >
                                                {{ isNotificationExpanded(notification.id) ? 'Hide details' : 'View details' }}
                                            </button>
                                            <!-- Click-to-read hint for unread -->
                                            <span
                                                v-if="!notification.read_at"
                                                class="text-xs text-blue-500 italic opacity-0 group-hover:opacity-100 transition-opacity"
                                            >
                                                Click to mark as read
                                            </span>
                                        </div>
                                        <div :class="[
                                            'text-xs flex items-center',
                                            notification.read_at ? 'text-gray-400' : 'text-gray-600 font-medium',
                                        ]">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ formatNotificationDate(notification.created_at) }}
                                            <span v-if="notification.read_at" class="ml-2 text-gray-400">
                                                &middot; Read {{ formatNotificationDate(notification.read_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="ml-2 flex flex-col items-center gap-1">
                                <button
                                    v-if="!notification.read_at"
                                    @click.stop="markAsRead(notification.id)"
                                    class="rounded-lg p-2 text-blue-600 transition-all hover:bg-blue-50 hover:shadow-sm"
                                    title="Mark as read"
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
                                            d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"
                                        />
                                    </svg>
                                </button>
                                <button
                                    v-else
                                    class="rounded-lg p-2 text-gray-300 cursor-default"
                                    title="Already read"
                                    disabled
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
                                            d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"
                                        />
                                    </svg>
                                </button>
                                <button
                                    @click.stop="deleteNotification(notification.id)"
                                    class="rounded-lg p-2 text-red-400 transition-all hover:text-red-600 hover:bg-red-50 opacity-0 group-hover:opacity-100"
                                    title="Delete notification"
                                >
                                    <svg
                                        class="h-4 w-4"
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

                <!-- Pagination -->
                <div v-if="notifications.last_page > 1" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="text-sm text-gray-700">
                            Showing {{ notifications.from || 0 }} to {{ notifications.to || 0 }} of
                            {{ notifications.total || 0 }} results
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="notifications.prev_page_url"
                                :href="notifications.prev_page_url"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-for="page in (notifications.links || []).slice(1, -1)"
                                :key="page.label"
                                :href="page.url || '#'"
                                :class="[
                                    'rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                                    page.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : page.url
                                            ? 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                            : 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-400'
                                ]"
                            >
                                <span v-html="page.label" />
                            </Link>
                            <Link
                                v-if="notifications.next_page_url"
                                :href="notifications.next_page_url"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped src="@/../css/Centralized/Pages/Notifications/Index.css"></style>

