<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const page = usePage();

const props = defineProps({
    audits: Object,
    filters: Object,
    users: Array,
    eventTypes: Array,
    perPageOptions: Array,
});

const filters = ref({
    user_id: props.filters.user_id || '',
    event: props.filters.event || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    search: props.filters.search || '',
    per_page: Number(props.filters.per_page || 20),
});

watch(
    filters,
    (newFilters) => {
        router.get('/admin/audits', newFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { deep: true },
);

const summary = computed(() => {
    const rows = props.audits?.data || [];

    return {
        total: props.audits?.total || rows.length,
        logins: rows.filter((audit) => audit.event === 'logged_in').length,
        critical: rows.filter((audit) => ['deleted', 'failed_login'].includes(audit.event)).length,
        trackedUsers: new Set(rows.map((audit) => audit.user_id).filter(Boolean)).size,
    };
});

function resetFilters() {
    filters.value = {
        user_id: '',
        event: '',
        date_from: '',
        date_to: '',
        search: '',
        per_page: 20,
    };
}

function getEventColor(event) {
    const colors = {
        created: 'bg-green-100 text-green-800 ring-green-200',
        updated: 'bg-blue-100 text-blue-800 ring-blue-200',
        deleted: 'bg-red-100 text-red-800 ring-red-200',
        restored: 'bg-yellow-100 text-yellow-800 ring-yellow-200',
        request: 'bg-slate-100 text-slate-800 ring-slate-200',
        logged_in: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        logged_out: 'bg-amber-100 text-amber-800 ring-amber-200',
        failed_login: 'bg-rose-100 text-rose-800 ring-rose-200',
        permission_attached: 'bg-cyan-100 text-cyan-800 ring-cyan-200',
        permission_detached: 'bg-orange-100 text-orange-800 ring-orange-200',
    };

    return colors[event] || 'bg-gray-100 text-gray-800 ring-gray-200';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : 'N/A';
}

function getSubject(audit) {
    return audit.auditable_label || audit.auditable_type?.replace('App\\Models\\', '') || 'Unknown';
}

function getChangesPreview(audit) {
    const entries = Object.entries(audit.new_values || {});

    if (!entries.length) {
        return 'No field payload recorded';
    }

    return entries
        .slice(0, 2)
        .map(([key, value]) => `${key}: ${typeof value === 'object' ? 'updated' : value}`)
        .join(' | ');
}

function showAuditUrl(audit) {
    return page.props.ziggy
        ? route('admin.audits.show', audit.id)
        : `/admin/audits/${audit.id}`;
}
</script>

<template>
    <AppLayout title="Audit Logs">
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-900">Audit Logs</h2>
                    <p class="text-sm text-gray-500">
                        Review activity across authentication, admin actions, data updates, and request flow.
                    </p>
                </div>
                <div class="text-sm text-gray-500">
                    Showing {{ audits.from || 0 }}-{{ audits.to || 0 }} of {{ audits.total || 0 }} entries
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase">Entries</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ summary.total }}</p>
                        <p class="mt-1 text-sm text-slate-500">Total audit rows matching the current page scope.</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                        <p class="text-xs font-semibold tracking-[0.2em] text-emerald-700 uppercase">Logins</p>
                        <p class="mt-3 text-3xl font-semibold text-emerald-900">{{ summary.logins }}</p>
                        <p class="mt-1 text-sm text-emerald-700">Successful sign-ins on the current page.</p>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                        <p class="text-xs font-semibold tracking-[0.2em] text-rose-700 uppercase">Critical</p>
                        <p class="mt-3 text-3xl font-semibold text-rose-900">{{ summary.critical }}</p>
                        <p class="mt-1 text-sm text-rose-700">Deletes and failed logins in the current page.</p>
                    </div>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                        <p class="text-xs font-semibold tracking-[0.2em] text-indigo-700 uppercase">Users</p>
                        <p class="mt-3 text-3xl font-semibold text-indigo-900">{{ summary.trackedUsers }}</p>
                        <p class="mt-1 text-sm text-indigo-700">Distinct users represented on this page.</p>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">System Audit Trail</h3>
                                <p class="text-sm text-slate-500">Filter by actor, event type, or date range.</p>
                            </div>
                            <button
                                @click="resetFilters"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Reset Filters
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-b border-slate-200 bg-slate-50/80 p-6 lg:grid-cols-6">
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">User</label>
                            <select v-model="filters.user_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Users</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Event</label>
                            <select v-model="filters.event" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Events</option>
                                <option v-for="event in eventTypes" :key="event" :value="event">
                                    {{ event }}
                                </option>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Search</label>
                            <input
                                v-model="filters.search"
                                type="text"
                                placeholder="User, subject, URL, event..."
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">From</label>
                            <input v-model="filters.date_from" type="date" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">To</label>
                            <input v-model="filters.date_to" type="date" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Per Page</label>
                            <select v-model="filters.per_page" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="option in perPageOptions || [20, 50, 100]" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="!audits.data.length" class="p-12 text-center">
                        <div class="mx-auto max-w-md">
                            <h4 class="text-lg font-semibold text-slate-900">No audit records found</h4>
                            <p class="mt-2 text-sm text-slate-500">
                                Try widening your filters or perform an action like logging in, updating a record, or managing assignments.
                            </p>
                        </div>
                    </div>

                    <div v-else>
                        <div class="hidden overflow-x-auto xl:block">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Actor</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Event</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Subject</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Preview</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Timestamp</th>
                                        <th class="px-6 py-4 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="audit in audits.data" :key="audit.id" class="transition hover:bg-slate-50/80">
                                        <td class="px-6 py-5">
                                            <div class="text-sm font-semibold text-slate-900">{{ audit.user?.name || 'System' }}</div>
                                            <div class="text-sm text-slate-500">{{ audit.user?.email || audit.ip_address || 'No identity data' }}</div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset', getEventColor(audit.event)]">
                                                {{ audit.event }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 text-sm text-slate-700">
                                            <div class="font-medium text-slate-900">{{ getSubject(audit) }}</div>
                                            <div class="text-slate-500">ID: {{ audit.auditable_id }}</div>
                                        </td>
                                        <td class="px-6 py-5 text-sm text-slate-500">
                                            <div class="max-w-md truncate">{{ getChangesPreview(audit) }}</div>
                                        </td>
                                        <td class="px-6 py-5 text-sm text-slate-500">{{ formatDate(audit.created_at) }}</td>
                                        <td class="px-6 py-5 text-right">
                                            <Link :href="showAuditUrl(audit)" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                                                View Details
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-4 p-4 xl:hidden">
                            <article v-for="audit in audits.data" :key="audit.id" class="rounded-2xl border border-slate-200 p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ audit.user?.name || 'System' }}</p>
                                        <p class="text-sm text-slate-500">{{ audit.user?.email || audit.ip_address || 'No identity data' }}</p>
                                    </div>
                                    <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset', getEventColor(audit.event)]">
                                        {{ audit.event }}
                                    </span>
                                </div>
                                <div class="mt-4 space-y-2 text-sm text-slate-600">
                                    <p><span class="font-medium text-slate-900">Subject:</span> {{ getSubject(audit) }} #{{ audit.auditable_id }}</p>
                                    <p><span class="font-medium text-slate-900">Timestamp:</span> {{ formatDate(audit.created_at) }}</p>
                                    <p><span class="font-medium text-slate-900">Preview:</span> {{ getChangesPreview(audit) }}</p>
                                </div>
                                <div class="mt-4">
                                    <Link :href="showAuditUrl(audit)" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                                        View Details
                                    </Link>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 p-6">
                        <Pagination :links="audits.links" />
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
