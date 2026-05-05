<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    filters: Object,
    uploads: Object,
    statusOptions: Array,
    perPageOptions: Array,
    currentPerPage: Number,
    premiumBatchData: Object,
});

let Papa = null;
let readXlsxFile = null;

onMounted(async () => {
    // Load PapaParse
    if (typeof window.Papa === 'undefined') {
        try {
            const papaModule = await import('papaparse');
            Papa = papaModule.default || papaModule;
            if (typeof window !== 'undefined') {
                window.Papa = Papa;
            }
        } catch (err) {
            console.warn('PapaParse failed to load', err);
        }
    } else {
        Papa = window.Papa;
    }

    await ensureXlsxReaderLoaded();
});

const ensureXlsxReaderLoaded = async () => {
    if (readXlsxFile) return readXlsxFile;

    try {
        const module = await import('read-excel-file');
        readXlsxFile = module.default || module;
        return readXlsxFile;
    } catch (err) {
        console.warn('XLSX reader failed to load', err);
        return null;
    }
};

/* ------------ Filters ------------ */
const form = ref({
    status: props.filters?.status || '',
    search: props.filters?.search || '',
    per_page: props.currentPerPage || 20,
});

// Watch for per_page changes
watch(() => form.value.per_page, () => {
    applyFilters();
});

const applyFilters = () =>
    router.get('/uploads/history', form.value, { preserveState: true, replace: true });

const resetFilters = () => {
    form.value = { status: '', search: '', per_page: 20 };
    applyFilters();
};

const exportData = () => {
    const params = new URLSearchParams();
    Object.entries(form.value).forEach(([key, value]) => {
        if (value) params.append(key, value);
    });
    window.location.href = `/uploads/export?${params}`;
};

/* ------------ File Type Detection ------------ */
const getFileType = (filename) => {
    if (!filename) return 'unknown';
    const ext = (filename.split('.').pop() || '').toLowerCase();

    if (['eml', 'msg'].includes(ext)) return 'email';
    if (['pdf'].includes(ext)) return 'pdf';
    if (['jpg','jpeg','png','gif','webp','bmp'].includes(ext)) return 'image';
    if (['xlsx','xls','xlsm','xlsb','csv'].includes(ext)) return 'spreadsheet';
    if (['txt','log','json','xml','html','htm'].includes(ext)) return 'text';
    if (['doc','docx'].includes(ext)) return 'word';
    if (['ppt','pptx'].includes(ext)) return 'powerpoint';

    return 'unknown';
};

const getFileExtension = (filename) => (filename?.split('.').pop() || '').toLowerCase();

const requiresDedicatedPreview = (filename, hasPreviewUrl = false) => {
    const ext = getFileExtension(filename);
    return hasPreviewUrl && ['xlsx', 'xls', 'xlsm', 'xlsb', 'csv'].includes(ext);
};

/* ------------ Email Parsing ------------ */
const sanitizeEmailHtml = (html) => {
    if (!html) return '';
    return html
        .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
        .replace(/\son\w+="[^"]*"/gi, '')
        .replace(/\son\w+='[^']*'/gi, '');
};

const parseEmailContent = async (text) => {
    const lines = String(text || '').split(/\r?\n/);
    const headers = {};
    let bodyStart = 0;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        if (line.trim() === '') {
            bodyStart = i + 1;
            break;
        }

        const colonIndex = line.indexOf(':');
        if (colonIndex === -1) continue;

        const key = line.substring(0, colonIndex).trim().toLowerCase();
        let value = line.substring(colonIndex + 1).trim();
        while (i + 1 < lines.length && /^\s/.test(lines[i + 1])) {
            i++;
            value += ` ${lines[i].trim()}`;
        }
        headers[key] = value;
    }

    const body = lines.slice(bodyStart).join('\n').trim();
    return {
        subject: headers.subject || '',
        from: headers.from || '',
        to: headers.to || '',
        cc: headers.cc || '',
        date: headers.date || '',
        body,
        html_body: '',
        attachments: [],
        has_attachments: false,
        parsed_successfully: true,
    };
};

const fetchEmailPreviewData = async (dataUrl, fallbackDownloadUrl) => {
    if (dataUrl) {
        const response = await fetch(dataUrl, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error(`Email preview failed (HTTP ${response.status})`);
        }
        const payload = await response.json();
        return {
            emailData: payload.email_data || {},
            rawContent: payload.raw_content || '',
            downloadUrl: payload.download_url || fallbackDownloadUrl || '',
            openOutlookHint: Boolean(payload.open_outlook_hint),
        };
    }

    const rawResponse = await fetch(fallbackDownloadUrl);
    if (!rawResponse.ok) {
        throw new Error(`Email file load failed (HTTP ${rawResponse.status})`);
    }
    const raw = await rawResponse.text();
    return {
        emailData: await parseEmailContent(raw),
        rawContent: raw,
        downloadUrl: fallbackDownloadUrl || '',
        openOutlookHint: true,
    };
};

/* ------------ CSV Parsing ------------ */
const parseCSVWithPapa = async (text) => {
    return new Promise((resolve) => {
        if (!Papa) {
            console.warn('PapaParse not loaded, using fallback CSV parser');
            resolve(parseCSVBasic(text));
            return;
        }

        Papa.parse(text, {
            header: true,
            skipEmptyLines: true,
            dynamicTyping: false,
            transformHeader: h => h?.trim() || '',
            complete: (result) => {
                const headers = result.meta.fields || [];
                const rows = result.data.map(row => headers.map(h => row[h] ?? ''));
                resolve({ headers, rows });
            },
            error: (err) => {
                console.warn('PapaParse error', err);
                resolve(parseCSVBasic(text));
            }
        });
    });
};

const parseCSVBasic = (csvText) => {
    const lines = csvText.split('\n').filter(line => line.trim());
    if (lines.length === 0) {
        return { headers: [], rows: [] };
    }

    const headers = parseCSVLine(lines[0]);
    const rows = [];

    for (let i = 1; i < Math.min(lines.length, 1001); i++) {
        const values = parseCSVLine(lines[i]);
        rows.push(values);
    }

    return { headers, rows };
};

const parseCSVLine = (line) => {
    const values = [];
    let currentValue = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        const nextChar = line[i + 1];

        if (char === '"' && !inQuotes) {
            inQuotes = true;
        } else if (char === '"' && inQuotes && nextChar === '"') {
            currentValue += '"';
            i++;
        } else if (char === '"' && inQuotes) {
            inQuotes = false;
        } else if (char === ',' && !inQuotes) {
            values.push(currentValue);
            currentValue = '';
        } else {
            currentValue += char;
        }
    }

    values.push(currentValue);
    return values;
};

/* ------------ Spreadsheet Preview ------------ */
const spreadsheetData = ref({
    headers: [],
    rows: [],
    currentPage: 1,
    itemsPerPage: 50,
    totalRows: 0,
    error: null
});

const loadSpreadsheetData = async (url, filename) => {
    const data = {
        headers: [],
        rows: [],
        totalRows: 0,
        error: null
    };

    try {
        const resp = await fetch(url);
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

        const ext = filename.toLowerCase().split('.').pop();

        if (ext === 'csv') {
            const text = await resp.text();
            const { headers, rows } = await parseCSVWithPapa(text);
            data.headers = headers;
            data.rows = rows.slice(0, 500);
            data.totalRows = rows.length;
        } else if (['xlsx', 'xlsm'].includes(ext)) {
            const xlsxReader = await ensureXlsxReaderLoaded();
            if (!xlsxReader) {
                throw new Error('Excel parser not available');
            }

            const blob = await resp.blob();
            let json;
            try {
                json = await xlsxReader(blob);
            } catch (xlsxErr) {
                // The file may be corrupted or use an unsupported zip structure.
                // Try the server-side preview endpoint as a fallback.
                console.warn('Client-side xlsx parse failed, trying server preview', xlsxErr);
                const serverData = await loadSpreadsheetFromServer(url);
                if (serverData) return serverData;
                throw new Error(
                    'This Excel file could not be read. It may be corrupted or in an unsupported format. ' +
                    'Try re-saving it as .xlsx in Excel and uploading again.'
                );
            }

            if (json.length === 0) {
                data.error = 'Empty spreadsheet';
                return data;
            }

            let startRow = 0;
            const firstRow = json[0] || [];
            if (firstRow.some(v => v && String(v).trim())) {
                data.headers = firstRow.map(v => String(v).trim() || `Col${startRow + 1}`);
                startRow = 1;
            } else {
                const maxCols = Math.max(...json.map(r => r.length), 0);
                data.headers = Array.from({ length: maxCols }, (_, i) => `Column ${i + 1}`);
            }

            data.rows = json.slice(startRow, startRow + 500);
            data.totalRows = Math.max(0, json.length - startRow);
        } else if (['xls', 'xlsb'].includes(ext)) {
            data.error = 'Legacy Excel format (.xls/.xlsb) is not supported in-browser. Please download the file or re-save as .xlsx.';
        } else {
            data.error = 'Unsupported spreadsheet format';
        }
    } catch (err) {
        console.error('Spreadsheet load failed', err);
        data.error = err.message;
    }

    return data;
};

/**
 * Fallback: try loading spreadsheet data via the server-side preview-data
 * endpoint (PhpSpreadsheet can handle files that the JS parser cannot).
 */
const loadSpreadsheetFromServer = async (downloadUrl) => {
    try {
        // Convert download URL to preview-data URL
        const previewUrl = downloadUrl
            .replace('/download/', '/preview-data/')
            .replace(/\/download\?/, '/preview-data?');
        const resp = await fetch(previewUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!resp.ok) return null;
        const json = await resp.json();
        const sd = json.spreadsheet_data || json;
        if (!sd.headers && !sd.rows) return null;
        return {
            headers: sd.headers || [],
            rows: (sd.rows || []).slice(0, 500),
            totalRows: sd.total_rows || (sd.rows || []).length,
            error: null,
        };
    } catch {
        return null;
    }
};

/* ------------ Preview Management ------------ */
const preview = ref({
    show: false,
    title: '',
    url: '',
    type: '',
    content: '',
    emailData: null,
    rawContent: '',
    downloadUrl: '',
    openOutlookHint: false,
    isSpreadsheet: false,
    loading: false
});

const openDedicatedPreview = (targetUrl) => {
    const opened = window.open(targetUrl, '_blank', 'noopener,noreferrer');
    if (!opened) {
        window.location.href = targetUrl;
    }
};

const openPreview = async (url, name, previewUrl = null, emailDataUrl = null) => {
    if (!url) return;

    if (requiresDedicatedPreview(name, !!previewUrl)) {
        openDedicatedPreview(previewUrl || url);
        return;
    }

    const type = getFileType(name);
    const isSpreadsheet = type === 'spreadsheet';
    const isEmail = type === 'email';

    preview.value = {
        show: true,
        title: name,
        url,
        type,
        content: '',
        emailData: null,
        rawContent: '',
        downloadUrl: '',
        openOutlookHint: false,
        isSpreadsheet,
        loading: true
    };

    try {
        if (isEmail) {
            const emailPayload = await fetchEmailPreviewData(emailDataUrl, url);
            preview.value.emailData = emailPayload.emailData;
            preview.value.rawContent = emailPayload.rawContent;
            preview.value.downloadUrl = emailPayload.downloadUrl;
            preview.value.openOutlookHint = emailPayload.openOutlookHint;
        } else if (isSpreadsheet) {
            const data = await loadSpreadsheetData(url, name);
            spreadsheetData.value = {
                ...data,
                currentPage: 1,
                itemsPerPage: 50
            };
        } else if (type === 'text' || type === 'word' || type === 'powerpoint') {
            const res = await fetch(url);
            preview.value.content = await res.text();
        }
    } catch (error) {
        console.error('Preview error:', error);
        preview.value.content = 'Unable to load preview';
        preview.value.emailData = null;
    } finally {
        preview.value.loading = false;
    }
};

const closePreview = () => {
    preview.value = {
        show: false,
        title: '',
        url: '',
        type: '',
        content: '',
        emailData: null,
        rawContent: '',
        downloadUrl: '',
        openOutlookHint: false,
        isSpreadsheet: false,
        loading: false
    };
    spreadsheetData.value = {
        headers: [],
        rows: [],
        currentPage: 1,
        itemsPerPage: 50,
        totalRows: 0,
        error: null
    };
};

/* ------------ All Files Preview ------------ */
const allFilesPreview = ref({
    show: false,
    upload: null,
    currentIndex: 0,
    files: [],
    loading: false
});

const openAllFilesPreview = (upload) => {
    if (!upload) return;

    const files = [];

    // Add email files
    if (upload.original_file_names?.length) {
        upload.original_file_names.forEach((name, index) => {
            const filePreviewUrl = upload.preview_urls?.[index] || null;
            files.push({
                name,
                url: upload.original_file_urls?.[index] || '',
                previewUrl: filePreviewUrl,
                emailDataUrl: upload.email_preview_data_urls?.[index] || null,
                requiresDedicatedPreview: requiresDedicatedPreview(name, !!filePreviewUrl),
                type: getFileType(name),
                category: 'email',
                content: ''
            });
        });
    }

    // Add system import file
    if (upload.systems_import_file_name) {
        files.push({
            name: upload.systems_import_file_name,
            url: upload.systems_import_file_url || '',
            previewUrl: null,
            requiresDedicatedPreview: requiresDedicatedPreview(upload.systems_import_file_name, false),
            type: getFileType(upload.systems_import_file_name),
            category: 'system_import',
            content: ''
        });
    }

    // Add workings file
    if (upload.workings_file_name) {
        files.push({
            name: upload.workings_file_name,
            url: upload.workings_file_url || '',
            previewUrl: null,
            requiresDedicatedPreview: requiresDedicatedPreview(upload.workings_file_name, false),
            type: getFileType(upload.workings_file_name),
            category: 'workings',
            content: ''
        });
    }

    allFilesPreview.value = {
        show: true,
        upload,
        currentIndex: 0,
        files,
        loading: false
    };

    // Load content for first file
    if (files.length > 0) {
        loadAllFilesFileContent(0);
    }
};

const closeAllFilesPreview = () => {
    allFilesPreview.value = {
        show: false,
        upload: null,
        currentIndex: 0,
        files: [],
        loading: false
    };
};

const loadAllFilesFileContent = async (index) => {
    const file = allFilesPreview.value.files[index];
    if (!file) return;

    allFilesPreview.value.loading = true;

    try {
        if (file.requiresDedicatedPreview) {
            file.content = 'Open this file in the dedicated preview page.';
            return;
        }

        if (file.type === 'email') {
            const emailPayload = await fetchEmailPreviewData(file.emailDataUrl, file.url);
            file.emailData = emailPayload.emailData;
            file.rawContent = emailPayload.rawContent;
            file.downloadUrl = emailPayload.downloadUrl;
            file.openOutlookHint = emailPayload.openOutlookHint;
        } else if (file.type === 'spreadsheet') {
            const data = await loadSpreadsheetData(file.url, file.name);
            file.spreadsheetData = {
                ...data,
                currentPage: 1,
                itemsPerPage: 50
            };
        } else if (file.type === 'text' || file.type === 'word' || file.type === 'powerpoint') {
            const res = await fetch(file.url);
            file.content = await res.text();
        }
    } catch (error) {
        console.error('Error loading file:', error);
        file.content = 'Unable to load preview';
        file.error = error.message;
    } finally {
        allFilesPreview.value.loading = false;
    }
};

const changeFile = (index) => {
    if (index < 0 || index >= allFilesPreview.value.files.length) return;
    allFilesPreview.value.currentIndex = index;
    loadAllFilesFileContent(index);
};

const nextFile = () => {
    const { currentIndex, files } = allFilesPreview.value;
    if (currentIndex < files.length - 1) {
        changeFile(currentIndex + 1);
    }
};

const prevFile = () => {
    const { currentIndex } = allFilesPreview.value;
    if (currentIndex > 0) {
        changeFile(currentIndex - 1);
    }
};

const getFileCategoryLabel = (category) => {
    const labels = {
        email: 'Email',
        system_import: 'System Import',
        workings: 'Workings'
    };
    return labels[category] || category;
};

/* ------------ Spreadsheet Pagination Helpers ------------ */
const getCurrentSpreadsheetPage = computed(() => {
    const start = (spreadsheetData.value.currentPage - 1) * spreadsheetData.value.itemsPerPage;
    const end = start + spreadsheetData.value.itemsPerPage;
    return spreadsheetData.value.rows.slice(start, end);
});

const totalSpreadsheetPages = computed(() => {
    return Math.ceil(spreadsheetData.value.totalRows / spreadsheetData.value.itemsPerPage);
});

const changeSpreadsheetPage = (page) => {
    if (page >= 1 && page <= totalSpreadsheetPages.value) {
        spreadsheetData.value.currentPage = page;
    }
};

const getAllFilesSpreadsheetPage = computed(() => {
    const file = allFilesPreview.value.files[allFilesPreview.value.currentIndex];
    if (!file || !file.spreadsheetData) return [];

    const start = (file.spreadsheetData.currentPage - 1) * file.spreadsheetData.itemsPerPage;
    const end = start + file.spreadsheetData.itemsPerPage;
    return file.spreadsheetData.rows.slice(start, end) || [];
});

const getAllFilesTotalSpreadsheetPages = computed(() => {
    const file = allFilesPreview.value.files[allFilesPreview.value.currentIndex];
    if (!file || !file.spreadsheetData) return 0;

    const rows = file.spreadsheetData.rows || [];
    const itemsPerPage = file.spreadsheetData.itemsPerPage || 50;
    return Math.ceil(rows.length / itemsPerPage);
});

const changeAllFilesSpreadsheetPage = (page) => {
    const file = allFilesPreview.value.files[allFilesPreview.value.currentIndex];
    if (!file || !file.spreadsheetData) return;

    if (page >= 1 && page <= getAllFilesTotalSpreadsheetPages.value) {
        file.spreadsheetData.currentPage = page;
    }
};

/* ------------ Utility Functions ------------ */
const formatDate = (d) => (d ? new Date(d).toLocaleDateString() : '—');
const formatDateTime = (d) => (d ? new Date(d).toLocaleString() : '—');
const formatTimeAgo = (d) => {
    if (!d) return '—';
    const date = new Date(d);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) return 'just now';
    if (diffMin < 60) return `${diffMin} minute${diffMin !== 1 ? 's' : ''} ago`;
    if (diffHour < 24) return `${diffHour} hour${diffHour !== 1 ? 's' : ''} ago`;
    if (diffDay < 7) return `${diffDay} day${diffDay !== 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
};

const statusPill = (s) => {
    const classes = {
        Pending: 'bg-yellow-100 text-yellow-800',
        Processing: 'bg-blue-100 text-blue-800',
        Completed: 'bg-green-100 text-green-800',
        Rejected: 'bg-red-100 text-red-800',
        default: 'bg-gray-100 text-gray-800'
    };
    return classes[s] || classes.default;
};

const getReuploadReason = (u) => {
    if (!u) return '';

    const t1 = u?.reupload_reason_type || u?.reason_type || u?.reupload_type;
    const n1 = u?.reupload_reason_note || u?.reason_note || u?.reupload_note;
    const t2 = u?.reupload_reason?.type || u?.meta?.reupload_reason?.type || u?.meta?.reupload?.type;
    const n2 = u?.reupload_reason?.note || u?.meta?.reupload_reason?.note || u?.meta?.reupload?.note;

    const type = t1 ?? t2 ?? '';
    const note = n1 ?? n2 ?? '';

    if (!type && !note) return '';
    return type && note ? `${type} — ${note}` : type || note;
};

const dateOrNull = (d) => (d ? new Date(d) : null);
const sortByDescDate = (a, b) => {
    const da = dateOrNull(a.submitted_at) || dateOrNull(a.system_import_date) || dateOrNull(a.created_at);
    const db = dateOrNull(b.submitted_at) || dateOrNull(b.system_import_date) || dateOrNull(b.created_at);
    return (db?.getTime?.() || 0) - (da?.getTime?.() || 0);
};

/* ------------ User Avatar Helper ------------ */
const getUserAvatar = (user) => {
    if (!user) return { initials: '??', name: 'Unknown User', email: '' };

    const initials = user.initials ||
        (user.name ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : '??');

    return {
        initials,
        name: user.name || 'Unknown User',
        email: user.email || ''
    };
};

/* ------------ Group by company ------------ */
const expanded = ref(new Set());
const toggle = (id) => {
    const s = new Set(expanded.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expanded.value = s;
};
const isOpen = (id) => expanded.value.has(id);

const grouped = computed(() => {
    const rows = (props.uploads?.data || []).slice().sort(sortByDescDate);
    const map = new Map();

    for (const u of rows) {
        const cid = u.company?.id ?? `unknown-${u.company?.name || 'N/A'}`;
        if (!map.has(cid)) {
            map.set(cid, {
                companyId: cid,
                company: u.company,
                municipality: u.municipality,
                items: [],
            });
        }
        map.get(cid).items.push(u);
    }

    const out = Array.from(map.values()).map((g) => {
        g.items.sort(sortByDescDate);
        g.latest = g.items[0];
        g.history = g.items.slice(1);
        return g;
    });

    out.sort((a, b) => sortByDescDate(a.latest, b.latest));
    return out;
});

/* ------------ File Count Helpers ------------ */
const emailCount = (u) => u?.original_file_names?.length || 0;
const hasSystems = (u) => !!u?.systems_import_file_name;
const hasWorkings = (u) => !!u?.workings_file_name;

const totalFiles = (u) => {
    let count = emailCount(u);
    if (hasSystems(u)) count++;
    if (hasWorkings(u)) count++;
    return count;
};

const premiumBatchRows = computed(() => {
    const payload = props.premiumBatchData?.data;
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.content)) return payload.content;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.items)) return payload.items;
    return [];
});

const toUpperNormalized = (value) => String(value ?? '').trim().toUpperCase();
const normalizeText = (value) => String(value ?? '').trim();

const normalizeAmount = (value) => {
    if (value === null || value === undefined || value === '') return null;
    if (typeof value === 'number') return Number(value.toFixed(2));

    const cleaned = String(value).replace(/[^0-9.-]/g, '');
    const parsed = Number.parseFloat(cleaned);
    return Number.isFinite(parsed) ? Number(parsed.toFixed(2)) : null;
};

const makeRecordKey = (record) => {
    const memberId = toUpperNormalized(record.memberId);
    const personnelNumber = toUpperNormalized(record.personelNumber || record.personnelNumber);
    const policyCode = toUpperNormalized(record.policyCode);
    return `${memberId}|${personnelNumber}|${policyCode}`;
};

const mappedPremiumRows = computed(() => {
    return premiumBatchRows.value.map((row, idx) => ({
        key: makeRecordKey(row),
        memberId: row.memberId ?? row.member_id ?? '',
        personelNumber: row.personelNumber ?? row.personnelNumber ?? row.personnel_number ?? '',
        policyCode: row.policyCode ?? row.policy_code ?? '',
        premiumAmount: normalizeAmount(row.premiumAmount ?? row.premium_amount ?? row.amountPayable ?? row.amount),
        raw: row,
        index: idx + 1,
    }));
});

const compareState = ref({
    show: false,
    loading: false,
    error: null,
    uploadReference: '',
    counts: {
        importOnly: 0,
        workingsOnly: 0,
        premiumMismatch: 0,
        matched: 0,
        importRecords: 0,
        workingsRecords: 0,
        importStatus1: 0,
        importStatus2: 0,
        importStatus0: 0,
        workingsStatus1: 0,
        workingsStatus2: 0,
        workingsStatus0: 0,
    },
    importOnly: [],
    workingsOnly: [],
    premiumMismatch: [],
    batchMatchInfo: null,
});

const compareFocus = ref('mismatch');
const compareSearch = ref('');
const comparePage = ref(1);
const compareRowsPerPage = ref(20);

const compareFocusRows = computed(() => {
    if (compareFocus.value === 'importOnly') return compareState.value.importOnly || [];
    if (compareFocus.value === 'workingsOnly') return compareState.value.workingsOnly || [];
    return compareState.value.premiumMismatch || [];
});

const compareFilteredRows = computed(() => {
    const term = compareSearch.value.trim().toLowerCase();
    if (!term) return compareFocusRows.value;

    return compareFocusRows.value.filter((row) => {
        const haystack = [
            row.memberId,
            row.personelNumber,
            row.policyCode,
            row.importAmount,
            row.workingsAmount,
            row.premiumAmount,
        ]
            .map((value) => String(value ?? '').toLowerCase())
            .join(' ');
        return haystack.includes(term);
    });
});

const compareTotalPages = computed(() => {
    const total = compareFilteredRows.value.length;
    const size = compareRowsPerPage.value || 20;
    return total > 0 ? Math.ceil(total / size) : 1;
});

const comparePagedRows = computed(() => {
    const size = compareRowsPerPage.value || 20;
    const start = (comparePage.value - 1) * size;
    return compareFilteredRows.value.slice(start, start + size);
});

watch([compareFocus, compareSearch, compareRowsPerPage], () => {
    comparePage.value = 1;
});

const compareFocusTitle = computed(() => {
    if (compareFocus.value === 'importOnly') return 'Import Only';
    if (compareFocus.value === 'workingsOnly') return 'Workings Only';
    return 'Premium Mismatches';
});

const closeCompareModal = () => {
    compareState.value.show = false;
    compareSearch.value = '';
    comparePage.value = 1;
};

const getAnyValue = (obj, keys) => {
    for (const key of keys) {
        const value = obj?.[key];
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            return value;
        }
    }
    return null;
};

const getBatchRowsForMatching = (rows) => rows.map((row) => row?.raw || row);

const computeBatchMatchInfo = (upload, importRows) => {
    const batchRows = getBatchRowsForMatching(importRows);
    const batchPayload = props.premiumBatchData?.data || {};
    const payloadBatch = batchPayload?.policyBatch || batchPayload?.batch || {};
    const uploadUsernameCandidates = [
        normalizeText(upload?.user?.name),
        normalizeText(upload?.user?.email?.split('@')?.[0]),
    ].filter(Boolean).map((v) => v.toLowerCase());

    const rowUsers = batchRows
        .map((row) => normalizeText(getAnyValue(row, ['username', 'userName', 'createdBy', 'modifiedBy', 'uploadedBy'])))
        .filter(Boolean);
    const payloadUser = normalizeText(getAnyValue(payloadBatch, ['username', 'userName', 'createdBy', 'modifiedBy', 'uploadedBy']));
    if (payloadUser) {
        rowUsers.push(payloadUser);
    }
    const userMatch = rowUsers.find((user) =>
        uploadUsernameCandidates.some((candidate) => user.toLowerCase().includes(candidate))
    );

    const uploadFileName = normalizeText(upload?.systems_import_file_name).toLowerCase();
    const rowFiles = batchRows
        .map((row) => normalizeText(getAnyValue(row, ['fileName', 'sourceFileName', 'importFileName', 'batchFileName'])))
        .filter(Boolean);
    const payloadFile = normalizeText(getAnyValue(payloadBatch, ['fileName', 'sourceFileName', 'importFileName', 'batchFileName']));
    if (payloadFile) {
        rowFiles.push(payloadFile);
    }
    const fileMatch = rowFiles.find((file) => uploadFileName && file.toLowerCase().includes(uploadFileName));

    const uploadDate = upload?.system_import_date_formatted || upload?.submitted_at_formatted || null;
    const rowDates = batchRows
        .map((row) => getAnyValue(row, ['createdAt', 'modifiedAt', 'importedAt', 'uploadDate', 'created_at', 'modified_at']))
        .filter(Boolean);
    const payloadDate = getAnyValue(payloadBatch, ['createdAt', 'modifiedAt', 'importedAt', 'uploadDate', 'created_at', 'modified_at']);
    if (payloadDate) {
        rowDates.push(payloadDate);
    }

    let closestDate = null;
    let closestHoursDiff = null;
    if (uploadDate && rowDates.length) {
        const base = new Date(uploadDate);
        rowDates.forEach((candidate) => {
            const dt = new Date(candidate);
            if (Number.isNaN(dt.getTime()) || Number.isNaN(base.getTime())) return;
            const diffHours = Math.abs((dt.getTime() - base.getTime()) / (1000 * 60 * 60));
            if (closestHoursDiff === null || diffHours < closestHoursDiff) {
                closestHoursDiff = diffHours;
                closestDate = candidate;
            }
        });
    }

    return {
        rowCount: batchRows.length,
        matchedUsername: userMatch || null,
        matchedFileName: fileMatch || null,
        uploadDateTime: uploadDate,
        closestBatchDateTime: closestDate,
        closestHoursDiff: closestHoursDiff !== null ? Number(closestHoursDiff.toFixed(2)) : null,
    };
};

const exportCompareFocusCsv = () => {
    const rows = compareFilteredRows.value || [];
    if (!rows.length) return;

    const headers = compareFocus.value === 'mismatch'
        ? ['Member ID', 'Personnel No', 'Policy Code', 'Import Amount', 'Workings Amount']
        : ['Member ID', 'Personnel No', 'Policy Code', 'Premium'];

    const csvRows = rows.map((row) => {
        if (compareFocus.value === 'mismatch') {
            return [row.memberId, row.personelNumber, row.policyCode, row.importAmount, row.workingsAmount];
        }
        return [row.memberId, row.personelNumber, row.policyCode, row.premiumAmount];
    });

    const escapeValue = (value) => {
        const text = String(value ?? '');
        if (text.includes('"') || text.includes(',') || text.includes('\n')) {
            return `"${text.replace(/"/g, '""')}"`;
        }
        return text;
    };

    const csv = [headers, ...csvRows]
        .map((line) => line.map(escapeValue).join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `caps-compare-${compareFocus.value}-${compareState.value.uploadReference || 'upload'}.csv`;
    link.click();
    URL.revokeObjectURL(url);
};

const toHeaderKey = (header) => String(header || '').toLowerCase().replace(/[^a-z0-9]/g, '');

const findHeaderIndex = (headers, aliases) => {
    const normalizedAliases = aliases.map((alias) => alias.toLowerCase());
    return headers.findIndex((key) => normalizedAliases.includes(key));
};

const mapSpreadsheetRows = (spreadsheetData) => {
    const headers = (spreadsheetData.headers || []).map(toHeaderKey);
    const rows = spreadsheetData.rows || [];

    const memberIdx = findHeaderIndex(headers, ['memberid', 'idnumber', 'idno']);
    const personnelIdx = findHeaderIndex(headers, ['personelnumber', 'personnelnumber', 'paynumber', 'employeenumber', 'personpaynumber']);
    const policyIdx = findHeaderIndex(headers, ['policycode', 'policynumber', 'policyno']);
    const amountIdx = findHeaderIndex(headers, ['premiumamount', 'premium', 'amountpayable', 'amount']);
    const statusIdx = findHeaderIndex(headers, ['policystatus', 'status']);
    const companyIdx = findHeaderIndex(headers, ['companyname', 'company', 'deductioncompany', 'insurancecompany', 'provider']);

    return rows
        .map((row, idx) => ({
            key: makeRecordKey({
                memberId: row[memberIdx] ?? '',
                personelNumber: row[personnelIdx] ?? '',
                policyCode: row[policyIdx] ?? '',
            }),
            memberId: normalizeText(row[memberIdx]),
            personelNumber: normalizeText(row[personnelIdx]),
            policyCode: normalizeText(row[policyIdx]),
            premiumAmount: normalizeAmount(row[amountIdx]),
            policyStatus: normalizeText(row[statusIdx]),
            companyName: companyIdx >= 0 ? String(row[companyIdx] ?? '').trim() : '',
            index: idx + 2,
        }))
        .filter((record) => record.key !== '||');
};

const countPolicyStatus = (rows) => {
    const normalized = rows.map((row) => String(row.policyStatus ?? '').trim());
    return {
        status1: normalized.filter((status) => status === '1').length,
        status2: normalized.filter((status) => status === '2').length,
        status0: normalized.filter((status) => status === '0').length,
    };
};

const compareWorkingsAgainstImport = async (upload) => {
    compareSearch.value = '';
    comparePage.value = 1;
    compareRowsPerPage.value = 20;

    compareState.value = {
        ...compareState.value,
        show: true,
        loading: true,
        error: null,
        uploadReference: upload?.reference || '',
        counts: {
            importOnly: 0,
            workingsOnly: 0,
            premiumMismatch: 0,
            matched: 0,
            importRecords: 0,
            workingsRecords: 0,
            importStatus1: 0,
            importStatus2: 0,
            importStatus0: 0,
            workingsStatus1: 0,
            workingsStatus2: 0,
            workingsStatus0: 0,
        },
        importOnly: [],
        workingsOnly: [],
        premiumMismatch: [],
        batchMatchInfo: null,
    };
    compareFocus.value = 'mismatch';

    try {
        if (!upload?.workings_file_url || !upload?.workings_file_name) {
            throw new Error('This upload has no workings file to compare.');
        }

        let importRows = [];
        if (upload?.systems_import_file_url && upload?.systems_import_file_name) {
            const importData = await loadSpreadsheetData(upload.systems_import_file_url, upload.systems_import_file_name);
            if (importData.error) {
                throw new Error(importData.error);
            }
            importRows = mapSpreadsheetRows(importData);
        } else if (mappedPremiumRows.value.length) {
            importRows = mappedPremiumRows.value.map((row) => ({
                ...row,
                policyStatus: normalizeText(row.raw?.policyStatus ?? row.raw?.status),
            }));
        } else {
            throw new Error('No systems import file or premium batch records available for comparison.');
        }

        const workingsData = await loadSpreadsheetData(upload.workings_file_url, upload.workings_file_name);
        if (workingsData.error) {
            throw new Error(workingsData.error);
        }

        const workingsRows = mapSpreadsheetRows(workingsData);
        const importMap = new Map(importRows.map((row) => [row.key, row]));
        const workingsMap = new Map(workingsRows.map((row) => [row.key, row]));
        const importStatusCounts = countPolicyStatus(importRows);
        const workingsStatusCounts = countPolicyStatus(workingsRows);

        const importOnly = [];
        const workingsOnly = [];
        const premiumMismatch = [];
        let matched = 0;

        for (const [key, importRow] of importMap.entries()) {
            const workingsRow = workingsMap.get(key);
            if (!workingsRow) {
                importOnly.push(importRow);
                continue;
            }

            const importAmount = importRow.premiumAmount;
            const workingsAmount = workingsRow.premiumAmount;
            if (importAmount !== null && workingsAmount !== null && importAmount !== workingsAmount) {
                premiumMismatch.push({
                    key,
                    memberId: importRow.memberId || workingsRow.memberId,
                    personelNumber: importRow.personelNumber || workingsRow.personelNumber,
                    policyCode: importRow.policyCode || workingsRow.policyCode,
                    importAmount,
                    workingsAmount,
                });
            } else {
                matched++;
            }
        }

        for (const [key, workingsRow] of workingsMap.entries()) {
            if (!importMap.has(key)) {
                workingsOnly.push(workingsRow);
            }
        }

        compareState.value = {
            ...compareState.value,
            loading: false,
            counts: {
                importOnly: importOnly.length,
                workingsOnly: workingsOnly.length,
                premiumMismatch: premiumMismatch.length,
                matched,
                importRecords: importRows.length,
                workingsRecords: workingsRows.length,
                importStatus1: importStatusCounts.status1,
                importStatus2: importStatusCounts.status2,
                importStatus0: importStatusCounts.status0,
                workingsStatus1: workingsStatusCounts.status1,
                workingsStatus2: workingsStatusCounts.status2,
                workingsStatus0: workingsStatusCounts.status0,
            },
            importOnly: importOnly.slice(0, 200),
            workingsOnly: workingsOnly.slice(0, 200),
            premiumMismatch: premiumMismatch.slice(0, 200),
            batchMatchInfo: computeBatchMatchInfo(upload, importRows),
        };
    } catch (error) {
        compareState.value = {
            ...compareState.value,
            loading: false,
            error: error?.message || 'Failed to compare records.',
        };
    }
};

/* ===================== CAPS Member/Policy Verification ===================== */
const capsVerifyState = ref({
    show: false,
    loading: false,
    error: null,
    uploadReference: '',
    companyName: '',
    results: null,
});

const capsVerifyFocus = ref(null);

const capsVerifyCards = [
    { key: 'member_found', label: 'Verified', badgeClass: 'bg-teal-100 text-teal-700', iconClass: 'text-green-500', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'policy_found', label: 'Policies OK', badgeClass: 'bg-blue-100 text-blue-700', iconClass: 'text-blue-500', icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'member_not_found', label: 'Members Missing', badgeClass: 'bg-red-100 text-red-700', iconClass: 'text-orange-500', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'policy_not_found', label: 'Policies Missing', badgeClass: 'bg-red-100 text-red-700', iconClass: 'text-red-500', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'premium_mismatch', label: 'Mismatch', badgeClass: 'bg-amber-100 text-amber-700', iconClass: 'text-amber-500', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
];

const capsVerifyCurrentRows = computed(() => {
    const results = capsVerifyState.value.results;
    if (!results) return [];
    if (!capsVerifyFocus.value) {
        // Show all rows combined with status
        const all = [];
        for (const card of capsVerifyCards) {
            for (const row of (results[card.key] || [])) {
                all.push({ ...row, _status: card.label, _badgeClass: card.badgeClass });
            }
        }
        // Deduplicate by policyCode + memberId (keep first occurrence = highest priority status)
        const seen = new Set();
        return all.filter(row => {
            const key = `${row.memberId || ''}|${row.personelNumber || ''}|${row.policyCode || ''}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        }).slice(0, 500);
    }
    return (results[capsVerifyFocus.value] || []).map(row => ({
        ...row,
        _status: capsVerifyCards.find(c => c.key === capsVerifyFocus.value)?.label || '',
        _badgeClass: capsVerifyCards.find(c => c.key === capsVerifyFocus.value)?.badgeClass || '',
    })).slice(0, 500);
});

const capsVerifyTotalPremium = computed(() => {
    const results = capsVerifyState.value.results;
    if (!results) return 0;
    const allRows = [
        ...(results.member_found || []),
        ...(results.member_not_found || []),
    ];
    const seen = new Set();
    let total = 0;
    for (const row of allRows) {
        const key = `${row.memberId || ''}|${row.policyCode || ''}`;
        if (seen.has(key)) continue;
        seen.add(key);
        total += Number(row.premiumAmount || 0);
    }
    return total;
});

const compareWithCapsMembers = async (upload) => {
    capsVerifyState.value = {
        show: true,
        loading: true,
        error: null,
        uploadReference: upload?.reference || '',
        companyName: upload?.company?.name || '',
        results: null,
    };

    try {
        // Load the spreadsheet data (prefer systems import, fall back to workings)
        let spreadsheet;
        if (upload?.systems_import_file_url && upload?.systems_import_file_name) {
            spreadsheet = await loadSpreadsheetData(upload.systems_import_file_url, upload.systems_import_file_name);
        } else if (upload?.workings_file_url && upload?.workings_file_name) {
            spreadsheet = await loadSpreadsheetData(upload.workings_file_url, upload.workings_file_name);
        } else {
            throw new Error('No spreadsheet file available for verification.');
        }

        if (spreadsheet.error) {
            throw new Error(spreadsheet.error);
        }

        const rows = mapSpreadsheetRows(spreadsheet);
        if (rows.length === 0) {
            throw new Error('No parseable rows found in the spreadsheet.');
        }

        // Send rows to the backend for CAPS comparison.
        // Get CSRF token from: cookie (XSRF-TOKEN) > Inertia props > meta tag
        const xsrfCookie = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
        const csrfToken = xsrfCookie
            ? decodeURIComponent(xsrfCookie.split('=')[1])
            : (page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

        const resp = await fetch(`/uploads/${upload.id}/compare-caps`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                rows: rows.map(r => ({
                    memberId: r.memberId || '',
                    personelNumber: r.personelNumber || '',
                    policyCode: r.policyCode || '',
                    premiumAmount: r.premiumAmount,
                    companyName: r.companyName || '',
                })),
            }),
        });

        if (!resp.ok) {
            throw new Error(`Server returned HTTP ${resp.status}`);
        }

        const json = await resp.json();

        if (!json.ok) {
            throw new Error(json.message || 'CAPS comparison failed.');
        }

        capsVerifyState.value = {
            ...capsVerifyState.value,
            loading: false,
            companyName: json.company_name || upload?.company?.name || '',
            results: json.results,
        };

        // Default: show all rows (no filter)
        capsVerifyFocus.value = null;

    } catch (error) {
        capsVerifyState.value = {
            ...capsVerifyState.value,
            loading: false,
            error: error?.message || 'Failed to verify against CAPS.',
        };
    }
};

const showPrecomputedVerification = (upload) => {
    const results = upload.caps_verification;
    if (!results) return;

    capsVerifyState.value = {
        show: true,
        loading: false,
        error: null,
        uploadReference: upload.reference || '',
        companyName: upload.company?.name || '',
        results,
    };

    // Default: show all rows
    capsVerifyFocus.value = null;
};

const closeCapsVerify = () => {
    capsVerifyState.value = { show: false, loading: false, error: null, uploadReference: '', companyName: '', results: null };
};
</script>

<template>
    <AppLayout>
        <div class="px-6 py-6">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold">Uploads History</h2>
                <Link href="/uploads" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                    View Recent →
                </Link>
            </div>

            <!-- Filters -->
            <div class="mt-6 rounded-xl border bg-white p-4 shadow sm:p-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                        <select
                            v-model="form.status"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="s in statusOptions || []" :key="s" :value="s">
                                {{ s }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Search</label>
                        <input
                            v-model="form.search"
                            placeholder="Reference, company, municipality, or user..."
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Show</label>
                        <select
                            v-model="form.per_page"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        >
                            <option v-for="option in perPageOptions || [20, 50, 100, 200, 500]"
                                    :key="option"
                                    :value="option">
                                {{ option }} records
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2 md:col-span-2">
                        <button
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                        <button
                            class="flex-1 rounded-lg bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                        <button
                            class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                            @click="exportData"
                        >
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="!uploads?.data?.length"
                class="mt-6 rounded-xl border bg-white p-8 text-center text-slate-600 shadow"
            >
                No uploads found matching your criteria.
            </div>

            <!-- Cards -->
            <div v-if="uploads?.data?.length" class="mt-6 space-y-3">
                <div
                    v-for="g in grouped"
                    :key="g.companyId"
                    class="rounded-xl border bg-white shadow"
                >
                    <!-- Card header -->
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-base font-semibold">
                                    {{ g.company?.name || '—' }}
                                </h3>
                                <span
                                    :class="statusPill(g.latest.status)"
                                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ g.latest.status }}
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                <span class="truncate">
                                    <span class="font-medium">Municipality:</span>
                                    {{ g.municipality?.name || '—' }}
                                </span>
                                <span class="hidden sm:inline">•</span>
                                <span class="truncate">
                                    <span class="font-medium">Latest Ref:</span>
                                    <span class="font-mono">{{ g.latest.reference }}</span>
                                </span>
                                <span class="hidden sm:inline">•</span>
                                <span>
                                    <span class="font-medium">Submitted:</span>
                                    {{ formatDate(g.latest.submitted_at) }}
                                </span>
                                <template v-if="g.latest.user">
                                    <span class="hidden sm:inline">•</span>
                                    <div class="flex items-center gap-1">
                                        <div class="w-4 h-4 rounded-full bg-blue-100 flex items-center justify-center text-[10px]">
                                            {{ getUserAvatar(g.latest.user).initials }}
                                        </div>
                                        <span class="text-xs text-slate-600">{{ g.latest.user.name }}</span>
                                    </div>
                                </template>
                                <template v-if="getReuploadReason(g.latest)">
                                    <span class="hidden sm:inline">•</span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">
                                        Reason: {{ getReuploadReason(g.latest) }}
                                    </span>
                                </template>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    @click="openAllFilesPreview(g.latest)"
                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] text-blue-700 hover:bg-blue-200 flex items-center gap-1"
                                >
                                    <span>All Files: {{ totalFiles(g.latest) }}</span>
                                    <span class="text-xs">📁</span>
                                </button>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700">
                                    Emails: {{ emailCount(g.latest) }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px]"
                                    :class="hasSystems(g.latest)
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-slate-100 text-slate-700'"
                                >
                                    System Import: {{ hasSystems(g.latest) ? 'Yes' : 'No' }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px]"
                                    :class="hasWorkings(g.latest)
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-slate-100 text-slate-700'"
                                >
                                    Workings: {{ hasWorkings(g.latest) ? 'Yes' : 'No' }}
                                </span>
                                <button
                                    v-if="hasWorkings(g.latest)"
                                    @click="compareWorkingsAgainstImport(g.latest)"
                                    class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] text-indigo-700 hover:bg-indigo-200"
                                >
                                    Compare CAPS vs Import
                                </button>
                                <button
                                    v-if="g.latest?.caps_verification"
                                    @click="showPrecomputedVerification(g.latest)"
                                    class="rounded-full bg-teal-100 px-2 py-0.5 text-[11px] text-teal-700 hover:bg-teal-200 flex items-center gap-1"
                                >
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                    Verification Results
                                </button>
                                <button
                                    v-else-if="g.latest?.systems_import_file_url || hasWorkings(g.latest)"
                                    @click="compareWithCapsMembers(g.latest)"
                                    class="rounded-full bg-teal-100 px-2 py-0.5 text-[11px] text-teal-700 hover:bg-teal-200"
                                >
                                    Verify Members &amp; Policies
                                </button>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 self-start sm:self-auto">
                            <button
                                class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                @click="toggle(g.companyId)"
                                :aria-expanded="isOpen(g.companyId)"
                            >
                                <span>{{ isOpen(g.companyId) ? 'Hide History' : 'Show History' }}</span>
                                <span>{{ isOpen(g.companyId) ? '▴' : '▾' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Expanded history -->
                    <transition name="fade" mode="out-in">
                        <div v-if="isOpen(g.companyId)" class="border-t bg-slate-50/60 p-3 sm:p-4">
                            <div class="mb-2 text-xs text-slate-500">History (newest → oldest)</div>

                            <!-- Compact table for history -->
                            <div class="overflow-x-auto rounded-lg border bg-white">
                                <table class="w-full table-fixed text-xs">
                                    <colgroup>
                                        <col class="w-32" />
                                        <col class="w-24" />
                                        <col class="w-32" />
                                        <col class="w-48" />
                                        <col class="w-28" />
                                        <col class="w-28" />
                                        <col class="w-20" />
                                    </colgroup>
                                    <thead class="bg-slate-100">
                                    <tr class="text-left">
                                        <th class="px-3 py-2">Reference</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Uploaded By</th>
                                        <th class="px-3 py-2">Files / Reason</th>
                                        <th class="px-3 py-2">Import</th>
                                        <th class="px-3 py-2">Submitted</th>
                                        <th class="px-3 py-2">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                    <tr v-for="h in g.history" :key="h.id">
                                        <td class="px-3 py-2 font-mono whitespace-nowrap">
                                            {{ h.reference }}
                                        </td>
                                        <td class="px-3 py-2">
                                                <span
                                                    :class="statusPill(h.status)"
                                                    class="inline-block rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                >
                                                    {{ h.status }}
                                                </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div v-if="h.user" class="flex items-center gap-2">
                                                <div class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center text-[9px]">
                                                    {{ getUserAvatar(h.user).initials }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="truncate text-[10px] font-medium">{{ h.user.name }}</div>
                                                    <div class="text-[9px] text-slate-500 truncate">
                                                        {{ formatTimeAgo(h.submitted_at_formatted) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <span v-else class="text-gray-400 text-xs">—</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700">
                                                        Emails: {{ emailCount(h) }}
                                                    </span>
                                                <span
                                                    v-if="hasSystems(h)"
                                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] text-emerald-700"
                                                >
                                                        System
                                                    </span>
                                                <span
                                                    v-if="hasWorkings(h)"
                                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] text-emerald-700"
                                                >
                                                        Workings
                                                    </span>
                                            </div>
                                            <div v-if="getReuploadReason(h)" class="mt-1 break-words whitespace-normal">
                                                    <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">
                                                        {{ getReuploadReason(h) }}
                                                    </span>
                                            </div>

                                            <!-- Quick list of email filenames -->
                                            <div v-if="h.original_file_names?.length" class="mt-1 flex flex-wrap gap-2">
                                                <template v-for="(name, i) in h.original_file_names.slice(0, 2)" :key="i">
                                                    <a
                                                        class="max-w-[12rem] cursor-pointer truncate text-blue-600 hover:text-blue-800 hover:underline"
                                                        @click.prevent="openPreview(h.original_file_urls?.[i], name, h.preview_urls?.[i], h.email_preview_data_urls?.[i])"
                                                    >
                                                        {{ name }}
                                                    </a>
                                                </template>
                                                <span v-if="h.original_file_names.length > 2" class="text-slate-500">
                                                        +{{ h.original_file_names.length - 2 }} more
                                                    </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            {{ formatDate(h.system_import_date) }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="text-[10px]">{{ formatDateTime(h.submitted_at_formatted) }}</div>
                                            <div class="text-[9px] text-slate-500">
                                                {{ formatTimeAgo(h.submitted_at_formatted) }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <button
                                                @click="openAllFilesPreview(h)"
                                                class="rounded-lg bg-blue-100 px-2 py-1 text-[10px] text-blue-700 hover:bg-blue-200 flex items-center gap-1"
                                                :disabled="totalFiles(h) === 0"
                                                :class="{ 'opacity-50 cursor-not-allowed': totalFiles(h) === 0 }"
                                            >
                                                <span>📁</span>
                                                <span>{{ totalFiles(h) }}</span>
                                            </button>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>

            <!-- ==================== CAPS Verification Modal (Batch-style) ==================== -->
            <div
                v-if="capsVerifyState.show"
                class="fixed inset-0 z-[95] flex items-center justify-center bg-black/50 p-4"
                @click.self="closeCapsVerify"
            >
                <div class="relative flex max-h-[94vh] w-full max-w-7xl flex-col rounded-xl bg-gray-50 shadow-2xl overflow-hidden">

                    <!-- Header -->
                    <div class="bg-white px-6 py-5 border-b">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-50">
                                    <svg class="h-6 w-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-lg font-bold text-gray-900">Upload #{{ capsVerifyState.uploadReference }}</h3>
                                        <span v-if="capsVerifyState.results" class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Pending</span>
                                    </div>
                                    <p v-if="capsVerifyState.results" class="text-sm text-gray-500 mt-0.5">
                                        {{ capsVerifyState.results.uploaded_rows_total }} total records
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="closeCapsVerify" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Metadata row -->
                    <div v-if="capsVerifyState.results" class="bg-white border-b px-6 py-3 flex flex-wrap items-center gap-x-8 gap-y-1 text-sm text-gray-600">
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            Company <strong class="text-gray-900 ml-1">{{ capsVerifyState.companyName || '—' }}</strong>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            Members checked <strong class="text-gray-900 ml-1">{{ capsVerifyState.results.caps_members_total }}</strong>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            Policies checked <strong class="text-gray-900 ml-1">{{ capsVerifyState.results.caps_policies_total }}</strong>
                        </span>
                    </div>

                    <!-- Loading -->
                    <div v-if="capsVerifyState.loading" class="flex flex-col items-center justify-center py-20 bg-white">
                        <div class="h-14 w-14 animate-spin rounded-full border-4 border-teal-100 border-t-teal-600"></div>
                        <p class="mt-4 text-sm font-medium text-gray-600">Verifying against CAPS...</p>
                        <p class="mt-1 text-xs text-gray-400">Checking members and policies</p>
                    </div>

                    <!-- Error -->
                    <div v-else-if="capsVerifyState.error" class="p-8 bg-white">
                        <div class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
                            <svg class="mx-auto h-10 w-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="mt-3 text-sm font-medium text-red-800">Verification Failed</p>
                            <p class="mt-1 text-sm text-red-600">{{ capsVerifyState.error }}</p>
                        </div>
                    </div>

                    <!-- Results -->
                    <div v-else-if="capsVerifyState.results" class="flex flex-col overflow-hidden">

                        <!-- Summary cards -->
                        <div class="grid grid-cols-5 gap-4 px-6 py-5">
                            <button
                                v-for="card in capsVerifyCards"
                                :key="card.key"
                                @click="capsVerifyFocus = capsVerifyFocus === card.key ? null : card.key"
                                :class="[
                                    'relative flex flex-col items-center rounded-lg border py-4 px-3 transition-all cursor-pointer',
                                    capsVerifyFocus === card.key
                                        ? 'border-teal-400 bg-white shadow-md ring-1 ring-teal-400'
                                        : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm',
                                ]"
                            >
                                <svg class="h-5 w-5 mb-2" :class="card.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.icon" />
                                </svg>
                                <span class="text-2xl font-bold text-gray-900">
                                    {{ (capsVerifyState.results[card.key] || []).length }}
                                </span>
                                <span class="text-xs font-medium text-gray-500 mt-0.5">{{ card.label }}</span>
                            </button>
                        </div>

                        <!-- CAPS data warnings -->
                        <div v-if="capsVerifyState.results.caps_members_error || capsVerifyState.results.caps_policies_error" class="px-6 pb-3 space-y-2">
                            <div v-if="capsVerifyState.results.caps_members_error" class="flex items-center gap-2 rounded-lg bg-yellow-50 border border-yellow-200 px-3 py-2 text-xs text-yellow-800">
                                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                Members: {{ capsVerifyState.results.caps_members_error }}
                            </div>
                            <div v-if="capsVerifyState.results.caps_policies_error" class="flex items-center gap-2 rounded-lg bg-yellow-50 border border-yellow-200 px-3 py-2 text-xs text-yellow-800">
                                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                Policies: {{ capsVerifyState.results.caps_policies_error }}
                            </div>
                        </div>

                        <!-- Table area -->
                        <div class="flex-1 overflow-hidden bg-white border-t mx-6 mb-4 rounded-lg border">
                            <!-- Table header with total premium -->
                            <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50/50">
                                <span class="text-sm text-gray-500">
                                    {{ capsVerifyFocus ? capsVerifyCards.find(c => c.key === capsVerifyFocus)?.label : 'All Records' }}
                                    &mdash; {{ capsVerifyCurrentRows.length }} records
                                </span>
                                <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-3 py-1 text-sm font-semibold text-gray-800">
                                    Total Premium: R {{ capsVerifyTotalPremium.toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>

                            <div class="overflow-auto" style="max-height: 46vh">
                                <table v-if="capsVerifyCurrentRows.length" class="w-full text-sm">
                                    <thead class="sticky top-0 bg-gray-50 z-10">
                                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            <th class="py-3 pl-4 pr-3">Employee No</th>
                                            <th class="py-3 pr-3">ID Number</th>
                                            <th class="py-3 pr-3">Policy Code</th>
                                            <th class="py-3 pr-3">Status</th>
                                            <th class="py-3 pr-3">Company</th>
                                            <th class="py-3 pr-3 text-right">Amount Payable</th>
                                            <th class="py-3 pr-4 text-right">Premium</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr
                                            v-for="(row, idx) in capsVerifyCurrentRows"
                                            :key="idx"
                                            class="hover:bg-gray-50/70 transition-colors"
                                        >
                                            <td class="py-2.5 pl-4 pr-3 text-sm text-gray-600">{{ row.personelNumber || '—' }}</td>
                                            <td class="py-2.5 pr-3 font-mono text-sm text-gray-900 font-medium">{{ row.memberId || '—' }}</td>
                                            <td class="py-2.5 pr-3 font-mono text-sm font-bold text-gray-900">{{ row.policyCode || '—' }}</td>
                                            <td class="py-2.5 pr-3">
                                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', row._badgeClass]">
                                                    {{ row._status }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 pr-3 text-sm text-gray-600">{{ row.companyName || '—' }}</td>
                                            <td class="py-2.5 pr-3 text-right text-sm text-gray-500">
                                                {{ row.caps_premium != null ? `R ${Number(row.caps_premium).toLocaleString('en-ZA', { minimumFractionDigits: 2 })}` : 'R 0,00' }}
                                            </td>
                                            <td class="py-2.5 pr-4 text-right text-sm font-semibold text-teal-600">
                                                R {{ Number(row.premiumAmount || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 mb-3">
                                        <svg class="h-7 w-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-700">All clear</p>
                                    <p class="text-xs text-gray-400 mt-1">No records in this category</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="compareState.show"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="closeCompareModal"
            >
                <div class="caps-compare-modal max-h-[90vh] w-full max-w-7xl overflow-auto rounded-xl bg-white shadow-2xl">
                    <div class="caps-compare-header sticky top-0 flex items-center justify-between border-b bg-white px-6 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">CAPS Comparison</h3>
                            <p class="text-sm text-slate-600">
                                Upload: <span class="font-mono">{{ compareState.uploadReference }}</span>
                            </p>
                        </div>
                        <button
                            class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50"
                            @click="closeCompareModal"
                        >
                            Close
                        </button>
                    </div>

                    <div class="space-y-4 p-6">
                        <div v-if="compareState.loading" class="text-sm text-slate-700">Comparing records...</div>
                        <p v-else-if="compareState.error" class="text-sm text-red-700">{{ compareState.error }}</p>
                        <template v-else>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                <div class="rounded-lg border bg-blue-50 p-3">
                                    <div class="text-xs text-blue-700">Import Records</div>
                                    <div class="text-lg font-semibold text-blue-800">{{ compareState.counts.importRecords }}</div>
                                </div>
                                <div class="rounded-lg border bg-cyan-50 p-3">
                                    <div class="text-xs text-cyan-700">Workings Records</div>
                                    <div class="text-lg font-semibold text-cyan-800">{{ compareState.counts.workingsRecords }}</div>
                                </div>
                                <div class="rounded-lg border bg-slate-50 p-3">
                                    <div class="text-xs text-slate-500">Matched</div>
                                    <div class="text-lg font-semibold text-slate-900">{{ compareState.counts.matched }}</div>
                                </div>
                                <div class="rounded-lg border bg-red-50 p-3">
                                    <div class="text-xs text-red-700">Import Only</div>
                                    <div class="text-lg font-semibold text-red-800">{{ compareState.counts.importOnly }}</div>
                                </div>
                                <div class="rounded-lg border bg-amber-50 p-3">
                                    <div class="text-xs text-amber-700">Workings Only</div>
                                    <div class="text-lg font-semibold text-amber-800">{{ compareState.counts.workingsOnly }}</div>
                                </div>
                                <div class="rounded-lg border bg-indigo-50 p-3">
                                    <div class="text-xs text-indigo-700">Premium Mismatches</div>
                                    <div class="text-lg font-semibold text-indigo-800">{{ compareState.counts.premiumMismatch }}</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                                <div class="rounded-lg border bg-emerald-50 p-3">
                                    <div class="text-xs text-emerald-700">Import 1 (New/Update)</div>
                                    <div class="text-lg font-semibold text-emerald-800">{{ compareState.counts.importStatus1 }}</div>
                                </div>
                                <div class="rounded-lg border bg-amber-50 p-3">
                                    <div class="text-xs text-amber-700">Import 2 (Reinstate)</div>
                                    <div class="text-lg font-semibold text-amber-800">{{ compareState.counts.importStatus2 }}</div>
                                </div>
                                <div class="rounded-lg border bg-rose-50 p-3">
                                    <div class="text-xs text-rose-700">Import 0 (Cancel)</div>
                                    <div class="text-lg font-semibold text-rose-800">{{ compareState.counts.importStatus0 }}</div>
                                </div>
                                <div class="rounded-lg border bg-emerald-50 p-3">
                                    <div class="text-xs text-emerald-700">Workings 1</div>
                                    <div class="text-lg font-semibold text-emerald-800">{{ compareState.counts.workingsStatus1 }}</div>
                                </div>
                                <div class="rounded-lg border bg-amber-50 p-3">
                                    <div class="text-xs text-amber-700">Workings 2</div>
                                    <div class="text-lg font-semibold text-amber-800">{{ compareState.counts.workingsStatus2 }}</div>
                                </div>
                                <div class="rounded-lg border bg-rose-50 p-3">
                                    <div class="text-xs text-rose-700">Workings 0</div>
                                    <div class="text-lg font-semibold text-rose-800">{{ compareState.counts.workingsStatus0 }}</div>
                                </div>
                            </div>

                            <div class="rounded-lg border bg-white">
                                <div class="border-b bg-slate-50 px-3 py-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                @click="compareFocus = 'mismatch'"
                                                :class="compareFocus === 'mismatch' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300'"
                                                class="rounded-md border px-3 py-1 text-xs font-medium"
                                            >
                                                Premium Mismatch ({{ compareState.counts.premiumMismatch }})
                                            </button>
                                            <button
                                                @click="compareFocus = 'importOnly'"
                                                :class="compareFocus === 'importOnly' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-slate-700 border-slate-300'"
                                                class="rounded-md border px-3 py-1 text-xs font-medium"
                                            >
                                                Import Only ({{ compareState.counts.importOnly }})
                                            </button>
                                            <button
                                                @click="compareFocus = 'workingsOnly'"
                                                :class="compareFocus === 'workingsOnly' ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-slate-700 border-slate-300'"
                                                class="rounded-md border px-3 py-1 text-xs font-medium"
                                            >
                                                Workings Only ({{ compareState.counts.workingsOnly }})
                                            </button>
                                        </div>
                                        <button
                                            @click="exportCompareFocusCsv"
                                            :disabled="!compareFilteredRows.length"
                                            class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Export CSV
                                        </button>
                                    </div>
                                </div>
                                <div class="border-b bg-white px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input
                                            v-model="compareSearch"
                                            type="text"
                                            placeholder="Search member ID, personnel no, policy code..."
                                            class="min-w-[16rem] flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                        />
                                        <select
                                            v-model="compareRowsPerPage"
                                            class="rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                        >
                                            <option :value="20">20 rows</option>
                                            <option :value="50">50 rows</option>
                                            <option :value="100">100 rows</option>
                                        </select>
                                        <span class="text-xs text-slate-500">
                                            {{ compareFilteredRows.length }} result{{ compareFilteredRows.length !== 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="compareFilteredRows.length" class="overflow-x-auto caps-focus-table-wrap">
                                    <table class="w-full table-auto text-xs caps-focus-table">
                                        <thead class="bg-slate-100">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Member ID</th>
                                            <th class="px-3 py-2 text-left">Personnel No</th>
                                            <th class="px-3 py-2 text-left">Policy Code</th>
                                            <th v-if="compareFocus === 'mismatch'" class="px-3 py-2 text-left">Import Amount</th>
                                            <th v-if="compareFocus === 'mismatch'" class="px-3 py-2 text-left">Workings Amount</th>
                                            <th v-else class="px-3 py-2 text-left">Premium</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(row, idx) in comparePagedRows" :key="`${row.key}-${idx}`" class="border-t">
                                            <td class="px-3 py-2 font-mono">{{ row.memberId || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.personelNumber || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.policyCode || '—' }}</td>
                                            <td v-if="compareFocus === 'mismatch'" class="px-3 py-2">{{ row.importAmount ?? '—' }}</td>
                                            <td v-if="compareFocus === 'mismatch'" class="px-3 py-2">{{ row.workingsAmount ?? '—' }}</td>
                                            <td v-else class="px-3 py-2">{{ row.premiumAmount ?? '—' }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="px-4 py-6 text-sm text-slate-600">
                                    No rows found for {{ compareFocusTitle.toLowerCase() }}.
                                </div>
                                <div v-if="compareFilteredRows.length > compareRowsPerPage" class="flex items-center justify-between border-t bg-slate-50 px-3 py-2">
                                    <span class="text-xs text-slate-600">
                                        Page {{ comparePage }} of {{ compareTotalPages }}
                                    </span>
                                    <div class="flex gap-2">
                                        <button
                                            @click="comparePage = Math.max(1, comparePage - 1)"
                                            :disabled="comparePage <= 1"
                                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            Previous
                                        </button>
                                        <button
                                            @click="comparePage = Math.min(compareTotalPages, comparePage + 1)"
                                            :disabled="comparePage >= compareTotalPages"
                                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="compareState.batchMatchInfo" class="rounded-lg border bg-slate-50 p-3 text-xs text-slate-700">
                                <div class="mb-2 font-semibold text-slate-800">Batch Match Summary</div>
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                                    <div>
                                        <div class="text-slate-500">Upload Date/Time</div>
                                        <div>{{ compareState.batchMatchInfo.uploadDateTime || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Closest Batch Date/Time</div>
                                        <div>{{ compareState.batchMatchInfo.closestBatchDateTime || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Time Difference (hours)</div>
                                        <div>{{ compareState.batchMatchInfo.closestHoursDiff ?? '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Matched Username</div>
                                        <div>{{ compareState.batchMatchInfo.matchedUsername || 'No direct match' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Matched Import Filename</div>
                                        <div class="break-all">{{ compareState.batchMatchInfo.matchedFileName || 'No direct match' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Batch Records Considered</div>
                                        <div>{{ compareState.batchMatchInfo.rowCount }}</div>
                                    </div>
                                </div>
                            </div>

                            <details class="rounded-lg border bg-white">
                                <summary class="cursor-pointer border-b bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700">
                                    Advanced Split Tables
                                </summary>

                            <div v-if="compareState.premiumMismatch.length" class="rounded-lg border">
                                <div class="border-b bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700">Premium Mismatches (top 200)</div>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-auto text-xs">
                                        <thead class="bg-slate-100">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Member ID</th>
                                            <th class="px-3 py-2 text-left">Personnel No</th>
                                            <th class="px-3 py-2 text-left">Policy Code</th>
                                            <th class="px-3 py-2 text-left">Import Amount</th>
                                            <th class="px-3 py-2 text-left">Workings Amount</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(row, idx) in compareState.premiumMismatch" :key="`${row.key}-${idx}`" class="border-t">
                                            <td class="px-3 py-2 font-mono">{{ row.memberId || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.personelNumber || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.policyCode || '—' }}</td>
                                            <td class="px-3 py-2">{{ row.importAmount ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ row.workingsAmount ?? '—' }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div v-if="compareState.importOnly.length" class="rounded-lg border">
                                <div class="border-b bg-red-50 px-3 py-2 text-xs font-medium text-red-700">Import Only (top 200)</div>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-auto text-xs">
                                        <thead class="bg-slate-100">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Member ID</th>
                                            <th class="px-3 py-2 text-left">Personnel No</th>
                                            <th class="px-3 py-2 text-left">Policy Code</th>
                                            <th class="px-3 py-2 text-left">Premium</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(row, idx) in compareState.importOnly" :key="`${row.key}-${idx}`" class="border-t">
                                            <td class="px-3 py-2 font-mono">{{ row.memberId || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.personelNumber || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.policyCode || '—' }}</td>
                                            <td class="px-3 py-2">{{ row.premiumAmount ?? '—' }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div v-if="compareState.workingsOnly.length" class="rounded-lg border">
                                <div class="border-b bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">Workings Only (top 200)</div>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-auto text-xs">
                                        <thead class="bg-slate-100">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Member ID</th>
                                            <th class="px-3 py-2 text-left">Personnel No</th>
                                            <th class="px-3 py-2 text-left">Policy Code</th>
                                            <th class="px-3 py-2 text-left">Premium</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(row, idx) in compareState.workingsOnly" :key="`${row.key}-${idx}`" class="border-t">
                                            <td class="px-3 py-2 font-mono">{{ row.memberId || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.personelNumber || '—' }}</td>
                                            <td class="px-3 py-2 font-mono">{{ row.policyCode || '—' }}</td>
                                            <td class="px-3 py-2">{{ row.premiumAmount ?? '—' }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            </details>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="uploads?.links?.length > 3"
                 class="mt-6 flex flex-col items-center justify-between gap-4 rounded-xl border bg-white px-4 py-3 shadow sm:flex-row"
            >
                <div class="text-sm text-slate-600">
                    Showing {{ uploads.from }} to {{ uploads.to }} of {{ uploads.total }} results
                    <span class="ml-2 text-xs text-slate-500">
                        ({{ currentPerPage }} per page)
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-sm text-slate-600">
                        Page {{ uploads.current_page }} of {{ uploads.last_page }}
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="uploads.prev_page_url"
                            :href="uploads.prev_page_url"
                            class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            Previous
                        </Link>
                        <Link
                            v-for="page in uploads.links.slice(1, -1)"
                            :key="page.label"
                            :href="page.url"
                            class="rounded-lg border px-3 py-1 text-sm"
                            :class="page.active ? 'bg-blue-600 text-white border-blue-600' : 'text-slate-700 hover:bg-slate-50'"
                        >
                            <span v-html="page.label" />
                        </Link>
                        <Link
                            v-if="uploads.next_page_url"
                            :href="uploads.next_page_url"
                            class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Preview Modal (Single File) -->
            <div v-if="preview.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 backdrop-blur-sm p-4">
                <div class="max-h-[90vh] w-full max-w-6xl overflow-hidden rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b bg-slate-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-900">{{ preview.title }}</h3>
                        <button
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
                            @click="closePreview"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="max-h-[calc(90vh-140px)] overflow-auto p-6">
                        <div v-if="preview.loading" class="flex items-center justify-center p-8">
                            <div class="text-center">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                <p class="text-slate-600">Loading preview...</p>
                            </div>
                        </div>

                        <!-- Email Preview -->
                        <div v-else-if="preview.type === 'email'">
                            <div class="mb-4 rounded-lg border bg-white p-4 space-y-4">
                                <div class="grid gap-3 text-sm sm:grid-cols-2">
                                    <div><span class="font-semibold text-slate-700">From:</span> {{ preview.emailData?.from || '—' }}</div>
                                    <div><span class="font-semibold text-slate-700">To:</span> {{ preview.emailData?.to || '—' }}</div>
                                    <div><span class="font-semibold text-slate-700">Subject:</span> {{ preview.emailData?.subject || '—' }}</div>
                                    <div><span class="font-semibold text-slate-700">Date:</span> {{ preview.emailData?.date || '—' }}</div>
                                </div>

                                <div class="rounded-lg border border-slate-200 p-3">
                                    <div class="mb-2 text-xs font-semibold uppercase text-slate-500">Message</div>
                                    <div
                                        v-if="preview.emailData?.html_body"
                                        class="email-html-preview rounded bg-white p-2"
                                        v-html="sanitizeEmailHtml(preview.emailData.html_body)"
                                    />
                                    <pre v-else class="text-xs whitespace-pre-wrap bg-slate-50 p-3 rounded">{{ preview.emailData?.body || preview.content || 'No email content available.' }}</pre>
                                </div>

                                <div v-if="preview.emailData?.has_attachments && preview.emailData?.attachments?.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                    <div class="mb-2 text-xs font-semibold uppercase text-amber-800">Attachments ({{ preview.emailData.attachments.length }})</div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div v-for="(attachment, idx) in preview.emailData.attachments" :key="`preview-attachment-${idx}`" class="rounded border border-amber-200 bg-white p-2 text-xs">
                                            <div class="font-medium text-slate-800 truncate">{{ attachment.name || `Attachment ${idx + 1}` }}</div>
                                            <div class="text-slate-500">{{ attachment.type || 'application/octet-stream' }}</div>
                                            <a
                                                v-if="attachment.download_url"
                                                :href="attachment.download_url"
                                                class="mt-2 inline-flex rounded bg-blue-600 px-2 py-1 text-white hover:bg-blue-700"
                                            >
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <a
                                        v-if="preview.downloadUrl"
                                        :href="preview.downloadUrl"
                                        class="rounded bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800"
                                    >
                                        Download Email
                                    </a>
                                    <a
                                        v-if="preview.openOutlookHint && preview.downloadUrl"
                                        :href="`ms-outlook:ofe|u|${preview.downloadUrl}`"
                                        class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                                    >
                                        Open in Outlook
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Spreadsheet Preview -->
                        <div v-else-if="preview.isSpreadsheet">
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-slate-900">Spreadsheet Preview</h4>
                                    <div class="text-sm text-slate-600">
                                        {{ spreadsheetData.totalRows }} rows, {{ spreadsheetData.headers.length }} columns
                                    </div>
                                </div>

                                <div v-if="spreadsheetData.error" class="rounded-lg border border-red-200 bg-red-50 p-4">
                                    <p class="text-red-600">{{ spreadsheetData.error }}</p>
                                </div>

                                <div v-else-if="spreadsheetData.headers.length > 0 && spreadsheetData.totalRows > 0">
                                    <!-- Spreadsheet Pagination -->
                                    <div class="mb-4 flex items-center justify-between">
                                        <div class="text-sm text-slate-600">
                                            Page {{ spreadsheetData.currentPage }} of {{ totalSpreadsheetPages }}
                                            (Rows {{ Math.min((spreadsheetData.currentPage - 1) * spreadsheetData.itemsPerPage + 1, spreadsheetData.totalRows) }}
                                            to {{ Math.min(spreadsheetData.currentPage * spreadsheetData.itemsPerPage, spreadsheetData.totalRows) }})
                                        </div>
                                        <div class="flex gap-2">
                                            <button
                                                @click="changeSpreadsheetPage(spreadsheetData.currentPage - 1)"
                                                :disabled="spreadsheetData.currentPage <= 1"
                                                class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                Previous
                                            </button>
                                            <button
                                                @click="changeSpreadsheetPage(spreadsheetData.currentPage + 1)"
                                                :disabled="spreadsheetData.currentPage >= totalSpreadsheetPages"
                                                class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                Next
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Spreadsheet Table -->
                                    <div class="overflow-auto rounded-lg border">
                                        <table class="min-w-full divide-y divide-slate-200">
                                            <thead class="bg-slate-50">
                                            <tr>
                                                <th
                                                    v-for="(header, index) in spreadsheetData.headers"
                                                    :key="index"
                                                    class="px-4 py-2 text-left text-xs font-medium text-slate-700 uppercase tracking-wider border-r border-slate-200 whitespace-nowrap"
                                                >
                                                    {{ header || `Column ${index + 1}` }}
                                                </th>
                                            </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-slate-200">
                                            <tr
                                                v-for="(row, rowIndex) in getCurrentSpreadsheetPage"
                                                :key="rowIndex"
                                                :class="rowIndex % 2 === 0 ? 'bg-white' : 'bg-slate-50'"
                                            >
                                                <td
                                                    v-for="(cell, cellIndex) in row"
                                                    :key="cellIndex"
                                                    class="px-4 py-2 text-sm text-slate-700 border-r border-slate-200 max-w-xs truncate"
                                                    :title="cell"
                                                >
                                                    {{ cell }}
                                                </td>
                                                <!-- Fill empty cells -->
                                                <td
                                                    v-for="n in spreadsheetData.headers.length - row.length"
                                                    :key="`empty-${n}`"
                                                    class="px-4 py-2 text-sm text-slate-400 border-r border-slate-200"
                                                >
                                                    —
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4 text-xs text-slate-500">
                                        Note: Showing first 500 rows. Download full file for complete data.
                                    </div>
                                </div>

                                <!-- Empty State -->
                                <div v-else-if="spreadsheetData.headers.length === 0 && !preview.loading"
                                     class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center"
                                >
                                    <p class="text-slate-500">No data found in spreadsheet</p>
                                </div>
                            </div>
                        </div>

                        <!-- Text Preview -->
                        <div v-else-if="preview.type === 'text'">
                            <pre class="rounded-lg bg-slate-50 p-4 text-xs whitespace-pre-wrap font-mono">{{ preview.content }}</pre>
                        </div>

                        <!-- PDF Preview -->
                        <div v-else-if="preview.type === 'pdf'">
                            <embed
                                :src="preview.url"
                                type="application/pdf"
                                class="h-96 w-full rounded-lg border"
                            />
                        </div>

                        <!-- Image Preview -->
                        <div v-else-if="preview.type === 'image'">
                            <img
                                :src="preview.url"
                                :alt="preview.title"
                                class="mx-auto max-h-[70vh] max-w-full rounded-lg border"
                            />
                        </div>

                        <!-- Other File Types -->
                        <div v-else class="py-8 text-center">
                            <p class="mb-4 text-slate-500">
                                Preview not available for this file type
                            </p>
                            <a
                                :href="preview.url"
                                download
                                class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                            >
                                Download File
                            </a>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t bg-slate-50 px-6 py-4">
                        <a
                            :href="preview.url"
                            download
                            class="rounded-lg bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                        >
                            Download
                        </a>
                        <button
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                            @click="closePreview"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- All Files Preview Modal -->
            <div v-if="allFilesPreview.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 backdrop-blur-sm p-4">
                <div class="max-h-[90vh] w-full max-w-6xl overflow-hidden rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b bg-slate-50 px-6 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">
                                All Files - {{ allFilesPreview.upload?.reference }}
                            </h3>
                            <div class="mt-1 flex items-center gap-2 text-sm text-slate-600">
                                <span>{{ allFilesPreview.upload?.company?.name || '—' }}</span>
                                <span>•</span>
                                <span class="font-medium">
                                    {{ allFilesPreview.currentIndex + 1 }} of {{ allFilesPreview.files.length }}
                                </span>
                            </div>
                        </div>
                        <button
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
                            @click="closeAllFilesPreview"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex h-[calc(90vh-140px)]">
                        <!-- Sidebar: File List -->
                        <div class="w-64 border-r bg-slate-50 overflow-auto">
                            <div class="p-4">
                                <h4 class="mb-2 text-sm font-semibold text-slate-700">Files</h4>
                                <div class="space-y-1">
                                    <button
                                        v-for="(file, index) in allFilesPreview.files"
                                        :key="index"
                                        @click="changeFile(index)"
                                        class="w-full text-left px-3 py-2 rounded text-sm transition-colors"
                                        :class="allFilesPreview.currentIndex === index
                                            ? 'bg-blue-100 text-blue-700 border border-blue-200'
                                            : 'text-slate-700 hover:bg-slate-100'"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full"
                                                 :class="allFilesPreview.currentIndex === index
                                                    ? 'bg-blue-500'
                                                    : 'bg-slate-300'"
                                            ></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="truncate font-medium">{{ file.name }}</div>
                                                <div class="flex items-center gap-1 mt-0.5">
                                                    <span class="text-xs px-1.5 py-0.5 rounded bg-slate-200 text-slate-600">
                                                        {{ getFileCategoryLabel(file.category) }}
                                                    </span>
                                                    <span class="text-xs text-slate-500">{{ file.type }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content: File Preview -->
                        <div class="flex-1 overflow-auto">
                            <div v-if="allFilesPreview.files.length > 0" class="h-full">
                                <div class="p-4 border-b bg-white flex items-center justify-between">
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">
                                            {{ allFilesPreview.files[allFilesPreview.currentIndex]?.name }}
                                        </h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-700">
                                                {{ getFileCategoryLabel(allFilesPreview.files[allFilesPreview.currentIndex]?.category) }}
                                            </span>
                                            <span class="text-xs text-slate-500">
                                                Type: {{ allFilesPreview.files[allFilesPreview.currentIndex]?.type }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="prevFile"
                                            :disabled="allFilesPreview.currentIndex === 0"
                                            class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Previous
                                        </button>
                                        <button
                                            @click="nextFile"
                                            :disabled="allFilesPreview.currentIndex === allFilesPreview.files.length - 1"
                                            class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Next
                                        </button>
                                    </div>
                                </div>

                                <div class="p-6 h-full overflow-auto">
                                    <div v-if="allFilesPreview.files[allFilesPreview.currentIndex]" class="h-full">
                                        <!-- Loading State -->
                                        <div v-if="allFilesPreview.loading" class="flex items-center justify-center h-full">
                                            <div class="text-center">
                                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                                <p class="text-slate-600">Loading file preview...</p>
                                            </div>
                                        </div>

                                        <!-- Email Preview -->
                                        <div v-else-if="allFilesPreview.files[allFilesPreview.currentIndex].requiresDedicatedPreview">
                                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-6">
                                                <p class="text-sm text-blue-800">
                                                    This file opens in a dedicated preview page for better compatibility.
                                                </p>
                                                <button
                                                    @click="openDedicatedPreview(allFilesPreview.files[allFilesPreview.currentIndex].previewUrl || allFilesPreview.files[allFilesPreview.currentIndex].url)"
                                                    class="mt-4 inline-block rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                                                >
                                                    Open Dedicated Preview
                                                </button>
                                            </div>
                                        </div>

                                        <div v-else-if="allFilesPreview.files[allFilesPreview.currentIndex].type === 'email'">
                                            <div class="mb-4 rounded-lg border bg-white p-4 space-y-4">
                                                <div class="grid gap-3 text-sm sm:grid-cols-2">
                                                    <div><span class="font-semibold text-slate-700">From:</span> {{ allFilesPreview.files[allFilesPreview.currentIndex].emailData?.from || '—' }}</div>
                                                    <div><span class="font-semibold text-slate-700">To:</span> {{ allFilesPreview.files[allFilesPreview.currentIndex].emailData?.to || '—' }}</div>
                                                    <div><span class="font-semibold text-slate-700">Subject:</span> {{ allFilesPreview.files[allFilesPreview.currentIndex].emailData?.subject || '—' }}</div>
                                                    <div><span class="font-semibold text-slate-700">Date:</span> {{ allFilesPreview.files[allFilesPreview.currentIndex].emailData?.date || '—' }}</div>
                                                </div>

                                                <div class="rounded-lg border border-slate-200 p-3">
                                                    <div class="mb-2 text-xs font-semibold uppercase text-slate-500">Message</div>
                                                    <div
                                                        v-if="allFilesPreview.files[allFilesPreview.currentIndex].emailData?.html_body"
                                                        class="email-html-preview rounded bg-white p-2"
                                                        v-html="sanitizeEmailHtml(allFilesPreview.files[allFilesPreview.currentIndex].emailData?.html_body)"
                                                    />
                                                    <pre v-else class="text-xs whitespace-pre-wrap bg-slate-50 p-3 rounded">{{ allFilesPreview.files[allFilesPreview.currentIndex].emailData?.body || allFilesPreview.files[allFilesPreview.currentIndex].content || 'No email content available.' }}</pre>
                                                </div>

                                                <div v-if="allFilesPreview.files[allFilesPreview.currentIndex].emailData?.has_attachments && allFilesPreview.files[allFilesPreview.currentIndex].emailData?.attachments?.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                                    <div class="mb-2 text-xs font-semibold uppercase text-amber-800">
                                                        Attachments ({{ allFilesPreview.files[allFilesPreview.currentIndex].emailData.attachments.length }})
                                                    </div>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div v-for="(attachment, idx) in allFilesPreview.files[allFilesPreview.currentIndex].emailData.attachments" :key="`all-files-attachment-${idx}`" class="rounded border border-amber-200 bg-white p-2 text-xs">
                                                            <div class="font-medium text-slate-800 truncate">{{ attachment.name || `Attachment ${idx + 1}` }}</div>
                                                            <div class="text-slate-500">{{ attachment.type || 'application/octet-stream' }}</div>
                                                            <a
                                                                v-if="attachment.download_url"
                                                                :href="attachment.download_url"
                                                                class="mt-2 inline-flex rounded bg-blue-600 px-2 py-1 text-white hover:bg-blue-700"
                                                            >
                                                                Download
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex flex-wrap gap-2">
                                                    <a
                                                        v-if="allFilesPreview.files[allFilesPreview.currentIndex].downloadUrl"
                                                        :href="allFilesPreview.files[allFilesPreview.currentIndex].downloadUrl"
                                                        class="rounded bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800"
                                                    >
                                                        Download Email
                                                    </a>
                                                    <a
                                                        v-if="allFilesPreview.files[allFilesPreview.currentIndex].openOutlookHint && allFilesPreview.files[allFilesPreview.currentIndex].downloadUrl"
                                                        :href="`ms-outlook:ofe|u|${allFilesPreview.files[allFilesPreview.currentIndex].downloadUrl}`"
                                                        class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                                                    >
                                                        Open in Outlook
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Spreadsheet Preview -->
                                        <div v-else-if="allFilesPreview.files[allFilesPreview.currentIndex].type === 'spreadsheet'">
                                            <div class="mb-4">
                                                <div class="flex items-center justify-between mb-4">
                                                    <h4 class="text-lg font-semibold text-slate-900">Spreadsheet Preview</h4>
                                                    <div v-if="allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData"
                                                         class="text-sm text-slate-600">
                                                        {{ allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.totalRows }} rows,
                                                        {{ allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.headers.length }} columns
                                                    </div>
                                                </div>

                                                <div v-if="allFilesPreview.files[allFilesPreview.currentIndex].error"
                                                     class="rounded-lg border border-red-200 bg-red-50 p-4">
                                                    <p class="text-red-600">{{ allFilesPreview.files[allFilesPreview.currentIndex].error }}</p>
                                                </div>

                                                <div v-else-if="allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData?.headers?.length > 0 &&
                                                                allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData?.totalRows > 0">
                                                    <!-- Spreadsheet Pagination -->
                                                    <div class="mb-4 flex items-center justify-between">
                                                        <div class="text-sm text-slate-600">
                                                            Page {{ allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.currentPage }}
                                                            of {{ getAllFilesTotalSpreadsheetPages }}
                                                        </div>
                                                        <div class="flex gap-2">
                                                            <button
                                                                @click="changeAllFilesSpreadsheetPage(allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.currentPage - 1)"
                                                                :disabled="allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.currentPage <= 1"
                                                                class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                                            >
                                                                Previous
                                                            </button>
                                                            <button
                                                                @click="changeAllFilesSpreadsheetPage(allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.currentPage + 1)"
                                                                :disabled="allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.currentPage >= getAllFilesTotalSpreadsheetPages"
                                                                class="rounded-lg border px-3 py-1 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                                            >
                                                                Next
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Spreadsheet Table -->
                                                    <div class="overflow-auto rounded-lg border">
                                                        <table class="min-w-full divide-y divide-slate-200">
                                                            <thead class="bg-slate-50">
                                                            <tr>
                                                                <th
                                                                    v-for="(header, index) in allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.headers"
                                                                    :key="index"
                                                                    class="px-4 py-2 text-left text-xs font-medium text-slate-700 uppercase tracking-wider border-r border-slate-200 whitespace-nowrap"
                                                                >
                                                                    {{ header || `Column ${index + 1}` }}
                                                                </th>
                                                            </tr>
                                                            </thead>
                                                            <tbody class="bg-white divide-y divide-slate-200">
                                                            <tr
                                                                v-for="(row, rowIndex) in getAllFilesSpreadsheetPage"
                                                                :key="rowIndex"
                                                                :class="rowIndex % 2 === 0 ? 'bg-white' : 'bg-slate-50'"
                                                            >
                                                                <td
                                                                    v-for="(cell, cellIndex) in row"
                                                                    :key="cellIndex"
                                                                    class="px-4 py-2 text-sm text-slate-700 border-r border-slate-200 max-w-xs truncate"
                                                                    :title="cell"
                                                                >
                                                                    {{ cell }}
                                                                </td>
                                                                <!-- Fill empty cells -->
                                                                <td
                                                                    v-for="n in allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData.headers.length - row.length"
                                                                    :key="`empty-${n}`"
                                                                    class="px-4 py-2 text-sm text-slate-400 border-r border-slate-200"
                                                                >
                                                                    —
                                                                </td>
                                                            </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <div class="mt-4 text-xs text-slate-500">
                                                        Note: Showing first 500 rows. Download full file for complete data.
                                                    </div>
                                                </div>

                                                <!-- Empty State -->
                                                <div v-else-if="!allFilesPreview.files[allFilesPreview.currentIndex].spreadsheetData?.headers?.length && !allFilesPreview.loading"
                                                     class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center"
                                                >
                                                    <p class="text-slate-500">No data found in spreadsheet</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Text Preview -->
                                        <pre
                                            v-else-if="allFilesPreview.files[allFilesPreview.currentIndex].type === 'text'"
                                            class="rounded-lg bg-slate-50 p-4 text-xs h-full overflow-auto whitespace-pre-wrap font-mono"
                                        >
                                            {{ allFilesPreview.files[allFilesPreview.currentIndex].content || 'No content available' }}
                                        </pre>

                                        <!-- PDF Preview -->
                                        <embed
                                            v-else-if="allFilesPreview.files[allFilesPreview.currentIndex].type === 'pdf'"
                                            :src="allFilesPreview.files[allFilesPreview.currentIndex].url"
                                            type="application/pdf"
                                            class="w-full h-full rounded-lg border"
                                        />

                                        <!-- Image Preview -->
                                        <img
                                            v-else-if="allFilesPreview.files[allFilesPreview.currentIndex].type === 'image'"
                                            :src="allFilesPreview.files[allFilesPreview.currentIndex].url"
                                            :alt="allFilesPreview.files[allFilesPreview.currentIndex].name"
                                            class="mx-auto max-h-full max-w-full rounded-lg border"
                                        />

                                        <!-- Other File Types -->
                                        <div v-else class="h-full flex items-center justify-center">
                                            <div class="text-center">
                                                <p class="mb-4 text-slate-500">
                                                    Preview not available for this file type
                                                </p>
                                                <a
                                                    :href="allFilesPreview.files[allFilesPreview.currentIndex].url"
                                                    download
                                                    class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                                >
                                                    Download File
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="h-full flex items-center justify-center">
                                <div class="text-center text-slate-500">
                                    No files available for this upload
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center border-t bg-slate-50 px-6 py-4">
                        <div>
                            <a
                                :href="allFilesPreview.files[allFilesPreview.currentIndex]?.url"
                                download
                                class="rounded-lg bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                                v-if="allFilesPreview.files[allFilesPreview.currentIndex]"
                            >
                                Download Current File
                            </a>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                @click="closeAllFilesPreview"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped src="@/../css/Centralized/Pages/Uploads/History.css"></style>

