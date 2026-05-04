<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    uploads: Object,
    stats: Object,
    filters: Object,
    municipalities: Array,
    companies: Array,
    statusOptions: Array,
    perPageOptions: Array,
    statusBreakdown: Array,
    municipalityPerformance: Array,
    dailyVolume: Array,
    deadlineSummary: Object,
    downloadUrls: Object,
});

const filters = ref({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    municipality_id: props.filters.municipality_id || '',
    company_id: props.filters.company_id || '',
    status: props.filters.status || '',
    search: props.filters.search || '',
    per_page: Number(props.filters.per_page || 20),
});

const filteredCompanies = computed(() => {
    if (!filters.value.municipality_id) {
        return props.companies;
    }

    return props.companies.filter(
        (company) => String(company.municipality_id) === String(filters.value.municipality_id),
    );
});

watch(
    filters,
    (newFilters) => {
        router.get('/admin/reports', newFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { deep: true },
);

function resetFilters() {
    filters.value = {
        date_from: '',
        date_to: '',
        municipality_id: '',
        company_id: '',
        status: '',
        search: '',
        per_page: 20,
    };
}

function getStatusClasses(status) {
    return {
        Completed: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        Pending: 'bg-amber-100 text-amber-800 ring-amber-200',
        Processing: 'bg-sky-100 text-sky-800 ring-sky-200',
        Rejected: 'bg-rose-100 text-rose-800 ring-rose-200',
    }[status] || 'bg-slate-100 text-slate-800 ring-slate-200';
}

function statCardTone(index) {
    return [
        'border-slate-200 bg-white',
        'border-emerald-200 bg-emerald-50',
        'border-sky-200 bg-sky-50',
        'border-amber-200 bg-amber-50',
    ][index] || 'border-slate-200 bg-white';
}
</script>

<template>
    <AppLayout title="Reports">
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Reports & Analytics</h2>
                    <p class="text-sm text-slate-500">
                        Preview filtered upload activity, track deadline coverage, and download reports in CSV or Excel.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="downloadUrls.uploads_csv" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Download CSV
                    </a>
                    <a :href="downloadUrls.uploads_xlsx" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                        Download Excel
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="(item, index) in [
                            { label: 'Filtered Uploads', value: stats.filtered_uploads, helper: 'Uploads matching the current filters.' },
                            { label: 'Completed', value: stats.completed_uploads, helper: 'Completed submissions in scope.' },
                            { label: 'Processing', value: stats.processing_uploads, helper: 'Items still moving through the workflow.' },
                            { label: 'Pending', value: stats.pending_uploads, helper: 'Uploads waiting for completion or review.' },
                        ]"
                        :key="item.label"
                        :class="['rounded-2xl border p-5 shadow-sm', statCardTone(index)]"
                    >
                        <p class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase">{{ item.label }}</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ item.value }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ item.helper }}</p>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Report Filters</h3>
                                <p class="text-sm text-slate-500">All previews and downloads use the same filter scope.</p>
                            </div>
                            <button
                                @click="resetFilters"
                                class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Reset Filters
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 bg-slate-50/80 p-6 md:grid-cols-2 xl:grid-cols-4">
                        <div class="xl:col-span-2">
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Search</label>
                            <input
                                v-model="filters.search"
                                type="text"
                                placeholder="Reference, company, municipality, user..."
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Date From</label>
                            <input v-model="filters.date_from" type="date" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Date To</label>
                            <input v-model="filters.date_to" type="date" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Municipality</label>
                            <select v-model="filters.municipality_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Municipalities</option>
                                <option v-for="municipality in municipalities" :key="municipality.id" :value="municipality.id">
                                    {{ municipality.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Company</label>
                            <select v-model="filters.company_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Companies</option>
                                <option v-for="company in filteredCompanies" :key="company.id" :value="company.id">
                                    {{ company.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Status</label>
                            <select v-model="filters.status" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                <option v-for="status in statusOptions" :key="status" :value="status">
                                    {{ status }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold tracking-wide text-slate-600 uppercase">Per Page</label>
                            <select v-model="filters.per_page" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="option in perPageOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm xl:col-span-1">
                        <div class="border-b border-slate-200 p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">Status Breakdown</h3>
                                    <p class="text-sm text-slate-500">Quick preview of upload distribution.</p>
                                </div>
                                <a :href="downloadUrls.upload_summary_csv" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    Download summary
                                </a>
                            </div>
                        </div>
                        <div class="space-y-4 p-6">
                            <div v-for="row in statusBreakdown" :key="row.status" class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between">
                                    <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset', getStatusClasses(row.status)]">
                                        {{ row.status }}
                                    </span>
                                    <span class="text-lg font-semibold text-slate-900">{{ row.count }}</span>
                                </div>
                            </div>
                            <p v-if="!statusBreakdown.length" class="text-sm text-slate-500">No status data available for the selected filters.</p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                        <div class="border-b border-slate-200 p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Municipality Performance Preview</h3>
                            <p class="text-sm text-slate-500">Top municipalities by upload volume and completion rate.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Municipality</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Uploads</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Completed</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Completion Rate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="row in municipalityPerformance" :key="row.municipality">
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ row.municipality }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-600">{{ row.uploads }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-600">{{ row.completed }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-600">{{ row.completion_rate }}</td>
                                    </tr>
                                    <tr v-if="!municipalityPerformance.length">
                                        <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500">
                                            No municipality performance data available for the selected filters.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Recent Daily Volume</h3>
                            <p class="text-sm text-slate-500">Last days in scope with completed vs total uploads.</p>
                        </div>
                        <div class="space-y-3 p-6">
                            <div v-for="row in dailyVolume" :key="row.date" class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ row.date }}</p>
                                        <p class="text-xs text-slate-500">{{ row.completed }} completed</p>
                                    </div>
                                    <p class="text-xl font-semibold text-slate-900">{{ row.count }}</p>
                                </div>
                            </div>
                            <p v-if="!dailyVolume.length" class="text-sm text-slate-500">No daily volume data available.</p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">Deadline Coverage Preview</h3>
                                    <p class="text-sm text-slate-500">Assigned companies vs submitted companies around each deadline.</p>
                                </div>
                                <a :href="downloadUrls.deadline_summary_csv" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    Download summary
                                </a>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-b border-slate-200 bg-slate-50/80 p-6">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Tracked Deadlines</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ deadlineSummary.stats.tracked_deadlines }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Missing Companies</p>
                                <p class="mt-2 text-2xl font-semibold text-rose-700">{{ deadlineSummary.stats.missing_companies }}</p>
                            </div>
                        </div>
                        <div class="max-h-[420px] overflow-y-auto">
                            <div v-for="row in deadlineSummary.rows" :key="`${row.municipality}-${row.deadline_date}`" class="border-b border-slate-100 p-6 last:border-b-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ row.municipality }}</p>
                                        <p class="text-sm text-slate-500">{{ row.deadline_date }}</p>
                                    </div>
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset',
                                            row.is_overdue ? 'bg-rose-100 text-rose-800 ring-rose-200' : 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                                        ]"
                                    >
                                        {{ row.coverage_rate }}
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                                    <div class="rounded-xl bg-slate-50 p-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Assigned</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ row.assigned_companies }}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Submitted</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ row.submitted_companies }}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Missing</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ row.missing_companies }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!deadlineSummary.rows.length" class="p-6 text-sm text-slate-500">
                                No deadline coverage data available for the selected filters.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Uploads Report Preview</h3>
                                <p class="text-sm text-slate-500">Detailed rows matching the active filters.</p>
                            </div>
                            <p class="text-sm text-slate-500">
                                Showing {{ uploads.from || 0 }}-{{ uploads.to || 0 }} of {{ uploads.total || 0 }}
                            </p>
                        </div>
                    </div>

                    <div v-if="!uploads.data.length" class="p-12 text-center">
                        <h4 class="text-lg font-semibold text-slate-900">No uploads found</h4>
                        <p class="mt-2 text-sm text-slate-500">Adjust the filters or download the summaries for a broader view.</p>
                    </div>

                    <div v-else>
                        <div class="hidden overflow-x-auto xl:block">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Reference</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Company</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Municipality</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Uploaded By</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Submitted</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Preview</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="upload in uploads.data" :key="upload.id" class="transition hover:bg-slate-50/80">
                                        <td class="px-6 py-5 text-sm font-semibold text-slate-900">{{ upload.reference }}</td>
                                        <td class="px-6 py-5 text-sm text-slate-600">{{ upload.company.name }}</td>
                                        <td class="px-6 py-5 text-sm text-slate-600">{{ upload.municipality.name }}</td>
                                        <td class="px-6 py-5">
                                            <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset', getStatusClasses(upload.status)]">
                                                {{ upload.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 text-sm text-slate-600">
                                            <div class="font-medium text-slate-900">{{ upload.user.name }}</div>
                                            <div class="text-slate-500">{{ upload.user.email }}</div>
                                        </td>
                                        <td class="px-6 py-5 text-sm text-slate-600">{{ upload.submitted_at_display }}</td>
                                        <td class="px-6 py-5 text-sm text-slate-600">
                                            <div>{{ upload.original_files_count }} original file(s)</div>
                                            <div class="text-slate-500">{{ upload.workings_file_name }}</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-4 p-4 xl:hidden">
                            <article v-for="upload in uploads.data" :key="upload.id" class="rounded-2xl border border-slate-200 p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ upload.reference }}</p>
                                        <p class="text-sm text-slate-500">{{ upload.company.name }} • {{ upload.municipality.name }}</p>
                                    </div>
                                    <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset', getStatusClasses(upload.status)]">
                                        {{ upload.status }}
                                    </span>
                                </div>
                                <div class="mt-4 space-y-2 text-sm text-slate-600">
                                    <p><span class="font-medium text-slate-900">Uploaded by:</span> {{ upload.user.name }}</p>
                                    <p><span class="font-medium text-slate-900">Submitted:</span> {{ upload.submitted_at_display }}</p>
                                    <p><span class="font-medium text-slate-900">Preview:</span> {{ upload.original_files_count }} original file(s), {{ upload.workings_file_name }}</p>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 p-6">
                        <Pagination :links="uploads.links" />
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
