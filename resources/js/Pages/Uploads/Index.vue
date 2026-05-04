<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import '@/../css/Pages/Uploads.scss';

/* ===================== Props ===================== */
const props = defineProps({
    filters: Object,
    uploads: Object,
    companies: Array,
    municipalities: Array,
    pendingDeadlines: Array,
});

/* ===================== View State ===================== */
const activeView = ref(props.filters?.view === 'history' ? 'history' : 'submit'); // 'submit' or 'history'
const isSubmitting = ref(false);

/* ===================== Form State ===================== */
const form = ref({
    municipality_id: '',
    company_id: '',
    original_files: [],
    workings_file: null,
    systems_import_file: null,
});

const muniQuery = ref('');
const companyQuery = ref('');
const reuploadReason = ref({ type: '', note: '' });

/* ===================== Data Helpers ===================== */
const fmtDateTime = (d) => (d ? new Date(d).toLocaleString() : '—');
/* ===================== Derived State ===================== */
const selectedMunicipality = computed(
    () => (props.municipalities || []).find(
        (m) => String(m.id) === String(form.value.municipality_id),
    ) || null,
);

const selectedCompany = computed(() => {
    if (!form.value.company_id) return null;
    const id = String(form.value.company_id);
    // Check props.companies first, then fall back to assigned_companies on the municipality
    return (props.companies || []).find((c) => String(c.id) === id)
        || (selectedMunicipality.value?.assigned_companies || []).find((c) => String(c.id) === id)
        || null;
});

const companiesInSelected = computed(() => {
    if (!form.value.municipality_id) return [];

    const muni = selectedMunicipality.value;

    // Use the assigned_companies from the municipality data (assignment-based)
    // since every company can submit to every municipality.
    const assigned = muni?.assigned_companies;
    if (assigned) {
        const arr = Array.isArray(assigned) ? assigned : Object.values(assigned);
        if (arr.length) return arr;
    }
    // Fallback: all companies the user is assigned to
    return props.companies || [];
});

const filteredMunicipalities = computed(() => {
    const q = muniQuery.value.trim().toLowerCase();
    return q
        ? (props.municipalities || []).filter((m) => (m?.name || '').toLowerCase().includes(q))
        : props.municipalities || [];
});

const filteredCompanies = computed(() => {
    const q = companyQuery.value.trim().toLowerCase();
    const list = companiesInSelected.value;
    return q ? list.filter((c) => (c?.name || '').toLowerCase().includes(q)) : list;
});

/* ===================== Re-upload Detection ===================== */
const existingUploadsMap = ref(new Map());
const hasExistingUploads = computed(() => {
    if (!selectedCompany.value) return false;
    return existingUploadsMap.value.has(selectedCompany.value.id);
});

const fetchExistingUploads = async () => {
    if (!selectedMunicipality.value) {
        existingUploadsMap.value.clear();
        return;
    }
    try {
        const res = await fetch(`/uploads/existing/${selectedMunicipality.value.id}`);
        if (res.ok) {
            const data = await res.json();
            existingUploadsMap.value = new Map(
                (data.details ? Object.entries(data.details) : []).map(([id, details]) => [
                    parseInt(id),
                    details
                ])
            );
        }
    } catch (error) {
        console.error('Error fetching existing uploads:', error);
        existingUploadsMap.value.clear();
    }
};

watch(
    () => form.value.municipality_id,
    async () => {
        form.value.company_id = '';
        companyQuery.value = '';
        reuploadReason.value = { type: '', note: '' };
        await fetchExistingUploads();
    }
);

/* ===================== File Helpers ===================== */
const onFilePick = (e, field) => {
    const files = Array.from(e.target?.files || []);
    if (!files.length) return;
    if (field === 'original_files')
        form.value.original_files = [...form.value.original_files, ...files];
    else form.value[field] = files[0];
};

const removeOriginalAt = (i) => {
    form.value.original_files.splice(i, 1);
};

const clearFile = (field) => {
    form.value[field] = null;
};

/* ===================== Actions ===================== */
const selectMunicipality = (municipality) => {
    form.value.municipality_id = municipality.id;
    muniQuery.value = municipality.name;
};

const selectCompany = (company) => {
    form.value.company_id = company.id;
    companyQuery.value = company.name;
};

const clearMunicipality = () => {
    form.value.municipality_id = '';
    muniQuery.value = '';
    form.value.company_id = '';
    existingUploadsMap.value.clear();
    reuploadReason.value = { type: '', note: '' };
};

const clearCompany = () => {
    form.value.company_id = '';
    companyQuery.value = '';
    reuploadReason.value = { type: '', note: '' };
};

const resetForm = () => {
    form.value = {
        municipality_id: '',
        company_id: '',
        original_files: [],
        workings_file: null,
        systems_import_file: null,
    };
    muniQuery.value = '';
    companyQuery.value = '';
    reuploadReason.value = { type: '', note: '' };
    existingUploadsMap.value.clear();
};

/* ===================== Validation ===================== */
const canSubmit = computed(() => {
    if (!form.value.municipality_id) return false;
    if (!form.value.company_id) return false;
    if (!selectedMunicipality.value?.has_deadline) return false;
    if (!form.value.original_files.length) return false;
    if (hasExistingUploads.value) {
        if (!reuploadReason.value.type) return false;
        if (reuploadReason.value.type === 'Other' && !reuploadReason.value.note?.trim()) return false;
    }
    return true;
});

const reasonOptions = [
    'Feedback / Amendments',
    'Corrections to Data',
    'Non-payment Follow-up',
    'Additional/Updated Period',
    'Wrong File Previously Uploaded',
    'Wrong Company Previously Selected',
    'Wrong Period/Date Range',
    'Replacement (Corrupted/Unreadable)',
    'Format/Template Compliance Fix',
    'Late Discovery of Missing Records',
    'Policy/Fee Override Change',
    'Other',
];

/* ===================== Submit ===================== */
const submitUpload = async () => {
    if (!canSubmit.value) return;

    const fd = new FormData();
    fd.append('municipality_id', form.value.municipality_id);
    fd.append('company_id', form.value.company_id);

    form.value.original_files.forEach((file, index) => {
        fd.append(`original_files[${index}]`, file);
    });

    if (form.value.workings_file) {
        fd.append('workings_file', form.value.workings_file);
    }

    if (form.value.systems_import_file) {
        fd.append('systems_import_file', form.value.systems_import_file);
    }

    if (hasExistingUploads.value) {
        fd.append('reupload_reason_type', reuploadReason.value.type || '');
        fd.append('reupload_reason_note', reuploadReason.value.note || '');
    }

    isSubmitting.value = true;

    router.post('/uploads', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onSuccess: () => {
            resetForm();
            activeView.value = 'history';
            router.reload();
        },
        onError: (errors) => {
            console.error('Upload errors:', errors);
            alert(errors.error || errors.message || 'Error submitting upload');
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

const removeUpload = (id) => {
    if (confirm('Delete this upload? This action cannot be undone.')) {
        router.delete(`/uploads/${id}`, {
            onSuccess: () => router.reload()
        });
    }
};

const completeUpload = (id) => {
    router.get(`/uploads/${id}/complete`);
};

// Preview modal state — two phases:
//   Phase 1: file picker (previewModalUpload set, previewContent null)
//   Phase 2: inline preview (previewContent loaded)
const previewModalUpload = ref(null);
const previewContent = ref(null);
const previewLoading = ref(false);
const previewFile = ref(null);

const openPreviewModal = (upload) => {
    previewModalUpload.value = upload;
    previewContent.value = null;
    previewFile.value = null;
    previewLoading.value = false;
};
const closePreviewModal = () => {
    previewModalUpload.value = null;
    previewContent.value = null;
    previewFile.value = null;
    previewLoading.value = false;
};
const backToFilePicker = () => {
    previewContent.value = null;
    previewFile.value = null;
    previewLoading.value = false;
};

const loadPreview = async (file) => {
    if (!file?.url) return;
    previewFile.value = file;
    previewLoading.value = true;

    // Use the dedicated JSON data_url when available; fall back to the
    // view-email-data URL derivation for older cached pages.
    const dataUrl = file.data_url
        || (file.type === 'email'
            ? file.url.replace('/view-email/', '/view-email-data/')
            : file.url.replace('/preview/', '/preview-data/'));

    try {
        const resp = await fetch(dataUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const json = await resp.json();

        if (file.type === 'email') {
            previewContent.value = {
                kind: 'email',
                data: json.email_data || json,
                fileName: json.file_name || file.name,
            };
        } else {
            // Spreadsheet
            const sd = json.spreadsheet_data || json;
            previewContent.value = {
                kind: 'spreadsheet',
                headers: sd.headers || [],
                rows: sd.rows || [],
                totalRows: sd.total_rows || 0,
                sheetName: sd.current_sheet || '',
                fileName: json.file_name || file.name,
            };
        }
    } catch (e) {
        previewContent.value = { kind: 'error', message: 'Failed to load preview: ' + e.message };
    } finally {
        previewLoading.value = false;
    }
};

/* ===================== History View & Pagination ===================== */
const historySearch = ref(props.filters?.search || '');
const historyStatusFilter = ref(props.filters?.status || '');
const perPage = ref(Number(props.filters?.per_page || props.uploads?.per_page || 12));

const applyHistoryFilters = (page = 1) => {
    router.get('/uploads', {
        search: historySearch.value || undefined,
        status: historyStatusFilter.value || undefined,
        per_page: perPage.value || 12,
        page,
        view: 'history',
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const getStatusColor = (status) => {
    const colors = {
        'Completed': 'bg-green-100 text-green-800',
        'Pending': 'bg-yellow-100 text-yellow-800',
        'Processing': 'bg-blue-100 text-blue-800',
        'Rejected': 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const getUserInitials = (user) => {
    if (!user?.name) return '??';
    return user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
};

/* ===================== Deadline Progress ===================== */
const deadlineForSelected = computed(() => {
    if (!selectedMunicipality.value) return null;
    return (props.pendingDeadlines || []).find(
        (d) => String(d?.municipality) === String(selectedMunicipality.value.name)
    ) || null;
});

const pendingCompanyNames = computed(() => deadlineForSelected.value?.pending_companies || []);
const totalCompaniesCount = computed(() => companiesInSelected.value.length);
const uploadedCount = computed(() =>
    companiesInSelected.value.filter(c => existingUploadsMap.value.has(c.id)).length
);
const progressPct = computed(() =>
    totalCompaniesCount.value
        ? Math.round((uploadedCount.value / totalCompaniesCount.value) * 100)
        : 0
);

/* ===================== Initialize ===================== */
onMounted(() => {
    resetForm();
});
</script>

<template>
    <AppLayout>
        <div class="uploads-container max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Upload Management</h1>
                <p class="text-sm text-gray-600 mt-1">Submit new uploads or track existing ones</p>
            </div>

            <!-- View Toggle -->
            <div class="flex gap-4 mb-6 border-b border-gray-200">
                <button
                    @click="activeView = 'submit'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                    :class="activeView === 'submit'
                        ? 'border-blue-600 text-blue-600'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Submit New Upload
                    </span>
                </button>
                <button
                    @click="activeView = 'history'; applyHistoryFilters(props.uploads?.current_page || 1)"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                    :class="activeView === 'history'
                        ? 'border-blue-600 text-blue-600'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Upload History
                    </span>
                </button>
            </div>

            <!-- SUBMIT VIEW -->
            <div v-if="activeView === 'submit'" class="space-y-6">
                <!-- Two-column layout for submission -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Form Column -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Step 1: Selection Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-semibold text-blue-700">1</div>
                                    <h2 class="text-lg font-semibold text-gray-900">Select Municipality & Company</h2>
                                </div>

                                <div class="space-y-6">
                                    <!-- Municipality Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Municipality <span class="text-red-500">*</span>
                                        </label>

                                        <div v-if="form.municipality_id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ selectedMunicipality?.name }}</div>
                                                    <div v-if="selectedMunicipality?.deadline_date" class="text-xs text-green-600">
                                                        Deadline: {{ selectedMunicipality.deadline_date }}
                                                    </div>
                                                </div>
                                            </div>
                                            <button @click="clearMunicipality" class="text-sm text-gray-500 hover:text-gray-700">
                                                Change
                                            </button>
                                        </div>

                                        <div v-else>
                                            <div class="relative mb-3">
                                                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                                <input
                                                    v-model="muniQuery"
                                                    type="text"
                                                    placeholder="Search municipalities..."
                                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </div>

                                            <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg divide-y">
                                                <button
                                                    v-for="m in filteredMunicipalities"
                                                    :key="m.id"
                                                    @click="selectMunicipality(m)"
                                                    :disabled="!m.has_deadline"
                                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 disabled:opacity-50 disabled:hover:bg-white"
                                                >
                                                    <div class="flex justify-between items-center">
                                                        <span class="font-medium text-gray-900">{{ m.name }}</span>
                                                        <span v-if="!m.has_deadline" class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                                                            No Active Deadline
                                                        </span>
                                                        <span v-else class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full">
                                                            Deadline: {{ m.deadline_date }}
                                                        </span>
                                                    </div>
                                                </button>
                                                <div v-if="!filteredMunicipalities.length" class="px-4 py-8 text-center text-gray-500">
                                                    No municipalities found
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Company Selection -->
                                    <div v-if="form.municipality_id">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Company <span class="text-red-500">*</span>
                                        </label>

                                        <div v-if="form.company_id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ selectedCompany?.name }}</div>
                                                    <div v-if="hasExistingUploads" class="flex items-center gap-1 text-xs text-amber-600 mt-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                        </svg>
                                                        Re-upload required
                                                    </div>
                                                </div>
                                            </div>
                                            <button @click="clearCompany" class="text-sm text-gray-500 hover:text-gray-700">
                                                Change
                                            </button>
                                        </div>

                                        <div v-else>
                                            <div class="relative mb-3">
                                                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                                <input
                                                    v-model="companyQuery"
                                                    type="text"
                                                    placeholder="Search companies..."
                                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </div>

                                            <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg divide-y">
                                                <button
                                                    v-for="c in filteredCompanies"
                                                    :key="c.id"
                                                    @click="selectCompany(c)"
                                                    class="w-full px-4 py-3 text-left hover:bg-gray-50"
                                                    :class="{ 'bg-amber-50': existingUploadsMap.has(c.id) }"
                                                >
                                                    <div class="flex justify-between items-center">
                                                        <span class="font-medium text-gray-900">{{ c.name }}</span>
                                                        <span v-if="existingUploadsMap.has(c.id)" class="text-xs px-2 py-1 bg-amber-100 text-amber-700 rounded-full">
                                                            Has Existing Uploads
                                                        </span>
                                                    </div>
                                                </button>
                                                <div v-if="!filteredCompanies.length" class="px-4 py-8 text-center text-gray-500">
                                                    No companies found
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Upload Files Card -->
                        <div v-if="form.company_id" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-semibold text-blue-700">2</div>
                                    <h2 class="text-lg font-semibold text-gray-900">Upload Files</h2>
                                </div>

                                <!-- Re-upload Reason (if needed) -->
                                <div v-if="hasExistingUploads" class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-amber-800">Re-upload Required</h4>
                                            <p class="text-xs text-amber-700 mt-1 mb-3">
                                                This company has existing uploads. Please provide a reason.
                                            </p>

                                            <select
                                                v-model="reuploadReason.type"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-3"
                                            >
                                                <option value="" disabled>Select a reason...</option>
                                                <option v-for="opt in reasonOptions" :key="opt" :value="opt">
                                                    {{ opt }}
                                                </option>
                                            </select>

                                            <textarea
                                                v-if="reuploadReason.type === 'Other'"
                                                v-model="reuploadReason.note"
                                                rows="2"
                                                placeholder="Please describe the reason..."
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Uploads -->
                                <div class="space-y-5">
                                    <!-- Email Files (Required) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Email Files <span class="text-red-500">*</span>
                                            <span class="text-xs text-gray-500 ml-2">EML or MSG format</span>
                                        </label>

                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-500 transition-colors">
                                            <input
                                                type="file"
                                                multiple
                                                accept=".eml,.msg"
                                                @change="(e) => onFilePick(e, 'original_files')"
                                                class="hidden"
                                                id="email-files"
                                            />
                                            <label for="email-files" class="cursor-pointer flex items-center justify-center gap-2">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                                <span class="text-sm text-gray-600">
                                                    <span class="font-medium text-blue-600">Click to upload</span> or drag and drop
                                                </span>
                                            </label>
                                        </div>

                                        <div v-if="form.original_files.length" class="mt-3 space-y-2">
                                            <div v-for="(file, i) in form.original_files" :key="i"
                                                 class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                                <div class="flex items-center gap-2 truncate">
                                                    <svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                    <span class="text-sm text-gray-700 truncate">{{ file.name }}</span>
                                                    <span class="text-xs text-gray-500">({{ (file.size / 1024).toFixed(0) }} KB)</span>
                                                </div>
                                                <button @click="removeOriginalAt(i)" class="text-gray-400 hover:text-red-500">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Optional Files -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Workings File
                                                <span class="text-xs text-gray-500 ml-2">Optional</span>
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="file"
                                                    @change="(e) => onFilePick(e, 'workings_file')"
                                                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                                />
                                                <button v-if="form.workings_file" @click="clearFile('workings_file')"
                                                        class="p-1 text-gray-400 hover:text-red-500">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <p v-if="form.workings_file" class="mt-1 text-xs text-gray-600 truncate">
                                                {{ form.workings_file.name }}
                                            </p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Systems Import File
                                                <span class="text-xs text-gray-500 ml-2">Optional</span>
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="file"
                                                    @change="(e) => onFilePick(e, 'systems_import_file')"
                                                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                                />
                                                <button v-if="form.systems_import_file" @click="clearFile('systems_import_file')"
                                                        class="p-1 text-gray-400 hover:text-red-500">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <p v-if="form.systems_import_file" class="mt-1 text-xs text-gray-600 truncate">
                                                {{ form.systems_import_file.name }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="mt-6 flex justify-end">
                                    <button
                                        @click="submitUpload"
                                        :disabled="!canSubmit || isSubmitting"
                                        class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                                    >
                                        <svg v-if="isSubmitting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ isSubmitting ? 'Submitting...' : hasExistingUploads ? 'Submit Re-upload' : 'Submit Upload' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar: Deadline Progress -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Deadline Status
                                </h3>

                                <div v-if="!selectedMunicipality" class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">Select a municipality to view deadline status</p>
                                </div>

                                <div v-else>
                                    <div class="mb-4">
                                        <h4 class="font-medium text-gray-900">{{ selectedMunicipality.name }}</h4>
                                        <p class="text-sm text-gray-600 mt-1">
                                            Deadline: {{ selectedMunicipality.deadline_date || 'Not set' }}
                                        </p>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Progress</span>
                                            <span class="font-medium text-gray-900">{{ progressPct }}%</span>
                                        </div>
                                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-green-600 rounded-full transition-all duration-300"
                                                 :style="{ width: progressPct + '%' }"></div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3 pt-2">
                                            <div class="text-center p-2 bg-gray-50 rounded-lg">
                                                <div class="text-xl font-semibold text-gray-900">{{ totalCompaniesCount }}</div>
                                                <div class="text-xs text-gray-600">Total</div>
                                            </div>
                                            <div class="text-center p-2 bg-green-50 rounded-lg">
                                                <div class="text-xl font-semibold text-green-700">{{ uploadedCount }}</div>
                                                <div class="text-xs text-green-600">Uploaded</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="pendingCompanyNames.length" class="mt-4">
                                        <p class="text-xs font-medium text-gray-700 mb-2">Pending Companies:</p>
                                        <div class="max-h-40 overflow-y-auto space-y-1">
                                            <div v-for="(name, idx) in pendingCompanyNames" :key="idx"
                                                 class="text-xs text-gray-600 flex items-center gap-2">
                                                <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                                {{ name }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else-if="totalCompaniesCount > 0" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <p class="text-xs text-green-800 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            All companies have submitted!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HISTORY VIEW with Pagination -->
            <div v-else-if="activeView === 'history'" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6">
                    <!-- Header with filters -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">Upload History</h2>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Items per page selector -->
                            <select
                                v-model="perPage"
                                @change="applyHistoryFilters(1)"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option :value="12">12 per page</option>
                                <option :value="20">20 per page</option>
                                <option :value="50">50 per page</option>
                                <option :value="100">100 per page</option>
                            </select>

                            <!-- Search -->
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    v-model="historySearch"
                                    type="text"
                                    placeholder="Search by company, municipality..."
                                    @keyup.enter="applyHistoryFilters(1)"
                                    class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>

                            <!-- Status filter -->
                            <select
                                v-model="historyStatusFilter"
                                @change="applyHistoryFilters(1)"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">All Status</option>
                                <option value="Pending">Pending</option>
                                <option value="Processing">Processing</option>
                                <option value="Completed">Completed</option>
                                <option value="Rejected">Rejected</option>
                            </select>

                            <button
                                @click="applyHistoryFilters(1)"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Apply
                            </button>
                        </div>
                    </div>

                    <!-- Results summary -->
                    <div class="mb-4 text-sm text-gray-600">
                        Showing {{ props.uploads?.from || 0 }} to {{ props.uploads?.to || 0 }} of {{ props.uploads?.total || 0 }} results
                    </div>

                    <!-- Uploads Table Container for scrolling reference -->
                    <div class="uploads-table-container overflow-x-auto">
                        <table v-if="(props.uploads?.data || []).length" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Municipality</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Files</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded By</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="upload in (props.uploads?.data || [])" :key="upload.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ upload.company?.name }}</div>
                                    <div v-if="upload.reupload_reason_type" class="text-xs text-amber-600">
                                        Re-upload
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ upload.municipality?.name }}</td>
                                <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="getStatusColor(upload.status)">
                                            {{ upload.status }}
                                        </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900">{{ fmtDateTime(upload.submitted_at_formatted) }}</div>
                                    <div class="text-xs text-gray-500">{{ upload.submitted_at_human }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                            <span v-if="upload.original_file_names?.length"
                                                  class="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                {{ upload.original_file_names.length }} Email
                                            </span>
                                        <span v-if="upload.workings_file_name"
                                              class="px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded-full">
                                                Workings
                                            </span>
                                        <span v-if="upload.systems_import_file_name"
                                              class="px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded-full">
                                                Systems
                                            </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs font-medium text-gray-700">
                                            {{ getUserInitials(upload.user) }}
                                        </div>
                                        <span class="text-sm text-gray-600">{{ upload.user?.name || '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Preview — eye icon opens a modal file picker -->
                                        <button
                                            v-if="(upload.all_previewable_files || []).length"
                                            @click="openPreviewModal(upload)"
                                            class="p-1 text-gray-500 hover:text-blue-600"
                                            title="Preview files"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <!-- Complete button -->
                                        <button
                                            v-if="upload.can_be_completed"
                                            @click="completeUpload(upload.id)"
                                            class="p-1 text-gray-500 hover:text-green-600"
                                            title="Complete Upload"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        </button>

                                        <!-- Delete button -->
                                        <button
                                            @click="removeUpload(upload.id)"
                                            class="p-1 text-gray-500 hover:text-red-600"
                                            title="Delete"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>

                        <!-- Empty state -->
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No uploads found</h3>
                            <p class="mt-1 text-sm text-gray-500">Try adjusting your filters or submit a new upload.</p>
                        </div>
                    </div>

                    <!-- Pagination Controls -->
                    <div v-if="(props.uploads?.last_page || 1) > 1" class="mt-6 flex items-center justify-between border-t border-gray-200 pt-6">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-700">
                                Page <span class="font-medium">{{ props.uploads?.current_page || 1 }}</span> of <span class="font-medium">{{ props.uploads?.last_page || 1 }}</span>
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="applyHistoryFilters((props.uploads?.current_page || 1) - 1)"
                                :disabled="!props.uploads?.prev_page_url"
                                class="px-3 py-1 rounded-md border border-gray-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                            >
                                Previous
                            </button>

                            <!-- Page numbers -->
                            <div class="hidden sm:flex items-center gap-1">
                                <button
                                    v-for="page in props.uploads?.last_page || 1"
                                    :key="page"
                                    @click="applyHistoryFilters(page)"
                                    class="px-3 py-1 rounded-md text-sm font-medium transition-colors"
                                    :class="(props.uploads?.current_page || 1) === page
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-700 hover:bg-gray-100'"
                                >
                                    {{ page }}
                                </button>
                            </div>

                            <button
                                @click="applyHistoryFilters((props.uploads?.current_page || 1) + 1)"
                                :disabled="!props.uploads?.next_page_url"
                                class="px-3 py-1 rounded-md border border-gray-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                            >
                                Next
                            </button>
                        </div>

                        <!-- Mobile page indicator -->
                        <div class="sm:hidden text-sm text-gray-700">
                            Page {{ props.uploads?.current_page || 1 }} of {{ props.uploads?.last_page || 1 }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Preview modal — phase 1: file picker, phase 2: inline preview -->
        <Teleport to="body">
            <div v-if="previewModalUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closePreviewModal">
                <div :class="[
                    'rounded-2xl bg-white shadow-xl transition-all',
                    previewContent ? 'w-full max-w-4xl max-h-[90vh] flex flex-col' : 'w-full max-w-md'
                ]">
                    <!-- Header -->
                    <div class="flex shrink-0 items-center justify-between border-b px-5 py-4">
                        <div class="flex items-center gap-2">
                            <button v-if="previewContent" @click="backToFilePicker" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Back to files">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <h3 class="text-base font-semibold text-gray-800">
                                {{ previewContent ? (previewFile?.name || 'Preview') : 'Preview Files' }}
                            </h3>
                        </div>
                        <button @click="closePreviewModal" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Phase 1: File picker -->
                    <template v-if="!previewContent && !previewLoading">
                        <div class="divide-y px-2 py-2">
                            <button
                                v-for="(file, fi) in previewModalUpload.all_previewable_files"
                                :key="fi"
                                @click="loadPreview(file)"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-gray-50"
                            >
                                <div v-if="file.type === 'email'" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50">
                                    <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div v-else-if="file.type === 'spreadsheet'" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-50">
                                    <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                                    </svg>
                                </div>
                                <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-800">{{ file.label }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ file.name }}</p>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                        <div class="border-t px-5 py-3 text-right">
                            <button @click="closePreviewModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Close</button>
                        </div>
                    </template>

                    <!-- Loading spinner -->
                    <div v-else-if="previewLoading" class="flex items-center justify-center py-16">
                        <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-blue-500"></div>
                    </div>

                    <!-- Phase 2: Inline email preview -->
                    <template v-else-if="previewContent?.kind === 'email'">
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                            <!-- Email headers -->
                            <div class="mb-4 space-y-1.5 rounded-lg bg-gray-50 p-4 text-sm">
                                <div v-if="previewContent.data.subject"><span class="font-semibold text-gray-500">Subject:</span> <span class="text-gray-800">{{ previewContent.data.subject }}</span></div>
                                <div v-if="previewContent.data.from"><span class="font-semibold text-gray-500">From:</span> <span class="text-gray-800">{{ previewContent.data.from }}</span></div>
                                <div v-if="previewContent.data.to"><span class="font-semibold text-gray-500">To:</span> <span class="text-gray-800">{{ previewContent.data.to }}</span></div>
                                <div v-if="previewContent.data.cc"><span class="font-semibold text-gray-500">CC:</span> <span class="text-gray-800">{{ previewContent.data.cc }}</span></div>
                                <div v-if="previewContent.data.date"><span class="font-semibold text-gray-500">Date:</span> <span class="text-gray-800">{{ previewContent.data.date }}</span></div>
                            </div>
                            <!-- Email body -->
                            <div v-if="previewContent.data.html_body" class="prose prose-sm max-w-none rounded-lg border p-4" v-html="previewContent.data.html_body"></div>
                            <pre v-else-if="previewContent.data.body" class="whitespace-pre-wrap rounded-lg border bg-white p-4 text-sm text-gray-700">{{ previewContent.data.body }}</pre>
                            <p v-else class="py-8 text-center text-gray-400">No message body available.</p>
                            <!-- Attachments -->
                            <div v-if="previewContent.data.attachments?.length" class="mt-4">
                                <h4 class="mb-2 text-xs font-semibold uppercase text-gray-400">Attachments ({{ previewContent.data.attachments.length }})</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="(att, ai) in previewContent.data.attachments" :key="ai"
                                          class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-xs text-gray-700">
                                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        {{ att.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 border-t px-5 py-3 flex justify-between">
                            <button @click="backToFilePicker" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Back</button>
                            <button @click="closePreviewModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Close</button>
                        </div>
                    </template>

                    <!-- Phase 2: Inline spreadsheet preview -->
                    <template v-else-if="previewContent?.kind === 'spreadsheet'">
                        <div class="min-h-0 flex-1 overflow-auto px-1 py-2">
                            <div class="mb-2 flex items-center justify-between px-4">
                                <span class="text-xs text-gray-400">
                                    {{ previewContent.sheetName }} — {{ previewContent.totalRows }} rows
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead class="sticky top-0 bg-gray-100">
                                        <tr>
                                            <th v-for="(h, hi) in previewContent.headers" :key="hi"
                                                class="whitespace-nowrap border-b px-3 py-2 text-left font-semibold text-gray-600">
                                                {{ h || `Col ${hi + 1}` }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, ri) in previewContent.rows" :key="ri" class="hover:bg-gray-50">
                                            <td v-for="(cell, ci) in row" :key="ci"
                                                class="whitespace-nowrap border-b border-gray-100 px-3 py-1.5 text-gray-700">
                                                {{ cell }}
                                            </td>
                                        </tr>
                                        <tr v-if="previewContent.rows.length === 0">
                                            <td :colspan="previewContent.headers.length" class="py-8 text-center text-gray-400">
                                                No data rows in this file.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="shrink-0 border-t px-5 py-3 flex justify-between">
                            <button @click="backToFilePicker" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Back</button>
                            <button @click="closePreviewModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Close</button>
                        </div>
                    </template>

                    <!-- Error -->
                    <template v-else-if="previewContent?.kind === 'error'">
                        <div class="px-5 py-8 text-center text-sm text-red-500">{{ previewContent.message }}</div>
                        <div class="border-t px-5 py-3 flex justify-between">
                            <button @click="backToFilePicker" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Back</button>
                            <button @click="closePreviewModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Close</button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped src="@/../css/Centralized/Pages/Uploads/Index.css"></style>

