<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    tickets: Object,
    stats: Object,
    filters: Object,
    isStaff: Boolean,
    companies: Array,
    municipalities: Array,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const priorityFilter = ref(props.filters?.priority || '');
const categoryFilter = ref(props.filters?.category || '');

const applyFilters = () => {
    router.get('/support', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        priority: priorityFilter.value || undefined,
        category: categoryFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

let debounce;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(applyFilters, 400); });
watch([statusFilter, priorityFilter, categoryFilter], applyFilters);

const clearFilters = () => {
    search.value = '';
    statusFilter.value = '';
    priorityFilter.value = '';
    categoryFilter.value = '';
    router.get('/support');
};

const statusConfig = {
    open: { label: 'Open', bg: 'bg-blue-100', text: 'text-blue-700', dot: 'bg-blue-500' },
    in_progress: { label: 'In Progress', bg: 'bg-indigo-100', text: 'text-indigo-700', dot: 'bg-indigo-500' },
    waiting_on_company: { label: 'Awaiting Company', bg: 'bg-amber-100', text: 'text-amber-700', dot: 'bg-amber-500' },
    waiting_on_casey: { label: 'Awaiting Casey', bg: 'bg-orange-100', text: 'text-orange-700', dot: 'bg-orange-500' },
    resolved: { label: 'Resolved', bg: 'bg-emerald-100', text: 'text-emerald-700', dot: 'bg-emerald-500' },
    closed: { label: 'Closed', bg: 'bg-gray-100', text: 'text-gray-500', dot: 'bg-gray-400' },
};
const priorityConfig = {
    low: { label: 'Low', color: 'text-gray-500' },
    medium: { label: 'Medium', color: 'text-blue-600' },
    high: { label: 'High', color: 'text-orange-600' },
    urgent: { label: 'Urgent', color: 'text-red-600' },
};
const categoryLabels = {
    upload_query: 'Upload Query',
    verification_issue: 'Verification Issue',
    deadline_query: 'Deadline Query',
    account_issue: 'Account Issue',
    data_correction: 'Data Correction',
    general: 'General',
};

const formatTime = (ts) => {
    if (!ts) return '';
    const d = new Date(ts);
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return d.toLocaleDateString();
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Support Tickets</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ isStaff ? 'Manage all support cases' : 'Your support cases and queries' }}</p>
                </div>
                <Link href="/support/create"
                      class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New Ticket
                </Link>
            </div>

            <!-- Stats -->
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-4">
                    <p class="text-2xl font-bold text-blue-700">{{ stats.open }}</p>
                    <p class="text-xs font-medium text-blue-500">Open</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-4">
                    <p class="text-2xl font-bold text-indigo-700">{{ stats.in_progress }}</p>
                    <p class="text-xs font-medium text-indigo-500">In Progress</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4">
                    <p class="text-2xl font-bold text-amber-700">{{ stats.waiting }}</p>
                    <p class="text-xs font-medium text-amber-500">Waiting</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4">
                    <p class="text-2xl font-bold text-emerald-700">{{ stats.resolved }}</p>
                    <p class="text-xs font-medium text-emerald-500">Resolved</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <input v-model="search" type="text" placeholder="Search tickets..."
                       class="h-9 w-64 rounded-lg border border-gray-200 px-3 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
                <select v-model="statusFilter" class="h-9 rounded-lg border border-gray-200 px-2 text-sm">
                    <option value="">All statuses</option>
                    <option v-for="(cfg, key) in statusConfig" :key="key" :value="key">{{ cfg.label }}</option>
                </select>
                <select v-model="priorityFilter" class="h-9 rounded-lg border border-gray-200 px-2 text-sm">
                    <option value="">All priorities</option>
                    <option v-for="(cfg, key) in priorityConfig" :key="key" :value="key">{{ cfg.label }}</option>
                </select>
                <select v-model="categoryFilter" class="h-9 rounded-lg border border-gray-200 px-2 text-sm">
                    <option value="">All categories</option>
                    <option v-for="(label, key) in categoryLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <button v-if="search || statusFilter || priorityFilter || categoryFilter" @click="clearFilters"
                        class="h-9 rounded-lg px-3 text-xs font-medium text-gray-500 hover:bg-gray-100">Clear</button>
            </div>

            <!-- Ticket List -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div v-if="!tickets.data?.length" class="px-6 py-16 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                    </svg>
                    <p class="mt-3 text-sm text-gray-500">No tickets yet</p>
                    <Link href="/support/create" class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800">Create your first ticket</Link>
                </div>

                <div v-for="t in tickets.data" :key="t.id"
                     @click="router.visit(`/support/${t.id}`)"
                     class="flex cursor-pointer items-start gap-4 border-b border-gray-100 px-5 py-4 transition-colors hover:bg-gray-50/80 last:border-b-0">
                    <!-- Priority indicator -->
                    <div class="mt-1 flex-shrink-0">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" :class="statusConfig[t.status]?.dot || 'bg-gray-300'"></span>
                    </div>

                    <!-- Main content -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono text-gray-400">{{ t.reference }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                  :class="[statusConfig[t.status]?.bg, statusConfig[t.status]?.text]">
                                {{ statusConfig[t.status]?.label }}
                            </span>
                            <span class="text-[10px] font-medium" :class="priorityConfig[t.priority]?.color">
                                {{ priorityConfig[t.priority]?.label }}
                            </span>
                            <span v-if="(isStaff && t.unread_casey > 0) || (!isStaff && t.unread_company > 0)"
                                  class="rounded-full bg-red-500 px-1.5 py-0.5 text-[9px] font-bold text-white">
                                {{ isStaff ? t.unread_casey : t.unread_company }} new
                            </span>
                        </div>
                        <h3 class="mt-1 text-sm font-semibold text-gray-900 truncate">{{ t.subject }}</h3>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                            <span>{{ t.creator?.name }}</span>
                            <span v-if="t.company" class="flex items-center gap-1">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18"/></svg>
                                {{ t.company.name }}
                            </span>
                            <span v-if="t.upload" class="flex items-center gap-1">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                {{ t.upload.reference }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px]">{{ categoryLabels[t.category] || t.category }}</span>
                        </div>
                        <p v-if="t.latest_message" class="mt-1.5 truncate text-xs text-gray-500">
                            {{ t.latest_message.body?.substring(0, 120) }}
                        </p>
                    </div>

                    <!-- Right side -->
                    <div class="flex-shrink-0 text-right">
                        <p class="text-xs text-gray-400">{{ formatTime(t.last_message_at || t.created_at) }}</p>
                        <p class="mt-1 text-[10px] text-gray-300">{{ t.message_count }} msg{{ t.message_count !== 1 ? 's' : '' }}</p>
                        <p v-if="t.assignee" class="mt-1 text-[10px] text-indigo-400">{{ t.assignee.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="tickets.links?.length > 3" class="mt-4 flex items-center justify-between">
                <p class="text-xs text-gray-500">Showing {{ tickets.from }}-{{ tickets.to }} of {{ tickets.total }}</p>
                <div class="flex gap-1">
                    <template v-for="link in tickets.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              class="rounded-md border px-3 py-1 text-xs"
                              :class="link.active ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                              v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
