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
    counts: Object,
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

let searchTimeout = null;

const applyFilters = () =>
    router.get('/uploads/history', form.value, { preserveState: true, replace: true });

// Dynamic search with debounce
watch(() => form.value.search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

watch(() => form.value.per_page, () => applyFilters());
watch(() => form.value.status, () => applyFilters());

const resetFilters = () => {
    form.value = { status: '', search: '', per_page: 20 };
    applyFilters();
};

/* ------------ Expandable rows ------------ */
const expandedRows = ref(new Set());
const toggleRow = (id) => {
    const s = new Set(expandedRows.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expandedRows.value = s;
};

/* ------------ CAPS Dispatch ------------ */
const dispatchToCaps = (uploadId) => {
    if (!confirm('Dispatch this submission to CAPS for processing?')) return;
    router.post(`/uploads/${uploadId}/dispatch-to-caps`, {}, {
        preserveState: true,
        onSuccess: () => router.reload(),
    });
};

const retryCapsDispatch = (uploadId) => {
    if (!confirm('Retry dispatching this submission to CAPS?')) return;
    router.post(`/uploads/${uploadId}/retry-caps`, {}, {
        preserveState: true,
        onSuccess: () => router.reload(),
    });
};

const saveToCaps = (uploadId) => {
    if (!confirm('Save this batch to CAPS? This will finalize the premiums.')) return;
    router.post(`/uploads/${uploadId}/save-to-caps`, {}, {
        preserveState: true,
        onSuccess: () => router.reload(),
    });
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
            data.rows = rows;
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

            data.rows = json.slice(startRow);
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
            rows: sd.rows || [],
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
            importOnly,
            workingsOnly,
            premiumMismatch,
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
    municipalityName: '',
    results: null,
    searchQuery: '',
});

const capsVerifyFocus = ref('member_not_found');

const capsVerifyTabs = [
    { key: 'member_not_found', label: 'Members Not in CAPS', shortLabel: 'Members Missing', activeBorder: 'border-red-500', iconBg: 'bg-red-100', iconColor: 'text-red-600', countColor: 'text-red-700', badgeBg: 'bg-red-500', icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
    { key: 'policy_not_found', label: 'Policies Not in CAPS', shortLabel: 'Policies Missing', activeBorder: 'border-orange-500', iconBg: 'bg-orange-100', iconColor: 'text-orange-600', countColor: 'text-orange-700', badgeBg: 'bg-orange-500', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { key: 'premium_mismatch', label: 'Premium Amount Mismatches', shortLabel: 'Premium Mismatch', activeBorder: 'border-amber-500', iconBg: 'bg-amber-100', iconColor: 'text-amber-600', countColor: 'text-amber-700', badgeBg: 'bg-amber-500', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'member_found', label: 'Members Verified in CAPS', shortLabel: 'Members OK', activeBorder: 'border-green-500', iconBg: 'bg-green-100', iconColor: 'text-green-600', countColor: 'text-green-700', badgeBg: 'bg-green-500', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'policy_found', label: 'Policies Verified in CAPS', shortLabel: 'Policies OK', activeBorder: 'border-teal-500', iconBg: 'bg-teal-100', iconColor: 'text-teal-600', countColor: 'text-teal-700', badgeBg: 'bg-teal-500', icon: 'M5 13l4 4L19 7' },
];

const capsVerifyAllRows = computed(() => {
    const results = capsVerifyState.value.results;
    if (!results) return [];
    return results[capsVerifyFocus.value] || [];
});

const capsVerifyCurrentRows = computed(() => {
    let rows = capsVerifyAllRows.value;
    const q = (capsVerifyState.value.searchQuery || '').trim().toLowerCase();
    if (q) {
        rows = rows.filter(r =>
            (r.memberId || '').toLowerCase().includes(q) ||
            (r.personelNumber || '').toLowerCase().includes(q) ||
            (r.policyCode || '').toLowerCase().includes(q)
        );
    }
    return rows;
});

const capsVerifyScore = computed(() => {
    const r = capsVerifyState.value.results;
    if (!r) return 0;
    const total = r.uploaded_rows_total || 1;
    const issues = (r.member_not_found?.length || 0) + (r.policy_not_found?.length || 0) + (r.premium_mismatch?.length || 0);
    if (total === 0) return 100;
    return Math.round(Math.max(0, ((total - issues) / total) * 100));
});

const capsVerifyTotalIssues = computed(() => {
    const r = capsVerifyState.value.results;
    if (!r) return 0;
    return (r.member_not_found?.length || 0) + (r.policy_not_found?.length || 0) + (r.premium_mismatch?.length || 0);
});

const capsVerifyTotalVerified = computed(() => {
    const r = capsVerifyState.value.results;
    if (!r) return 0;
    return (r.member_found?.length || 0) + (r.policy_found?.length || 0);
});

const capsVerifyMismatchTotal = computed(() => {
    const r = capsVerifyState.value.results;
    if (!r) return 0;
    return (r.premium_mismatch || []).reduce((sum, row) => {
        const diff = Math.abs(Number(row.uploaded_premium ?? row.premiumAmount ?? 0) - Number(row.caps_premium ?? 0));
        return sum + diff;
    }, 0);
});

const exportVerificationCsv = () => {
    const rows = capsVerifyAllRows.value;
    if (!rows.length) return;
    const headers = ['Member ID', 'Employee No', 'Policy Code', 'Uploaded Premium', 'CAPS Premium', 'Difference'];
    const csvRows = rows.map(r => [
        r.memberId || '', r.personelNumber || '', r.policyCode || '',
        r.uploaded_premium ?? r.premiumAmount ?? '',
        r.caps_premium ?? '',
        capsVerifyFocus.value === 'premium_mismatch' ? (Number(r.uploaded_premium ?? r.premiumAmount ?? 0) - Number(r.caps_premium ?? 0)).toFixed(2) : '',
    ]);
    const csv = [headers, ...csvRows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `verification-${capsVerifyFocus.value}-${capsVerifyState.value.uploadReference || 'export'}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
};

const compareWithCapsMembers = async (upload) => {
    capsVerifyState.value = {
        show: true,
        loading: true,
        error: null,
        uploadReference: upload?.reference || '',
        companyName: upload?.company?.name || '',
        municipalityName: upload?.municipality?.name || '',
        results: null,
        searchQuery: '',
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

        // Default to the tab with the most issues
        const r = json.results;
        if ((r.member_not_found?.length || 0) > 0) capsVerifyFocus.value = 'member_not_found';
        else if ((r.policy_not_found?.length || 0) > 0) capsVerifyFocus.value = 'policy_not_found';
        else if ((r.premium_mismatch?.length || 0) > 0) capsVerifyFocus.value = 'premium_mismatch';
        else capsVerifyFocus.value = 'member_found';

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
        municipalityName: upload.municipality?.name || '',
        results,
        searchQuery: '',
    };

    // Default to the tab with the most issues
    if ((results.member_not_found?.length || 0) > 0) capsVerifyFocus.value = 'member_not_found';
    else if ((results.policy_not_found?.length || 0) > 0) capsVerifyFocus.value = 'policy_not_found';
    else if ((results.premium_mismatch?.length || 0) > 0) capsVerifyFocus.value = 'premium_mismatch';
    else capsVerifyFocus.value = 'member_found';
};

const closeCapsVerify = () => {
    capsVerifyState.value = { show: false, loading: false, error: null, uploadReference: '', companyName: '', municipalityName: '', results: null, searchQuery: '' };
};
</script>

<template>
    <AppLayout>
        <div class="px-6 py-6">

            <!-- Flash -->
            <div v-if="page.props.flash?.success" class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <svg class="h-4 w-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <svg class="h-4 w-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                {{ page.props.flash.error }}
            </div>

            <!-- Header + Stats -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">View Premiums</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Premium batch submission history</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="exportData" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export
                    </button>
                    <Link href="/uploads" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 shadow-sm transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        New Upload
                    </Link>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-sm text-slate-500">Total Submissions</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">{{ counts?.total ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <div class="text-sm text-amber-700">Needs Review</div>
                    <div class="text-2xl font-bold text-amber-800 mt-1">{{ counts?.needs_review ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
                    <div class="text-sm text-green-700">Saved to CAPS</div>
                    <div class="text-2xl font-bold text-green-800 mt-1">{{ counts?.saved ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                    <div class="text-sm text-red-700">Failed</div>
                    <div class="text-2xl font-bold text-red-800 mt-1">{{ counts?.failed ?? 0 }}</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-3 mb-4 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">All</option>
                    <option value="caps_processing">Needs Review</option>
                    <option value="completed">Saved</option>
                </select>
                <input v-model="form.search" placeholder="Search by reference, company..." @keyup.enter="applyFilters"
                       class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" />
                <select v-model="form.per_page" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option v-for="o in perPageOptions || [20,50,100]" :key="o" :value="o">{{ o }} rows</option>
                </select>
                <button @click="resetFilters" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 transition">Clear</button>
            </div>

            <!-- Empty -->
            <div v-if="!uploads?.data?.length" class="rounded-xl border border-slate-200 bg-white py-20 text-center shadow-sm">
                <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                <p class="text-sm text-slate-500">No submissions found</p>
            </div>

            <!-- Table -->
            <div v-if="uploads?.data?.length" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-left">
                        <tr>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider w-14"># &darr;</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">File</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider text-right">Total</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider text-right">New</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider text-right">Upd</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider text-right">Can</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider text-right">Err</th>
                            <th class="px-4 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="u in (uploads?.data || [])" :key="u.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-400">{{ u.id }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block rounded px-2 py-0.5 text-[11px] font-medium"
                                      :class="{
                                          'bg-green-50 text-green-700': u.caps_dispatch_status === 'completed',
                                          'bg-amber-50 text-amber-700': u.caps_dispatch_status === 'caps_processing',
                                          'bg-red-50 text-red-700': u.caps_dispatch_status === 'failed',
                                          'bg-gray-100 text-gray-600': !u.caps_dispatch_status || u.caps_dispatch_status === 'draft',
                                          'bg-blue-50 text-blue-700': u.caps_dispatch_status === 'dispatched',
                                      }">
                                    {{ u.caps_dispatch_status === 'caps_processing' ? 'Review' : u.caps_dispatch_status === 'completed' ? 'Saved' : u.status }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-gray-700">{{ formatDateTime(u.submitted_at_formatted) }}</td>
                            <td class="px-4 py-2.5">
                                <div v-if="u.user" class="flex items-center gap-2">
                                    <div class="h-6 w-6 rounded-full bg-gray-200 flex items-center justify-center text-[10px] font-semibold text-gray-600">{{ getUserAvatar(u.user).initials }}</div>
                                    <span class="text-xs text-gray-700">{{ u.user.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="text-xs text-gray-800 max-w-[220px] truncate">{{ u.company?.name }}</div>
                                <div class="text-[10px] text-gray-400">{{ u.municipality?.name }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-medium text-gray-800">{{ u.caps_summary?.total ?? '\u2014' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums" :class="(u.caps_summary?.caps_new ?? 0) > 0 ? 'font-semibold text-green-700' : 'text-gray-300'">{{ u.caps_summary?.caps_new ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums" :class="(u.caps_summary?.caps_updated ?? 0) > 0 ? 'font-semibold text-blue-700' : 'text-gray-300'">{{ u.caps_summary?.caps_updated ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums" :class="(u.caps_summary?.caps_cancelled ?? 0) > 0 ? 'font-semibold text-amber-700' : 'text-gray-300'">{{ u.caps_summary?.caps_cancelled ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums" :class="(u.caps_summary?.caps_errors ?? 0) > 0 ? 'font-semibold text-red-600' : 'text-gray-300'">{{ u.caps_summary?.caps_errors ?? 0 }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <button v-if="(u.all_previewable_files || []).length" @click.stop="openAllFilesPreview(u)"
                                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        Files
                                    </button>
                                    <a v-if="u.caps_payment_batch_id" :href="`/uploads/${u.id}/caps-batch-detail`"
                                       class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                        Review
                                    </a>
                                    <button v-if="u.can_dispatch_to_caps" @click.stop="dispatchToCaps(u.id)"
                                            class="inline-flex items-center gap-1 rounded-md bg-slate-800 px-2 py-1 text-[11px] font-medium text-white hover:bg-slate-900 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                        Send
                                    </button>
                                    <button v-if="u.can_retry_caps" @click.stop="retryCapsDispatch(u.id)"
                                            class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-700 hover:bg-amber-100 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                        Retry
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div v-if="uploads?.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 bg-slate-50">
                    <span class="text-xs text-slate-500">{{ uploads.from }}&ndash;{{ uploads.to }} of {{ uploads.total }}</span>
                    <div class="flex gap-1">
                        <button v-for="link in uploads.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })" :disabled="!link.url" class="rounded-lg px-3 py-1 text-xs transition" :class="link.active ? 'bg-slate-800 text-white' : link.url ? 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' : 'text-slate-300'" v-html="link.label"></button>
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

