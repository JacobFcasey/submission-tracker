<template>
    <AppLayout>
        <div class="min-h-screen bg-gray-50 p-6">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Email Preview</h1>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ upload.company?.name || '—' }} • {{ upload.municipality?.name || '—' }} • {{ upload.reference || '—' }}
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                File: {{ file_name }}
                                <span v-if="email_data.note" class="ml-2 text-amber-600">
                                    ({{ email_data.note }})
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <a :href="download_url" download class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                                Download File
                            </a>
                            <button
                                v-if="open_outlook_hint"
                                @click="openInOutlook"
                                class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-800 text-sm"
                            >
                                Open in Outlook
                            </button>
                            <Link href="/uploads" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 text-sm">
                                Back to Uploads
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="bg-white rounded-lg shadow">
                    <!-- Email Headers -->
                    <div class="border-b p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-gray-500">From</div>
                                <div class="font-medium">{{ email_data.from || '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">To</div>
                                <div class="font-medium">{{ email_data.to || '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Subject</div>
                                <div class="font-medium">{{ email_data.subject || '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Date</div>
                                <div class="font-medium">{{ email_data.date || '—' }}</div>
                            </div>
                            <div v-if="email_data.cc" class="md:col-span-2">
                                <div class="text-sm text-gray-500">CC</div>
                                <div class="font-medium">{{ email_data.cc }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Body -->
                    <div class="p-6">
                        <div class="flex border-b mb-4">
                            <button
                                v-for="tab in availableTabs"
                                :key="tab"
                                @click="activeTab = tab"
                                :class="[
                  'px-4 py-2 font-medium text-sm border-b-2 -mb-px',
                  activeTab === tab
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                ]"
                            >
                                {{ tabLabels[tab] }}
                            </button>
                        </div>

                        <div v-if="activeTab === 'text' && email_data.body" class="email-content">
                            <pre class="whitespace-pre-wrap font-sans text-sm bg-gray-50 p-4 rounded border">{{ email_data.body }}</pre>
                        </div>

                        <div v-else-if="activeTab === 'html' && email_data.html_body" class="email-content">
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <iframe
                                    v-if="email_data.html_body"
                                    :srcdoc="email_data.html_body"
                                    class="w-full h-96 border-0"
                                    sandbox="allow-same-origin"
                                ></iframe>
                                <div v-else class="text-center py-12 text-gray-500">
                                    No HTML content available
                                </div>
                            </div>
                        </div>

                        <div v-else-if="activeTab === 'raw' && raw_content" class="email-content">
                            <pre class="whitespace-pre-wrap font-mono text-xs overflow-x-auto bg-gray-50 p-4 rounded border">{{ raw_content }}</pre>
                        </div>

                        <div v-else class="text-center py-12 text-gray-500">
                            No content available for this view
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div v-if="email_data.has_attachments && email_data.attachments && email_data.attachments.length > 0" class="border-t p-6">
                        <h3 class="font-medium text-gray-900 mb-3">Attachments ({{ email_data.attachments.length }})</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="(attachment, index) in email_data.attachments"
                                :key="index"
                                class="border rounded-lg p-3 hover:bg-gray-50"
                            >
                                <div class="flex items-center">
                                    <div class="text-blue-600 mr-3">
                                        📎
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-sm truncate">{{ attachment.name }}</div>
                                        <div class="text-xs text-gray-500">{{ attachment.type }}</div>
                                        <div v-if="attachment.size" class="text-xs text-gray-500">
                                            {{ formatFileSize(attachment.size) }}
                                        </div>
                                        <div class="mt-2">
                                            <a
                                                v-if="attachment.download_url"
                                                :href="attachment.download_url"
                                                class="inline-flex items-center rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700"
                                            >
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parsing Notes -->
                    <div v-if="email_data.note || email_data.parsed_successfully === false || email_data.error" class="border-t p-6 bg-yellow-50">
                        <div class="flex items-start">
                            <div class="text-yellow-600 mr-2">ℹ️</div>
                            <div class="text-sm text-yellow-800">
                                <span v-if="email_data.note">{{ email_data.note }}</span>
                                <span v-if="email_data.error">Error: {{ email_data.error }}</span>
                                <span v-if="email_data.parsed_successfully === false">
                  Email parsing was not fully successful. Some information may be missing.
                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    upload: {
        type: Object,
        required: true,
        default: () => ({})
    },
    file_index: {
        type: Number,
        default: 0
    },
    file_name: {
        type: String,
        default: ''
    },
    file_type: {
        type: String,
        default: ''
    },
    email_data: {
        type: Object,
        default: () => ({
            headers: {},
            subject: '',
            from: '',
            to: '',
            cc: '',
            bcc: '',
            date: null,
            body: '',
            html_body: '',
            attachments: [],
            has_attachments: false,
            parsed_successfully: false,
            note: '',
            error: ''
        })
    },
    download_url: {
        type: String,
        default: ''
    },
    raw_content: {
        type: String,
        default: ''
    },
    open_outlook_hint: {
        type: Boolean,
        default: false,
    }
});

const activeTab = ref('text');

// Dynamically determine available tabs
const availableTabs = computed(() => {
    const tabs = [];
    if (props.email_data.body) tabs.push('text');
    if (props.email_data.html_body) tabs.push('html');
    if (props.raw_content) tabs.push('raw');
    return tabs.length > 0 ? tabs : ['text'];
});

const tabLabels = {
    text: 'Text',
    html: 'HTML',
    raw: 'Raw'
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Ensure we show something by default
onMounted(() => {
    if (props.email_data.html_body) {
        activeTab.value = 'html';
    } else if (props.email_data.body) {
        activeTab.value = 'text';
    } else if (props.raw_content) {
        activeTab.value = 'raw';
    }

    if (!props.email_data.body && !props.email_data.html_body && !props.raw_content) {
        console.warn('No email content received from backend');
    }
});

const openInOutlook = () => {
    if (!props.download_url) return;
    window.location.href = `ms-outlook:ofe|u|${props.download_url}`;
};
</script>

<style scoped src="@/../css/Centralized/Pages/Uploads/ViewEmail.css"></style>

