<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    upload: Object,
    missing_files: Array,
    next_required: String,
    can_complete: Boolean,
});

const form = ref({
    original_files: [],
    workings_file: null,
    systems_import_file: null,
});

const isSubmitting = ref(false);
const activeTab = ref('missing');
const dragActive = ref(false);
const fileInputRef = ref(null);
const workingsInputRef = ref(null);
const systemsInputRef = ref(null);

// File type helpers
const getFileTypeIcon = (fileName) => {
    const ext = fileName?.split('.').pop().toLowerCase();
    const icons = {
        pdf: '📄',
        doc: '📝', docx: '📝',
        xls: '📊', xlsx: '📊', csv: '📊',
        msg: '📧', eml: '📧',
        jpg: '🖼️', jpeg: '🖼️', png: '🖼️', gif: '🖼️',
        txt: '📃',
        zip: '📦', rar: '📦',
    };
    return icons[ext] || '📎';
};

const formatFileSize = (bytes) => {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

// Drag and drop handlers
const onDragEnter = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragActive.value = true;
};

const onDragLeave = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragActive.value = false;
};

const onDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragActive.value = true;
};

const onDrop = (e, field) => {
    e.preventDefault();
    e.stopPropagation();
    dragActive.value = false;

    const files = Array.from(e.dataTransfer?.files || []);
    if (!files.length) return;

    if (field === 'original_files') {
        form.value.original_files = [...form.value.original_files, ...files];
    } else {
        form.value[field] = files[0];
    }
};

// File handlers
const onFilePick = (e, field) => {
    const files = Array.from(e.target?.files || []);
    if (!files.length) return;

    if (field === 'original_files') {
        form.value.original_files = [...form.value.original_files, ...files];
    } else {
        form.value[field] = files[0];
    }
};

const removeOriginalAt = (i) => {
    form.value.original_files.splice(i, 1);
};

const clearFile = (field) => {
    form.value[field] = null;
    if (field === 'workings_file' && workingsInputRef.value) {
        workingsInputRef.value.value = '';
    }
    if (field === 'systems_import_file' && systemsInputRef.value) {
        systemsInputRef.value.value = '';
    }
};

const hasMissingFiles = computed(() => {
    return props.missing_files && props.missing_files.length > 0;
});

const selectedFilesCount = computed(() => {
    let count = form.value.original_files.length;
    if (form.value.workings_file) count++;
    if (form.value.systems_import_file) count++;
    return count;
});

const canSubmit = computed(() => {
    // Check if at least one missing file is being uploaded
    let hasRequiredFile = false;

    if (props.missing_files?.includes('Email Files (EML/MSG)') && form.value.original_files.length > 0) {
        hasRequiredFile = true;
    }

    if (props.missing_files?.includes('Workings File') && form.value.workings_file) {
        hasRequiredFile = true;
    }

    if (props.missing_files?.includes('Systems Import File') && form.value.systems_import_file) {
        hasRequiredFile = true;
    }

    return hasRequiredFile && !isSubmitting.value;
});

const statusColor = computed(() => {
    switch (props.upload?.status) {
        case 'Pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'Processing': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'Completed': return 'bg-green-100 text-green-800 border-green-200';
        case 'Rejected': return 'bg-red-100 text-red-800 border-red-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
});

// Submit handler
const submit = async () => {
    if (!canSubmit.value) {
        alert('Please upload at least one of the missing files to complete this upload.');
        return;
    }

    const fd = new FormData();

    form.value.original_files.forEach((file, index) => {
        fd.append(`original_files[${index}]`, file);
    });

    if (form.value.workings_file) {
        fd.append('workings_file', form.value.workings_file);
    }

    if (form.value.systems_import_file) {
        fd.append('systems_import_file', form.value.systems_import_file);
    }

    isSubmitting.value = true;

    try {
        router.post(`/uploads/${props.upload.id}/complete`, fd, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onSuccess: () => {
                // Redirect back to uploads index
                router.get('/uploads', {}, {
                    onSuccess: () => {
                        // Show success message
                        const event = new CustomEvent('toast', {
                            detail: { message: 'Upload completed successfully!', type: 'success' }
                        });
                        window.dispatchEvent(event);
                    }
                });
            },
            onError: (errors) => {
                console.error('Complete upload errors:', errors);
                let errorMessage = 'Failed to complete upload. ';

                if (errors.error) {
                    errorMessage += errors.error;
                } else if (errors.message) {
                    errorMessage += errors.message;
                } else {
                    errorMessage += 'Please try again.';
                }

                alert(errorMessage);
                isSubmitting.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            }
        });
    } catch (error) {
        console.error('Network error:', error);
        alert('Network error: ' + error.message);
        isSubmitting.value = false;
    }
};

// Navigation
const goBack = () => {
    router.get('/uploads');
};

// Format date
const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString();
};
</script>

<template>
    <AppLayout>
        <div class="complete-upload-page">
            <!-- Header -->
            <div class="complete-header">
                <div class="header-content">
                    <div class="header-left">
                        <button @click="goBack" class="back-button">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back
                        </button>
                        <div class="header-title">
                            <h1>Complete Upload</h1>
                            <div class="upload-reference">
                                Reference: <span class="font-mono">{{ upload?.reference }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="status-badge" :class="statusColor">
                        {{ upload?.status }}
                    </div>
                </div>
            </div>

            <div class="complete-content">
                <!-- Upload Summary Card -->
                <div class="summary-card">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="summary-label">Company</span>
                            <span class="summary-value">{{ upload?.company?.name || '—' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Municipality</span>
                            <span class="summary-value">{{ upload?.municipality?.name || '—' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Submitted</span>
                            <span class="summary-value">{{ upload?.submitted_at_human || formatDate(upload?.submitted_at) }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Completion</span>
                            <span class="summary-value">
                                <span class="progress-indicator">
                                    <span class="progress-dot" :class="{ 'bg-green-500': upload?.status === 'Completed', 'bg-yellow-500': upload?.status !== 'Completed' }"></span>
                                    {{ upload?.status }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Current Files Card -->
                <div class="current-files-card">
                    <h2>Current Files</h2>
                    <div class="files-grid">
                        <div class="file-item" v-if="upload?.original_file_names?.length">
                            <div class="file-icon">📧</div>
                            <div class="file-details">
                                <div class="file-name">{{ upload.original_file_names.length }} Email File(s)</div>
                                <div class="file-meta">Already uploaded</div>
                            </div>
                            <div class="file-status success">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                        <div class="file-item" v-if="upload?.workings_file_name">
                            <div class="file-icon">📝</div>
                            <div class="file-details">
                                <div class="file-name">{{ upload.workings_file_name }}</div>
                                <div class="file-meta">Workings File</div>
                            </div>
                            <div class="file-status success">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                        <div class="file-item" v-if="upload?.systems_import_file_name">
                            <div class="file-icon">📊</div>
                            <div class="file-details">
                                <div class="file-name">{{ upload.systems_import_file_name }}</div>
                                <div class="file-meta">Systems Import File</div>
                            </div>
                            <div class="file-status success">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Missing Files Alert -->
                <div v-if="hasMissingFiles" class="missing-files-alert">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-content">
                        <h3>Missing Files Required</h3>
                        <p>This upload is incomplete. Please add the following files to complete it:</p>
                        <ul class="missing-list">
                            <li v-for="file in missing_files" :key="file" class="missing-item">
                                <span class="missing-badge">Missing</span>
                                {{ file }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Add Files Section -->
                <div class="add-files-card">
                    <div class="card-header">
                        <h2>Add Missing Files</h2>
                        <p class="text-sm text-gray-600">Upload the required files to complete this submission</p>
                    </div>

                    <!-- Tabs -->
                    <div class="tabs">
                        <button
                            class="tab"
                            :class="{ active: activeTab === 'missing' }"
                            @click="activeTab = 'missing'"
                        >
                            Missing Files
                            <span v-if="missing_files?.length" class="tab-count">{{ missing_files.length }}</span>
                        </button>
                        <button
                            class="tab"
                            :class="{ active: activeTab === 'all' }"
                            @click="activeTab = 'all'"
                        >
                            All Upload Types
                        </button>
                    </div>

                    <!-- Missing Files View -->
                    <div v-if="activeTab === 'missing'" class="tab-content">
                        <!-- Email Files -->
                        <div v-if="missing_files.includes('Email Files (EML/MSG)')" class="upload-section">
                            <label class="section-label">
                                Email Files (EML/MSG)
                                <span class="required-badge">Required</span>
                            </label>
                            <div
                                class="drop-zone"
                                :class="{ 'drag-active': dragActive }"
                                @dragenter="onDragEnter"
                                @dragleave="onDragLeave"
                                @dragover="onDragOver"
                                @drop="(e) => onDrop(e, 'original_files')"
                            >
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    multiple
                                    accept=".eml,.msg"
                                    @change="(e) => onFilePick(e, 'original_files')"
                                    class="file-input"
                                    id="email-files"
                                />
                                <label for="email-files" class="drop-zone-label">
                                    <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span class="drop-zone-text">
                                        <span class="text-blue-600">Click to upload</span> or drag and drop
                                    </span>
                                    <span class="drop-zone-hint">EML or MSG files only</span>
                                </label>
                            </div>

                            <!-- Selected Files -->
                            <div v-if="form.original_files.length" class="selected-files">
                                <div class="selected-files-header">
                                    <span class="selected-files-title">Selected Files ({{ form.original_files.length }})</span>
                                    <button @click="form.original_files = []" class="clear-all-btn">
                                        Clear all
                                    </button>
                                </div>
                                <div class="files-list">
                                    <div v-for="(file, index) in form.original_files" :key="index" class="file-chip">
                                        <span class="file-chip-icon">{{ getFileTypeIcon(file.name) }}</span>
                                        <div class="file-chip-info">
                                            <span class="file-chip-name">{{ file.name }}</span>
                                            <span class="file-chip-size">{{ formatFileSize(file.size) }}</span>
                                        </div>
                                        <button @click="removeOriginalAt(index)" class="file-chip-remove">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Workings File -->
                        <div v-if="missing_files.includes('Workings File')" class="upload-section">
                            <label class="section-label">
                                Workings File
                                <span class="required-badge">Required</span>
                            </label>
                            <div class="file-input-wrapper">
                                <input
                                    ref="workingsInputRef"
                                    type="file"
                                    @change="(e) => onFilePick(e, 'workings_file')"
                                    class="file-input-custom"
                                    id="workings-file"
                                />
                                <label for="workings-file" class="file-input-label">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    Choose file
                                </label>
                                <span v-if="!form.workings_file" class="file-input-hint">No file chosen</span>
                            </div>
                            <div v-if="form.workings_file" class="selected-file">
                                <span class="file-chip-icon">{{ getFileTypeIcon(form.workings_file.name) }}</span>
                                <span class="file-name">{{ form.workings_file.name }}</span>
                                <span class="file-size">{{ formatFileSize(form.workings_file.size) }}</span>
                                <button @click="clearFile('workings_file')" class="remove-btn">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Systems Import File -->
                        <div v-if="missing_files.includes('Systems Import File')" class="upload-section">
                            <label class="section-label">
                                Systems Import File
                                <span class="required-badge">Required</span>
                            </label>
                            <div class="file-input-wrapper">
                                <input
                                    ref="systemsInputRef"
                                    type="file"
                                    @change="(e) => onFilePick(e, 'systems_import_file')"
                                    class="file-input-custom"
                                    id="systems-file"
                                />
                                <label for="systems-file" class="file-input-label">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Choose file
                                </label>
                                <span v-if="!form.systems_import_file" class="file-input-hint">No file chosen</span>
                            </div>
                            <div v-if="form.systems_import_file" class="selected-file">
                                <span class="file-chip-icon">{{ getFileTypeIcon(form.systems_import_file.name) }}</span>
                                <span class="file-name">{{ form.systems_import_file.name }}</span>
                                <span class="file-size">{{ formatFileSize(form.systems_import_file.size) }}</span>
                                <button @click="clearFile('systems_import_file')" class="remove-btn">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- All Files View -->
                    <div v-if="activeTab === 'all'" class="tab-content">
                        <!-- Email Files (Optional now) -->
                        <div class="upload-section">
                            <label class="section-label">
                                Additional Email Files (EML/MSG)
                                <span class="optional-badge">Optional</span>
                            </label>
                            <div
                                class="drop-zone"
                                :class="{ 'drag-active': dragActive }"
                                @dragenter="onDragEnter"
                                @dragleave="onDragLeave"
                                @dragover="onDragOver"
                                @drop="(e) => onDrop(e, 'original_files')"
                            >
                                <input
                                    type="file"
                                    multiple
                                    accept=".eml,.msg"
                                    @change="(e) => onFilePick(e, 'original_files')"
                                    class="file-input"
                                    id="email-files-all"
                                />
                                <label for="email-files-all" class="drop-zone-label">
                                    <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span class="drop-zone-text">
                                        <span class="text-blue-600">Click to upload</span> or drag and drop
                                    </span>
                                    <span class="drop-zone-hint">Add more email files (optional)</span>
                                </label>
                            </div>
                            <div v-if="form.original_files.length" class="selected-files">
                                <div class="selected-files-header">
                                    <span class="selected-files-title">Selected Files ({{ form.original_files.length }})</span>
                                    <button @click="form.original_files = []" class="clear-all-btn">
                                        Clear all
                                    </button>
                                </div>
                                <div class="files-list">
                                    <div v-for="(file, index) in form.original_files" :key="index" class="file-chip">
                                        <span class="file-chip-icon">{{ getFileTypeIcon(file.name) }}</span>
                                        <span class="file-chip-name">{{ file.name }}</span>
                                        <button @click="removeOriginalAt(index)" class="file-chip-remove">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Workings File (Optional) -->
                        <div class="upload-section">
                            <label class="section-label">
                                Workings File (Optional)
                            </label>
                            <div class="file-input-wrapper">
                                <input
                                    type="file"
                                    @change="(e) => onFilePick(e, 'workings_file')"
                                    class="file-input-custom"
                                    id="workings-file-all"
                                />
                                <label for="workings-file-all" class="file-input-label">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    Choose file
                                </label>
                            </div>
                            <div v-if="form.workings_file" class="selected-file">
                                <span class="file-chip-icon">{{ getFileTypeIcon(form.workings_file.name) }}</span>
                                <span class="file-name">{{ form.workings_file.name }}</span>
                                <button @click="clearFile('workings_file')" class="remove-btn">×</button>
                            </div>
                        </div>

                        <!-- Systems Import File (Optional) -->
                        <div class="upload-section">
                            <label class="section-label">
                                Systems Import File (Optional)
                            </label>
                            <div class="file-input-wrapper">
                                <input
                                    type="file"
                                    @change="(e) => onFilePick(e, 'systems_import_file')"
                                    class="file-input-custom"
                                    id="systems-file-all"
                                />
                                <label for="systems-file-all" class="file-input-label">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Choose file
                                </label>
                            </div>
                            <div v-if="form.systems_import_file" class="selected-file">
                                <span class="file-chip-icon">{{ getFileTypeIcon(form.systems_import_file.name) }}</span>
                                <span class="file-name">{{ form.systems_import_file.name }}</span>
                                <button @click="clearFile('systems_import_file')" class="remove-btn">×</button>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Files Summary -->
                    <div v-if="selectedFilesCount > 0" class="selected-summary">
                        <div class="summary-header">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ selectedFilesCount }} file(s) ready to upload</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="actions">
                        <button @click="goBack" class="btn-secondary">
                            Cancel
                        </button>
                        <button
                            @click="submit"
                            :disabled="!canSubmit"
                            class="btn-primary"
                        >
                            <svg v-if="isSubmitting" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isSubmitting ? 'Adding Files...' : 'Add Files & Complete Upload' }}
                        </button>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="help-card">
                    <div class="help-icon">ℹ️</div>
                    <div class="help-content">
                        <h4>Need Help?</h4>
                        <p>
                            You're completing a {{ upload?.status?.toLowerCase() }} upload.
                            Add the missing files listed above to complete this submission.
                            Once all required files are uploaded, the status will automatically update to "Completed".
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped src="@/../css/Centralized/Pages/Uploads/Complete.css"></style>

