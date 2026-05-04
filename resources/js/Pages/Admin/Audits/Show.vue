<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    audit: Object,
});

const sections = computed(() => [
    {
        label: 'Event',
        value: props.audit.event,
    },
    {
        label: 'Timestamp',
        value: formatDate(props.audit.created_at),
    },
    {
        label: 'Actor',
        value: props.audit.user?.name || 'System',
        hint: props.audit.user?.email || props.audit.ip_address || 'No actor details',
    },
    {
        label: 'Subject',
        value: props.audit.auditable_label || props.audit.auditable_type,
        hint: `ID: ${props.audit.auditable_id}`,
    },
    {
        label: 'URL',
        value: props.audit.url || 'N/A',
    },
    {
        label: 'Tags',
        value: props.audit.tags || 'N/A',
    },
]);

function pretty(value) {
    if (!value || !Object.keys(value).length) {
        return 'No values recorded.';
    }

    return JSON.stringify(value, null, 2);
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : 'N/A';
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
</script>

<template>
    <AppLayout title="Audit Details">
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Audit Details</h2>
                        <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset', getEventColor(audit.event)]">
                            {{ audit.event }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        Detailed payload for audit record #{{ audit.id }}.
                    </p>
                </div>
                <Link href="/admin/audits" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Back to Audit Logs
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase">Change Entries</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ audit.changes_count || 0 }}</p>
                        <p class="mt-1 text-sm text-slate-500">Total old/new value pairs captured for this record.</p>
                    </div>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                        <p class="text-xs font-semibold tracking-[0.2em] text-indigo-700 uppercase">Actor</p>
                        <p class="mt-3 text-xl font-semibold text-indigo-950">{{ audit.user?.name || 'System' }}</p>
                        <p class="mt-1 text-sm text-indigo-700">{{ audit.user?.email || audit.ip_address || 'No identity details' }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <p class="text-xs font-semibold tracking-[0.2em] text-amber-700 uppercase">Occurred</p>
                        <p class="mt-3 text-xl font-semibold text-amber-950">{{ formatDate(audit.created_at) }}</p>
                        <p class="mt-1 text-sm text-amber-700">{{ audit.tags || 'No tags recorded' }}</p>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-6">
                        <h3 class="text-lg font-semibold text-slate-900">Record Overview</h3>
                        <p class="text-sm text-slate-500">Core metadata attached to this audit event.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="section in sections" :key="section.label" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ section.label }}</p>
                            <p class="mt-2 break-all text-sm font-medium text-slate-900">{{ section.value }}</p>
                            <p v-if="section.hint" class="mt-1 text-xs text-slate-500">{{ section.hint }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 md:col-span-2 xl:col-span-3">
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">User Agent</p>
                            <p class="mt-2 break-all text-sm text-slate-900">{{ audit.user_agent || 'N/A' }}</p>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-4">
                            <h3 class="text-lg font-semibold text-slate-900">Old Values</h3>
                            <p class="text-sm text-slate-500">State before the event was recorded.</p>
                        </div>
                        <pre class="overflow-x-auto bg-slate-950 p-6 text-sm leading-6 text-slate-100">{{ pretty(audit.old_values) }}</pre>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-4">
                            <h3 class="text-lg font-semibold text-slate-900">New Values</h3>
                            <p class="text-sm text-slate-500">State or payload after the event was recorded.</p>
                        </div>
                        <pre class="overflow-x-auto bg-slate-950 p-6 text-sm leading-6 text-slate-100">{{ pretty(audit.new_values) }}</pre>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
