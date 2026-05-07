<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    allocations: Array,
    assignedUsers: Array,
    filters: Object,
});

const searchTerm = ref(props.filters?.search || '');
const userFilter = ref(props.filters?.user_id || '');
const expandedMunis = ref(new Set());

const filteredAllocations = computed(() => {
    let items = props.allocations || [];
    const q = searchTerm.value.toLowerCase().trim();

    if (q) {
        items = items.filter(m =>
            m.municipality_name.toLowerCase().includes(q) ||
            m.companies.some(c => c.company_name.toLowerCase().includes(q))
        );
    }

    if (userFilter.value) {
        items = items.map(m => ({
            ...m,
            companies: m.companies.filter(c =>
                c.assigned_users.some(u => String(u.id) === String(userFilter.value))
            ),
        })).filter(m => m.companies.length > 0);
    }

    return items;
});

const totalCompanies = computed(() =>
    filteredAllocations.value.reduce((sum, m) => sum + m.companies.length, 0)
);

const totalUsers = computed(() => (props.assignedUsers || []).length);

const toggleMuni = (id) => {
    const s = new Set(expandedMunis.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expandedMunis.value = s;
};

const expandAll = () => {
    expandedMunis.value = new Set(filteredAllocations.value.map(m => m.municipality_id));
};
const collapseAll = () => { expandedMunis.value = new Set(); };

const userColor = (name) => {
    const colors = {
        'Tumi': 'bg-green-100 text-green-700',
        'Tsholo': 'bg-blue-100 text-blue-700',
        'Bafi': 'bg-amber-100 text-amber-700',
        'Bafikile': 'bg-amber-100 text-amber-700',
    };
    return colors[name] || 'bg-slate-100 text-slate-700';
};
</script>

<template>
    <AppLayout>
        <Head title="Work Allocation" />

        <div class="px-6 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Work Allocation</h2>
                    <p class="mt-1 text-sm text-gray-500">Municipality and company assignment overview</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-slate-500">Municipalities</div>
                    <div class="mt-1 text-2xl font-bold text-slate-900">{{ filteredAllocations.length }}</div>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-slate-500">Company Assignments</div>
                    <div class="mt-1 text-2xl font-bold text-slate-900">{{ totalCompanies }}</div>
                </div>
                <div class="rounded-xl border bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-slate-500">Assigned Users</div>
                    <div class="mt-1 text-2xl font-bold text-slate-900">{{ totalUsers }}</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="mt-6 rounded-xl border bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-4">
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search municipality or company..."
                        class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                    <select
                        v-model="userFilter"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="">All Users</option>
                        <option v-for="u in assignedUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div class="flex gap-2">
                        <button @click="expandAll" class="rounded-lg border px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">Expand All</button>
                        <button @click="collapseAll" class="rounded-lg border px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">Collapse All</button>
                    </div>
                    <span class="text-sm text-slate-500">{{ totalCompanies }} assignments across {{ filteredAllocations.length }} municipalities</span>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!filteredAllocations.length" class="mt-6 rounded-xl border bg-white p-8 text-center text-slate-500 shadow-sm">
                No allocations match the current filters.
            </div>

            <!-- Allocation Table -->
            <div v-if="filteredAllocations.length" class="mt-6 space-y-3">
                <div v-for="muni in filteredAllocations" :key="muni.municipality_id"
                     class="rounded-xl border bg-white shadow-sm overflow-hidden">

                    <!-- Municipality Header -->
                    <button
                        @click="toggleMuni(muni.municipality_id)"
                        class="flex w-full items-center justify-between p-4 text-left hover:bg-slate-50 transition"
                    >
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-content-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">{{ muni.municipality_name }}</h3>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span v-if="muni.deadline_date" class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                        Deadline: {{ muni.deadline_date }}
                                    </span>
                                    <span v-if="muni.deadline_day" class="rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-medium text-purple-700">
                                        {{ muni.deadline_day }}th of month
                                    </span>
                                    <span class="text-xs text-slate-500">{{ muni.total_companies }} companies</span>
                                </div>
                            </div>
                        </div>
                        <svg class="h-5 w-5 text-slate-400 transition-transform"
                             :class="{ 'rotate-180': expandedMunis.has(muni.municipality_id) }"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Expanded Company List -->
                    <transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div v-if="expandedMunis.has(muni.municipality_id)" class="border-t bg-slate-50/60">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-100">
                                    <tr class="text-left">
                                        <th class="px-4 py-2 font-medium text-slate-600">Company</th>
                                        <th class="px-4 py-2 font-medium text-slate-600">Assigned To</th>
                                        <th class="px-4 py-2 font-medium text-slate-600">Deadline</th>
                                        <th class="px-4 py-2 font-medium text-slate-600">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr v-for="co in muni.companies" :key="co.company_id" class="hover:bg-slate-50">
                                        <td class="px-4 py-2.5 font-medium text-slate-900">{{ co.company_name }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex flex-wrap gap-1">
                                                <span v-for="u in co.assigned_users" :key="u.id"
                                                      class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                                      :class="userColor(u.name)">
                                                    {{ u.name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ co.deadline_date || muni.deadline_date || '—' }}</td>
                                        <td class="px-4 py-2.5 text-slate-500 text-xs">{{ co.notes || '—' }}</td>
                                    </tr>
                                    <tr v-if="!muni.companies.length">
                                        <td colspan="4" class="px-4 py-4 text-center text-slate-400">No companies assigned</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </transition>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
