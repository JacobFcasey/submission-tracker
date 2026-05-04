<!-- resources/js/Pages/Uploads/ViewSpreadsheet.vue -->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    upload: Object,
    file_index: Number,
    file_name: String,
    file_type: String,
    spreadsheet_data: Object,
    rejection_analysis: Object,
    download_url: String,
});

const activeTab = ref('data');
const currentPage = ref(1);
const rowsPerPage = ref(20);
const searchQuery = ref('');
const highlightRejections = ref(true);
const expandedRows = ref(new Set());

// Rejection summary with the specific counts
const rejectionSummary = computed(() => {
    const summary = props.rejection_analysis?.rejection_summary || {};

    // Ensure we have the specific rejection reasons with correct counts
    return {
        'Member Not on System': summary['Member Not on System'] || 0,
        'ID / Pay Number Mismatch': summary['ID / Pay Number Mismatch'] || 0,
        'Incorrect Division Loading': summary['Incorrect Division Loading'] || 0,
        'Duplicate Record': summary['Duplicate Record'] || 0,
        'Invalid Format': summary['Invalid Format'] || 0,
        ...(summary['Other'] ? { 'Other': summary['Other'] } : {})
    };
});

const totalRejections = computed(() => {
    return Object.values(rejectionSummary.value).reduce((sum, count) => sum + count, 0);
});

// Filtered and paginated data
const filteredData = computed(() => {
    if (!props.spreadsheet_data?.data) return [];

    let data = props.spreadsheet_data.data;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        data = data.filter(row => {
            return row.some(cell =>
                String(cell || '').toLowerCase().includes(query)
            );
        });
    }

    return data;
});

const paginatedData = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    const end = start + rowsPerPage.value;
    return filteredData.value.slice(start, end);
});

const totalPages = computed(() => {
    return Math.ceil(filteredData.value.length / rowsPerPage.value);
});

// Helper functions
const formatCellValue = (value) => {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string' && value.length > 100) {
        return value.substring(0, 100) + '...';
    }
    return String(value);
};

const isRejectionRow = (rowIndex) => {
    if (!highlightRejections.value || !props.rejection_analysis?.rejection_reasons) {
        return false;
    }

    // Data rows start at index 1 in the rejection reasons (row 2 in spreadsheet)
    const dataRowIndex = rowIndex + 1; // +1 because we skip header
    return props.rejection_analysis.rejection_reasons.some(r => r.row === dataRowIndex);
};

const getRejectionReasonForRow = (rowIndex) => {
    if (!props.rejection_analysis?.rejection_reasons) return null;

    const dataRowIndex = rowIndex + 1;
    const rejection = props.rejection_analysis.rejection_reasons.find(r => r.row === dataRowIndex);
    return rejection?.reasons?.join(', ') || null;
};

const toggleRowExpand = (index) => {
    const newSet = new Set(expandedRows.value);
    if (newSet.has(index)) {
        newSet.delete(index);
    } else {
        newSet.add(index);
    }
    expandedRows.value = newSet;
};

const exportRejectionReport = () => {
    if (!props.rejection_analysis) return;

    const data = {
        upload_reference: props.upload.reference,
        company: props.upload.company?.name,
        municipality: props.upload.municipality?.name,
        file_name: props.file_name,
        analyzed_at: props.rejection_analysis.analyzed_at,
        total_records: props.rejection_analysis.total_records,
        rejection_count: props.rejection_analysis.rejection_count,
        rejection_rate: props.rejection_analysis.rejection_rate,
        rejection_summary: props.rejection_analysis.rejection_summary,
        rejection_details: props.rejection_analysis.rejection_reasons
    };

    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rejection-report-${props.upload.reference}-${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(url);
};

onMounted(() => {
    // Log rejection summary for debugging
    console.log('Rejection Analysis:', props.rejection_analysis);
    console.log('Rejection Summary:', rejectionSummary.value);
});
</script>

<template>
    <AppLayout>
        <div class="spreadsheet-viewer-page">
            <!-- Header -->
            <div class="viewer-header">
                <div class="header-left">
                    <button @click="router.get('/uploads')" class="back-btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Uploads
                    </button>
                    <div class="file-info">
                        <h1>{{ file_name }}</h1>
                        <div class="file-meta">
                            <span class="badge" :class="{
                                'badge-pending': upload.status === 'Pending',
                                'badge-processing': upload.status === 'Processing',
                                'badge-completed': upload.status === 'Completed',
                                'badge-rejected': upload.status === 'Rejected'
                            }">
                                {{ upload.status }}
                            </span>
                            <span class="meta-item">{{ upload.company?.name }}</span>
                            <span class="meta-separator">•</span>
                            <span class="meta-item">{{ upload.municipality?.name }}</span>
                            <span class="meta-separator">•</span>
                            <span class="meta-item">Ref: {{ upload.reference }}</span>
                        </div>
                    </div>
                </div>
                <div class="header-right">
                    <a :href="download_url" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download
                    </a>
                </div>
            </div>

            <div class="viewer-content">
                <!-- Rejection Analysis Dashboard -->
                <div v-if="rejection_analysis?.has_rejections" class="rejection-dashboard">
                    <div class="dashboard-header">
                        <div class="header-title">
                            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <h2>Rejection Analysis</h2>
                        </div>
                        <div class="header-stats">
                            <div class="stat">
                                <span class="stat-label">Total Records</span>
                                <span class="stat-value">{{ rejection_analysis.total_records }}</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">Rejections</span>
                                <span class="stat-value text-red-600">{{ rejection_analysis.rejection_count }}</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">Rejection Rate</span>
                                <span class="stat-value">{{ rejection_analysis.rejection_rate }}%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Specific Rejection Reasons with Counts (42 records as specified) -->
                    <div class="rejection-summary-cards">
                        <div class="summary-card" v-for="(count, reason) in rejectionSummary" :key="reason">
                            <div class="card-header" :class="{
                                'bg-red-50': reason.includes('Member'),
                                'bg-amber-50': reason.includes('ID'),
                                'bg-purple-50': reason.includes('Division'),
                                'bg-pink-50': reason.includes('Duplicate'),
                                'bg-cyan-50': reason.includes('Format'),
                                'bg-gray-50': reason === 'Other'
                            }">
                                <h3 :class="{
                                    'text-red-700': reason.includes('Member'),
                                    'text-amber-700': reason.includes('ID'),
                                    'text-purple-700': reason.includes('Division'),
                                    'text-pink-700': reason.includes('Duplicate'),
                                    'text-cyan-700': reason.includes('Format'),
                                    'text-gray-700': reason === 'Other'
                                }">{{ reason }}</h3>
                                <span class="count-badge">{{ count }}</span>
                            </div>
                            <div class="card-body">
                                <div class="progress-bar">
                                    <div class="progress-fill"
                                         :style="{
                                             width: `${(count / totalRejections) * 100}%`,
                                             backgroundColor: reason.includes('Member') ? '#ef4444' :
                                                             reason.includes('ID') ? '#f59e0b' :
                                                             reason.includes('Division') ? '#8b5cf6' :
                                                             reason.includes('Duplicate') ? '#ec4899' :
                                                             reason.includes('Format') ? '#06b6d4' : '#64748b'
                                         }">
                                    </div>
                                </div>
                                <div class="percentage">
                                    {{ Math.round((count / totalRejections) * 100) }}% of rejections
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Stats Bar -->
                    <div class="summary-stats-bar">
                        <div class="stat-item">
                            <span class="stat-icon">📊</span>
                            <div>
                                <div class="stat-label">Member Not on System</div>
                                <div class="stat-number">{{ rejectionSummary['Member Not on System'] || 0 }}</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">🆔</span>
                            <div>
                                <div class="stat-label">ID / Pay Number Mismatch</div>
                                <div class="stat-number">{{ rejectionSummary['ID / Pay Number Mismatch'] || 0 }}</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">⚖️</span>
                            <div>
                                <div class="stat-label">Incorrect Division Loading</div>
                                <div class="stat-number">{{ rejectionSummary['Incorrect Division Loading'] || 0 }}</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">🔄</span>
                            <div>
                                <div class="stat-label">Duplicate Record</div>
                                <div class="stat-number">{{ rejectionSummary['Duplicate Record'] || 0 }}</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">⚠️</span>
                            <div>
                                <div class="stat-label">Invalid Format</div>
                                <div class="stat-number">{{ rejectionSummary['Invalid Format'] || 0 }}</div>
                            </div>
                        </div>
                        <div class="stat-item total">
                            <span class="stat-icon">📋</span>
                            <div>
                                <div class="stat-label">Total Rejections</div>
                                <div class="stat-number">{{ totalRejections }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Button -->
                    <div class="export-section">
                        <button @click="exportRejectionReport" class="btn-export">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export Rejection Report
                        </button>
                    </div>
                </div>

                <!-- No Rejections Message -->
                <div v-else-if="rejection_analysis && !rejection_analysis.has_rejections" class="success-message">
                    <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3>No Rejections Found</h3>
                    <p>This file appears to be clean with no rejection records.</p>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <button
                        class="tab"
                        :class="{ active: activeTab === 'data' }"
                        @click="activeTab = 'data'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                        Data View
                    </button>
                    <button
                        class="tab"
                        :class="{ active: activeTab === 'rejections' }"
                        @click="activeTab = 'rejections'"
                        :disabled="!rejection_analysis?.has_rejections"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Rejection Details ({{ rejection_analysis?.rejection_count || 0 }})
                    </button>
                    <button
                        class="tab"
                        :class="{ active: activeTab === 'info' }"
                        @click="activeTab = 'info'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        File Info
                    </button>
                </div>

                <!-- Data View Tab -->
                <div v-show="activeTab === 'data'" class="data-view">
                    <div class="data-controls">
                        <div class="search-box">
                            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                v-model="searchQuery"
                                placeholder="Search in data..."
                                class="search-input"
                            />
                        </div>
                        <div class="data-controls-right">
                            <label class="highlight-toggle">
                                <input type="checkbox" v-model="highlightRejections">
                                <span>Highlight Rejections</span>
                            </label>
                            <select v-model="rowsPerPage" class="per-page-select">
                                <option value="10">10 rows</option>
                                <option value="20">20 rows</option>
                                <option value="50">50 rows</option>
                                <option value="100">100 rows</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th class="row-number">#</th>
                                <th v-for="(header, index) in spreadsheet_data?.headers" :key="index">
                                    {{ header || `Column ${index + 1}` }}
                                </th>
                                <th v-if="highlightRejections" class="rejection-col">Rejection</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, rowIndex) in paginatedData" :key="rowIndex"
                                :class="{
                                        'rejection-row': isRejectionRow((currentPage - 1) * rowsPerPage + rowIndex),
                                        'expanded': expandedRows.has((currentPage - 1) * rowsPerPage + rowIndex)
                                    }"
                                @click="toggleRowExpand((currentPage - 1) * rowsPerPage + rowIndex)">
                                <td class="row-number">
                                    {{ (currentPage - 1) * rowsPerPage + rowIndex + 1 }}
                                </td>
                                <td v-for="(cell, cellIndex) in row" :key="cellIndex"
                                    :title="String(cell || '')">
                                    {{ formatCellValue(cell) }}
                                </td>
                                <td v-if="highlightRejections" class="rejection-cell">
                                        <span v-if="getRejectionReasonForRow((currentPage - 1) * rowsPerPage + rowIndex)"
                                              class="rejection-badge">
                                            {{ getRejectionReasonForRow((currentPage - 1) * rowsPerPage + rowIndex) }}
                                        </span>
                                </td>
                            </tr>
                            <tr v-if="paginatedData.length === 0">
                                <td :colspan="(spreadsheet_data?.headers?.length || 0) + 2" class="no-data">
                                    No data found
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing {{ (currentPage - 1) * rowsPerPage + 1 }} to
                            {{ Math.min(currentPage * rowsPerPage, filteredData.length) }}
                            of {{ filteredData.length }} rows
                        </div>
                        <div class="pagination-controls">
                            <button
                                @click="currentPage--"
                                :disabled="currentPage === 1"
                                class="pagination-btn"
                            >
                                Previous
                            </button>
                            <span class="page-indicator">
                                Page {{ currentPage }} of {{ totalPages }}
                            </span>
                            <button
                                @click="currentPage++"
                                :disabled="currentPage === totalPages"
                                class="pagination-btn"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Rejection Details Tab -->
                <div v-show="activeTab === 'rejections'" class="rejection-details">
                    <div class="rejection-list">
                        <div v-for="(rejection, idx) in rejection_analysis?.rejection_reasons" :key="idx"
                             class="rejection-item">
                            <div class="rejection-header">
                                <span class="row-badge">Row {{ rejection.row }}</span>
                                <span class="rejection-reasons">
                                    {{ rejection.reasons.join(', ') }}
                                </span>
                            </div>
                            <div class="rejection-data">
                                <table class="rejection-data-table">
                                    <tr v-for="(value, colIndex) in rejection.data" :key="colIndex">
                                        <th>{{ spreadsheet_data?.headers?.[colIndex] || `Column ${colIndex + 1}` }}</th>
                                        <td>{{ formatCellValue(value) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Info Tab -->
                <div v-show="activeTab === 'info'" class="file-info">
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">File Name:</span>
                            <span class="info-value">{{ file_name }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">File Type:</span>
                            <span class="info-value">{{ file_type?.toUpperCase() }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">File Size:</span>
                            <span class="info-value">
                                {{ spreadsheet_data?.file_size ? (spreadsheet_data.file_size / 1024).toFixed(2) + ' KB' : 'Unknown' }}
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Rows:</span>
                            <span class="info-value">{{ spreadsheet_data?.total_rows || 0 }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Columns:</span>
                            <span class="info-value">{{ spreadsheet_data?.total_columns || 0 }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Sheet Name:</span>
                            <span class="info-value">{{ spreadsheet_data?.current_sheet || 'Sheet1' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Upload Reference:</span>
                            <span class="info-value">{{ upload.reference }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Submitted:</span>
                            <span class="info-value">{{ upload.submitted_at_human }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped src="@/../css/Centralized/Pages/Uploads/ViewSpreadsheet.css"></style>

