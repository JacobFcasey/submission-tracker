<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ filters: Object, submissions: Object });
</script>

<template>
    <AppLayout>
        <h2 class="text-2xl font-bold">Submissions</h2>

        <div
            v-if="!submissions?.data?.length"
            class="mt-6 rounded-xl border bg-white p-6 text-slate-600"
        >
            No submissions yet.
        </div>

        <div v-else class="mt-6 overflow-hidden rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left">
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Municipality</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in submissions.data" :key="s.id" class="border-t">
                        <td class="px-4 py-3 font-medium">{{ s.reference }}</td>
                        <td class="px-4 py-3">{{ s.company?.name }}</td>
                        <td class="px-4 py-3">{{ s.municipality?.name }}</td>
                        <td class="px-4 py-3">
                            {{
                                new Intl.NumberFormat('en-ZA', {
                                    style: 'currency',
                                    currency: 'ZAR',
                                }).format(s.amount)
                            }}
                        </td>
                        <td class="px-4 py-3">{{ s.status }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
