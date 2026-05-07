<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const search = ref({ id_number: '', pay_number: '' });
const loading = ref(false);
const error = ref('');
const member = ref(null);
const deductionFile = ref(null);
const deductionUploaded = ref(false);
const uploading = ref(false);

const fmtR = (v) => v != null && v !== '' ? `R ${Number(v).toLocaleString('en-ZA', { minimumFractionDigits: 2 })}` : '—';
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-ZA') : '—';

const doSearch = async () => {
    if (!search.value.id_number || !search.value.pay_number) {
        error.value = 'Both fields are required: ID Number and Pay Number.';
        return;
    }
    loading.value = true;
    error.value = '';
    member.value = null;
    deductionUploaded.value = false;
    deductionFile.value = null;

    try {
        const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '');
        const resp = await fetch('/uploads/affordability/search', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify(search.value),
        });
        const data = await resp.json();
        if (data.ok) { member.value = data.member; }
        else { error.value = data.message || 'No results found.'; }
    } catch (e) { error.value = 'Search failed: ' + e.message; }
    finally { loading.value = false; }
};

const onFileSelect = (e) => { deductionFile.value = e.target.files?.[0] || null; };

const uploadDeduction = () => {
    if (!deductionFile.value) { error.value = 'Please select a deduction application file.'; return; }
    uploading.value = true;
    // Simulate upload (in real implementation this would POST to backend)
    setTimeout(() => { deductionUploaded.value = true; uploading.value = false; }, 800);
};

const clear = () => {
    search.value = { id_number: '', pay_number: '' };
    member.value = null;
    error.value = '';
    deductionFile.value = null;
    deductionUploaded.value = false;
};
</script>

<template>
<AppLayout>
<div class="px-6 py-6">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900">Affordability Check</h1>
        <p class="text-sm text-slate-500 mt-0.5">Enter both ID number and pay number to look up a member's affordability information.</p>
    </div>

    <!-- Search -->
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm mb-6">
        <form @submit.prevent="doSearch" class="flex items-end gap-3 flex-wrap">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">ID Number <span class="text-red-500">*</span></label>
                <input v-model="search.id_number" type="text" required placeholder="e.g. 8501065833088"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" />
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">Pay Number <span class="text-red-500">*</span></label>
                <input v-model="search.pay_number" type="text" required placeholder="e.g. 42763"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" />
            </div>
            <button type="submit" :disabled="loading" class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50 transition">
                {{ loading ? 'Searching...' : 'Search' }}
            </button>
            <button type="button" @click="clear" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition">Clear</button>
        </form>
        <div v-if="error" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ error }}</div>
    </div>

    <!-- Step 1: Member Found — Show Remaining Amount + Employment Info -->
    <div v-if="member && !deductionUploaded" class="space-y-5">

        <!-- Remaining Available Amount -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100">
                    <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="text-base font-semibold text-slate-900">Affordability Summary</h2>
                <span class="ml-auto rounded-full px-2.5 py-0.5 text-xs font-medium"
                      :class="member.status === 'ACTIVE' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                    {{ member.status || '—' }}
                </span>
            </div>
            <div class="p-5">
                <div class="text-center py-4 mb-4 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1">Remaining Available Amount</div>
                    <div class="text-3xl font-bold" :class="member.affordability_remaining != null && Number(member.affordability_remaining) > 0 ? 'text-emerald-700' : 'text-red-600'">
                        {{ member.affordability_remaining != null ? fmtR(member.affordability_remaining) : '—' }}
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
                        <div class="text-sm font-semibold text-slate-800">{{ fmtR(member.affordability_existing_deductions) }}</div>
                        <div class="text-[10px] text-slate-500">Existing Deductions</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
                        <div class="text-sm font-semibold text-slate-800">{{ member.affordability_max_deduction != null ? fmtR(member.affordability_max_deduction) : '—' }}</div>
                        <div class="text-[10px] text-slate-500">Max Deduction</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
                        <div class="text-sm font-semibold text-slate-800">{{ member.affordability_utilization != null ? Number(member.affordability_utilization).toFixed(2) + '%' : '—' }}</div>
                        <div class="text-[10px] text-slate-500">Utilization</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
                        <div class="text-sm font-semibold text-slate-800">{{ member.affordability_protected_income != null ? fmtR(member.affordability_protected_income) : '—' }}</div>
                        <div class="text-[10px] text-slate-500">Protected Income</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Deduction Application -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                </div>
                <h2 class="text-base font-semibold text-slate-900">Upload Deduction Application</h2>
            </div>
            <div class="p-5">
                <p class="text-sm text-slate-500 mb-4">Upload the deduction application document to proceed with the full affordability assessment.</p>
                <div class="flex items-center gap-3">
                    <label class="flex-1 flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 cursor-pointer hover:border-slate-400 hover:bg-slate-100 transition">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span class="text-sm text-slate-600">{{ deductionFile ? deductionFile.name : 'Choose file or drag here' }}</span>
                        <input type="file" class="hidden" @change="onFileSelect" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv" />
                    </label>
                    <button @click="uploadDeduction" :disabled="!deductionFile || uploading"
                            class="rounded-lg bg-slate-800 px-5 py-3 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50 transition whitespace-nowrap">
                        {{ uploading ? 'Uploading...' : 'Upload & Continue' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: After upload — Full Personal + Affordability Details -->
    <div v-if="member && deductionUploaded" class="space-y-5">

        <!-- Success banner -->
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            Deduction application uploaded. Full member details below.
        </div>

        <!-- Personal Information -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100">
                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h2 class="text-base font-semibold text-slate-900">Personal Information</h2>
                <span class="ml-auto rounded-full px-2.5 py-0.5 text-xs font-medium"
                      :class="member.status === 'ACTIVE' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                    {{ member.status || '—' }}
                </span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Title</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ member.title || '—' }}</div></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Initials</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ member.initials || '—' }}</div></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">First Name</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">{{ member.first_name || '—' }}</div></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Surname</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">{{ member.surname || '—' }}</div></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">ID Number</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-mono text-slate-800">{{ member.id_number || '—' }}</div></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Date of Birth</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ fmtDate(member.date_of_birth) }}</div></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Pay Number</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-mono text-slate-800">{{ member.pay_number || '—' }}</div></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Gender</label>
                        <div class="mt-0.5 flex items-center gap-3 text-sm text-slate-700">
                            <label class="flex items-center gap-1"><span class="h-3 w-3 rounded-full border-2" :class="member.gender === 1 ? 'border-slate-800 bg-slate-800' : 'border-slate-300'"></span> Male</label>
                            <label class="flex items-center gap-1"><span class="h-3 w-3 rounded-full border-2" :class="member.gender === 2 ? 'border-slate-800 bg-slate-800' : 'border-slate-300'"></span> Female</label>
                            <label class="flex items-center gap-1"><span class="h-3 w-3 rounded-full border-2" :class="member.gender === 3 ? 'border-slate-800 bg-slate-800' : 'border-slate-300'"></span> Other</label>
                        </div>
                    </div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Cell Number</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ member.cell_number || '—' }}</div></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Email</label><div class="mt-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ member.email || '—' }}</div></div>
                </div>
            </div>
        </div>

        <!-- Affordability Information -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100">
                    <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="text-base font-semibold text-slate-900">Affordability Information</h2>
                <div v-if="member.affordability_last_updated" class="ml-auto text-xs text-slate-400">
                    Last updated: <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700 font-medium">{{ fmtDate(member.affordability_last_updated) }}</span>
                </div>
            </div>
            <div class="p-5 space-y-6">
                <div>
                    <h3 class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-3">Tier Configuration Applied</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ member.affordability_tier_used || '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Tier Used</p></div>
                        <div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ member.affordability_take_home_percent != null ? member.affordability_take_home_percent + '%' : '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Take Home Percentage Used</p></div>
                        <div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ member.affordability_min_take_home != null ? fmtR(member.affordability_min_take_home) : '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Minimum Take Home Amount Used</p></div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-3">
                        <div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ member.affordability_effective_income != null ? fmtR(member.affordability_effective_income) : '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Effective Income Used</p><p class="text-[9px] text-slate-400">Basic + 30% of variable income</p></div>
                    </div>
                </div>
                <div>
                    <h3 class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-3">Calculation Results</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ member.affordability_protected_income != null ? fmtR(member.affordability_protected_income) : '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Protected Income</p></div>
                        <div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ member.affordability_max_deduction != null ? fmtR(member.affordability_max_deduction) : '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Maximum Deduction Amount</p></div>
                        <div><div class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-800">{{ fmtR(member.affordability_existing_deductions) }}</div><p class="text-[10px] text-slate-400 mt-0.5">Existing Deductions</p></div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-3">
                        <div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ member.affordability_remaining != null ? fmtR(member.affordability_remaining) : '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Remaining Available Amount</p></div>
                        <div><div class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-800">{{ member.affordability_utilization != null ? Number(member.affordability_utilization).toFixed(2) : '—' }}</div><p class="text-[10px] text-slate-400 mt-0.5">Utilization Percentage</p></div>
                    </div>
                </div>
                <div>
                    <h3 class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-3">Income Summary</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="rounded-lg border border-slate-200 bg-white p-3 text-center"><div class="text-lg font-bold text-slate-800">{{ fmtR(member.inc_basic) }}</div><div class="text-[10px] text-slate-500">Basic</div></div>
                        <div class="rounded-lg border border-slate-200 bg-white p-3 text-center"><div class="text-lg font-bold text-slate-800">{{ fmtR(member.inc_net_salary) }}</div><div class="text-[10px] text-slate-500">Net Salary</div></div>
                        <div class="rounded-lg border border-slate-200 bg-white p-3 text-center"><div class="text-lg font-bold text-red-600">{{ fmtR(member.exp_total) }}</div><div class="text-[10px] text-slate-500">Total Deductions</div></div>
                        <div class="rounded-lg border border-slate-200 bg-white p-3 text-center"><div class="text-lg font-bold text-emerald-700">{{ fmtR(member.inc_net_salary - member.exp_total) }}</div><div class="text-[10px] text-slate-500">Take Home</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Empty state -->
    <div v-if="!member && !loading && !error" class="rounded-xl border border-slate-200 bg-white py-20 text-center shadow-sm">
        <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <p class="mt-3 text-sm text-slate-500">Search for a member to view their affordability details</p>
    </div>

</div>
</AppLayout>
</template>
