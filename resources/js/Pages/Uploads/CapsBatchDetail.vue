<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'

const page = usePage()
const props = defineProps({ upload: Object, webhookEvents: Array })
const s = computed(() => props.upload.caps_summary || {})
const phase = computed(() => s.value.phase || props.upload.caps_status || 'draft')

const tabs = [
    { key: 'new', label: 'New', countKey: 'caps_new', recordsKey: 'new_records' },
    { key: 'updated', label: 'Updated', countKey: 'caps_updated', recordsKey: 'updated_records' },
    { key: 'cancelled', label: 'Cancelled', countKey: 'caps_cancelled', recordsKey: 'cancelled_records' },
    { key: 'errors', label: 'Errors', countKey: 'caps_errors', recordsKey: 'errors_records' },
    { key: 'inactive_members', label: 'Inact. Members', countKey: 'caps_inactive_members', recordsKey: 'inactive_members_records' },
    { key: 'inactive_policies', label: 'Inact. Policies', countKey: 'caps_inactive_policies', recordsKey: 'inactive_policies_records' },
    { key: 'duplicates', label: 'Duplicates', countKey: 'caps_duplicates', recordsKey: 'duplicates_records' },
    { key: 'affordability', label: 'Unaffordable', countKey: 'caps_unaffordable', recordsKey: 'affordability_records' },
]

const activeTab = ref('new')
const activeRecords = computed(() => {
    const tab = tabs.find(t => t.key === activeTab.value)
    return tab ? (s.value[tab.recordsKey] || []) : []
})

const search = ref('')
const filtered = computed(() => {
    const q = search.value.toLowerCase().trim()
    if (!q) return activeRecords.value
    return activeRecords.value.filter(r => Object.values(r).some(v => v && String(v).toLowerCase().includes(q)))
})

const fmtR = (v) => v != null && Number(v) !== 0 ? `R ${Number(v).toLocaleString('en-ZA', { minimumFractionDigits: 2 })}` : '—'
const hasErrors = computed(() => activeTab.value === 'errors' || activeTab.value === 'affordability')

function dispatch() { if (confirm('Dispatch to CAPS?')) router.post(`/uploads/${props.upload.id}/dispatch-to-caps`) }
function retry() { if (confirm('Retry?')) router.post(`/uploads/${props.upload.id}/retry-caps`) }
function save() { if (confirm('Finalize this batch in CAPS?')) router.post(`/uploads/${props.upload.id}/save-to-caps`) }
</script>

<template>
<AppLayout>
<Head :title="`Batch #${upload.caps_payment_batch_id || upload.reference}`" />
<div class="min-h-screen bg-white">
<div class="mx-auto max-w-[1440px] px-8 py-8 space-y-6">

    <!-- Flash -->
    <div v-if="page.props.flash?.success" class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        {{ page.props.flash.success }}
    </div>
    <div v-if="page.props.flash?.error" class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ page.props.flash.error }}
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between border-b pb-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">
                Batch #{{ upload.caps_payment_batch_id || '—' }}
                <span class="ml-3 inline-flex rounded px-2 py-0.5 text-xs font-medium"
                      :class="phase === 'failed' ? 'bg-red-50 text-red-700' : phase === 'imported' ? 'bg-amber-50 text-amber-700' : phase === 'saved' || phase === 'completed' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                    {{ phase === 'imported' ? 'Review & Save' : phase }}
                </span>
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ upload.company?.name }}<span v-if="upload.municipality"> &middot; {{ upload.municipality.name }}</span><span v-if="s.caps_file_name"> &middot; {{ s.caps_file_name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button v-if="phase === 'imported' && upload.caps_payment_batch_id" @click="save"
                    class="rounded border border-green-700 bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800">Save Premiums</button>
            <button v-if="upload.can_dispatch" @click="dispatch" class="rounded border border-gray-800 bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Dispatch</button>
            <button v-if="upload.can_retry" @click="retry" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Retry</button>
            <a href="/uploads/history" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</a>
        </div>
    </div>

    <!-- Company Mismatch Warnings -->
    <div v-if="s.company_warnings?.length" class="rounded border border-amber-200 bg-amber-50 px-4 py-3">
        <div class="flex items-center gap-2 text-sm font-medium text-amber-800 mb-1">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Company Name Mismatch
        </div>
        <div v-for="(w, i) in s.company_warnings" :key="i" class="text-xs text-amber-700 mt-0.5">{{ w }}</div>
    </div>

    <!-- Meta Row -->
    <div class="flex items-center gap-8 text-sm text-gray-500 border-b pb-5">
        <span v-if="s.caps_user">Uploaded by <strong class="text-gray-800">{{ s.caps_user }}</strong></span>
        <span v-if="upload.caps_dispatched_at">{{ upload.caps_dispatched_at }}</span>
        <span v-if="s.caps_total">{{ s.caps_total }} total records</span>
        <span v-if="s.total_premium" class="ml-auto font-semibold text-gray-900">Total Premium: R {{ Number(s.total_premium).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}</span>
    </div>

    <!-- Stat Row -->
    <div class="grid grid-cols-8 gap-px bg-gray-200 rounded overflow-hidden border">
        <button v-for="tab in tabs" :key="tab.key"
                @click="activeTab = tab.key; search = ''"
                class="flex flex-col items-center justify-center py-4 text-center cursor-pointer transition-colors"
                :class="activeTab === tab.key ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'">
            <span class="text-2xl font-semibold tabular-nums" :class="activeTab === tab.key ? 'text-white' : ((s[tab.countKey] ?? 0) > 0 && tab.key === 'errors' ? 'text-red-600' : 'text-gray-900')">
                {{ s[tab.countKey] ?? 0 }}
            </span>
            <span class="text-[10px] font-medium uppercase tracking-wide mt-1" :class="activeTab === tab.key ? 'text-gray-300' : 'text-gray-500'">{{ tab.label }}</span>
        </button>
    </div>

    <!-- Records -->
    <div class="border rounded overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center justify-between border-b px-4 py-3 bg-gray-50">
            <span class="text-sm font-medium text-gray-700">
                {{ tabs.find(t => t.key === activeTab)?.label }}
                <span class="text-gray-400 font-normal ml-1">({{ activeRecords.length }})</span>
            </span>
            <input v-model="search" type="text" placeholder="Search..."
                   class="w-56 rounded border border-gray-300 py-1.5 px-3 text-sm focus:border-gray-500 focus:ring-0" />
        </div>

        <!-- Table -->
        <div v-if="filtered.length" class="overflow-x-auto" style="max-height: 60vh">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 border-b text-left">
                    <tr>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase w-14">#</th>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">ID Number</th>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Emp No</th>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Policy Code</th>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase text-right">Premium</th>
                        <th class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Note</th>
                        <th v-if="hasErrors" class="px-4 py-2.5 text-xs font-medium text-gray-500 uppercase min-w-[260px]">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(r, idx) in filtered" :key="idx" class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-gray-400 font-mono text-xs">{{ r.row ?? idx + 1 }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-800">{{ r.member_id || '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-600">{{ r.employee_no || '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-800">{{ [r.first_name, r.surname].filter(Boolean).join(' ') || '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-800">{{ r.policy_code || '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-600 max-w-[160px] truncate" :title="r.company">{{ r.company || '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-medium text-gray-800">{{ fmtR(r.premium) }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs max-w-[180px] truncate" :title="r.note">{{ r.note || '—' }}</td>
                        <td v-if="hasErrors" class="px-4 py-2.5">
                            <span v-if="r.error" class="text-xs text-red-700">{{ r.error }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty -->
        <div v-else class="py-16 text-center text-sm text-gray-400">
            No {{ tabs.find(t => t.key === activeTab)?.label.toLowerCase() }} records in this batch.
        </div>

        <div v-if="filtered.length" class="border-t px-4 py-2 text-xs text-gray-400 bg-gray-50">
            {{ search ? `${filtered.length} of ${activeRecords.length}` : filtered.length }} records
        </div>
    </div>

    <!-- Unmatched Headers -->
    <div v-if="s.unmatched_headers?.length" class="border rounded p-4">
        <p class="text-xs font-medium text-gray-500 uppercase mb-2">Missing Mandatory Headers</p>
        <div class="flex flex-wrap gap-2">
            <span v-for="h in s.unmatched_headers" :key="h" class="rounded border border-red-200 bg-red-50 px-2.5 py-1 text-xs text-red-700">{{ h }}</span>
        </div>
    </div>
    <div v-if="s.unmatched_optional?.length" class="border rounded p-4">
        <p class="text-xs font-medium text-gray-500 uppercase mb-2">Missing Optional Headers</p>
        <div class="flex flex-wrap gap-2">
            <span v-for="h in s.unmatched_optional" :key="h" class="rounded border border-gray-200 px-2.5 py-1 text-xs text-gray-600">{{ h }}</span>
        </div>
    </div>

    <!-- Dispatch Errors -->
    <div v-if="upload.caps_errors?.length" class="border rounded p-4">
        <p class="text-xs font-medium text-gray-500 uppercase mb-2">Dispatch Errors</p>
        <div v-for="(err, i) in upload.caps_errors" :key="i" class="mt-1 text-sm text-red-700">
            {{ err.message || JSON.stringify(err) }}
        </div>
    </div>

</div>
</div>
</AppLayout>
</template>
