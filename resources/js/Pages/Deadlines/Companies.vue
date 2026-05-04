<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    companies: Object,   // paginated
    stats: Object,
    filters: Object,
});

/* ---------- Filters ---------- */
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const perPage = ref(Number(props.filters?.per_page || 20));

const applyFilters = (page = 1) => {
    router.get('/deadlines/companies', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        per_page: perPage.value,
        page,
    }, { preserveState: true, replace: true });
};

let searchTimeout = null;
const onSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(1), 350);
};

/* ---------- Detail modal ---------- */
const detailCompany = ref(null);
const detailLoading = ref(false);
const detailData = ref(null);

const openDetail = async (company) => {
    detailCompany.value = company;
    detailLoading.value = true;
    detailData.value = null;
    try {
        const resp = await fetch(`/deadlines/companies/${company.id}/submissions`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        detailData.value = await resp.json();
    } catch (e) {
        detailData.value = { error: e.message };
    } finally {
        detailLoading.value = false;
    }
};
const closeDetail = () => { detailCompany.value = null; detailData.value = null; };

/* ---------- Helpers ---------- */
const statusColor = (s) => {
    const map = { Completed: 'bg-green-100 text-green-700', Pending: 'bg-yellow-100 text-yellow-700', Processing: 'bg-blue-100 text-blue-700', Rejected: 'bg-red-100 text-red-700' };
    return map[s] || 'bg-gray-100 text-gray-600';
};
const capsStatusBadge = (s) => {
    if (!s) return '';
    const map = { imported: 'bg-blue-100 text-blue-700', allocated: 'bg-green-100 text-green-700', exported: 'bg-emerald-100 text-emerald-700', failed: 'bg-red-100 text-red-700', refund_created: 'bg-purple-100 text-purple-700' };
    return map[s] || 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Companies</h1>
                <p class="mt-1 text-sm text-gray-500">All companies synced from CAPS and their submission activity.</p>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-400">Total</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800">{{ stats?.total ?? 0 }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-green-500">Submitted this month</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">{{ stats?.submitted_this_month ?? 0 }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-amber-500">Pending this month</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600">{{ stats?.pending_this_month ?? 0 }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-blue-500">Upcoming deadlines</p>
                    <p class="mt-1 text-2xl font-bold text-blue-600">{{ stats?.upcoming_deadlines ?? 0 }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    @input="onSearch"
                    type="text"
                    placeholder="Search companies..."
                    class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                />
                <select v-model="statusFilter" @change="applyFilters(1)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select v-model="perPage" @change="applyFilters(1)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option :value="10">10 / page</option>
                    <option :value="20">20 / page</option>
                    <option :value="50">50 / page</option>
                </select>
                <span class="ml-auto text-xs text-gray-400">
                    Showing {{ companies?.from ?? 0 }}–{{ companies?.to ?? 0 }} of {{ companies?.total ?? 0 }}
                </span>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Company</th>
                            <th class="px-4 py-3 text-left">Contact</th>
                            <th class="px-4 py-3 text-center">Submissions</th>
                            <th class="px-4 py-3 text-center">This Month</th>
                            <th class="px-4 py-3 text-left">Last Submitted</th>
                            <th class="px-4 py-3 text-center">CAPS</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="co in companies?.data || []" :key="co.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ co.name }}</p>
                                <p v-if="co.registration_number" class="text-xs text-gray-400">{{ co.registration_number }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ co.contact_email || '—' }}</td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ co.total_submissions ?? 0 }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="co.submissions_this_month > 0" class="inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                    {{ co.submissions_this_month }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                {{ co.uploads?.[0]?.submitted_at ? new Date(co.uploads[0].submitted_at).toLocaleDateString('en-ZA') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="co.casey_id" class="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-600" title="Synced from CAPS">Synced</span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="co.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="inline-block rounded-full px-2 py-0.5 text-[10px] font-medium capitalize">
                                    {{ co.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button @click="openDetail(co)" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-blue-600" title="View submissions">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!(companies?.data?.length)">
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400">No companies found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="companies?.last_page > 1" class="flex items-center justify-between">
                <button @click="applyFilters((companies?.current_page || 1) - 1)" :disabled="!companies?.prev_page_url"
                    class="rounded-lg border px-3 py-1.5 text-sm disabled:opacity-40">Prev</button>
                <div class="flex gap-1">
                    <button v-for="p in companies.last_page" :key="p" @click="applyFilters(p)"
                        :class="companies.current_page === p ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium">{{ p }}</button>
                </div>
                <button @click="applyFilters((companies?.current_page || 1) + 1)" :disabled="!companies?.next_page_url"
                    class="rounded-lg border px-3 py-1.5 text-sm disabled:opacity-40">Next</button>
            </div>
        </div>

        <!-- Company detail modal -->
        <Teleport to="body">
            <div v-if="detailCompany" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeDetail">
                <div class="w-full max-w-3xl max-h-[85vh] flex flex-col rounded-2xl bg-white shadow-xl">
                    <!-- Header -->
                    <div class="flex shrink-0 items-center justify-between border-b px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800">{{ detailCompany.name }}</h3>
                            <p class="text-xs text-gray-400">{{ detailCompany.contact_email || 'No email' }}</p>
                        </div>
                        <button @click="closeDetail" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <!-- Loading -->
                        <div v-if="detailLoading" class="flex justify-center py-12">
                            <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-blue-500"></div>
                        </div>
                        <!-- Error -->
                        <p v-else-if="detailData?.error" class="py-8 text-center text-red-500">{{ detailData.error }}</p>
                        <!-- Content -->
                        <template v-else-if="detailData">
                            <!-- Company info -->
                            <div class="mb-4 grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-4">
                                <div>
                                    <span class="text-xs text-gray-400">Status</span>
                                    <p class="font-medium capitalize">{{ detailData.company.status }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400">CAPS ID</span>
                                    <p class="font-mono text-xs">{{ detailData.company.casey_id ? detailData.company.casey_id.slice(0, 16) + '…' : '—' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400">Last synced</span>
                                    <p>{{ detailData.company.casey_synced_at || '—' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400">Total submissions</span>
                                    <p class="text-lg font-bold">{{ detailData.submissions.length }}</p>
                                </div>
                            </div>

                            <!-- Submissions table -->
                            <h4 class="mb-2 text-xs font-semibold uppercase text-gray-400">Recent Submissions</h4>
                            <table v-if="detailData.submissions.length" class="w-full text-xs">
                                <thead class="border-b bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Reference</th>
                                        <th class="px-3 py-2 text-left">Municipality</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                        <th class="px-3 py-2 text-center">CAPS Status</th>
                                        <th class="px-3 py-2 text-left">Submitted</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="s in detailData.submissions" :key="s.id" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 font-mono">{{ s.reference }}</td>
                                        <td class="px-3 py-2">{{ s.municipality }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span :class="statusColor(s.status)" class="inline-block rounded-full px-2 py-0.5 text-[10px] font-medium">{{ s.status }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span v-if="s.caps_status" :class="capsStatusBadge(s.caps_status)" class="inline-block rounded-full px-2 py-0.5 text-[10px] font-medium">{{ s.caps_status }}</span>
                                            <span v-else class="text-gray-300">—</span>
                                        </td>
                                        <td class="px-3 py-2">{{ s.submitted_at || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="py-8 text-center text-gray-400">No submissions yet for this company.</p>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="shrink-0 border-t px-5 py-3 text-right">
                        <button @click="closeDetail" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Close</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
