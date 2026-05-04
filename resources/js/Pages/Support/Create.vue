<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    companies: Array,
    municipalities: Array,
    uploads: Array,
    staffUsers: Array,
    linkedUpload: Object,
    isStaff: Boolean,
});

const form = useForm({
    subject: '',
    body: '',
    priority: 'medium',
    category: 'general',
    company_id: props.linkedUpload?.company_id || '',
    municipality_id: props.linkedUpload?.municipality_id || '',
    upload_id: props.linkedUpload?.id || '',
    assigned_to: '',
    attachments: [],
});

// Auto-fill subject when linking to upload
if (props.linkedUpload) {
    form.subject = `Query about upload ${props.linkedUpload.reference}`;
    form.category = 'upload_query';
}

const fileInput = ref(null);

const addFiles = (e) => {
    const files = Array.from(e.target.files);
    form.attachments = [...form.attachments, ...files];
    if (fileInput.value) fileInput.value.value = '';
};

const removeFile = (idx) => {
    form.attachments = form.attachments.filter((_, i) => i !== idx);
};

const formatSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const submit = () => {
    form.post('/support', {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <Link href="/support" class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    Back to Tickets
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">New Support Ticket</h1>
                <p class="mt-1 text-sm text-gray-500">Describe your issue and we'll get back to you</p>
            </div>

            <!-- Linked Upload Banner -->
            <div v-if="linkedUpload"
                 class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                <div class="flex items-center gap-2 text-sm text-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.07-9.07l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
                    <span class="font-medium">Linked to upload {{ linkedUpload.reference }}</span>
                    <span v-if="linkedUpload.company">| {{ linkedUpload.company.name }}</span>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">Ticket Details</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <!-- Subject -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Subject</label>
                            <input v-model="form.subject" type="text" required
                                   class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                   placeholder="Brief description of your issue" />
                            <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                        </div>

                        <!-- Category + Priority -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <select v-model="form.category" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="general">General</option>
                                    <option value="upload_query">Upload Query</option>
                                    <option value="verification_issue">Verification Issue</option>
                                    <option value="deadline_query">Deadline Query</option>
                                    <option value="account_issue">Account Issue</option>
                                    <option value="data_correction">Data Correction</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Priority</label>
                                <select v-model="form.priority" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <!-- Link to Company / Municipality / Upload -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Company</label>
                                <select v-model="form.company_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Municipality</label>
                                <select v-model="form.municipality_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="m in municipalities" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Linked Upload</label>
                                <select v-model="form.upload_id" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="u in uploads" :key="u.id" :value="u.id">{{ u.reference }} ({{ u.status }})</option>
                                </select>
                            </div>
                        </div>

                        <!-- Assign to (staff only) -->
                        <div v-if="isStaff && staffUsers.length">
                            <label class="block text-sm font-medium text-gray-700">Assign To</label>
                            <select v-model="form.assigned_to" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <option value="">Unassigned</option>
                                <option v-for="u in staffUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Message -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">Message</h2>
                    </div>
                    <div class="p-5">
                        <textarea v-model="form.body" required rows="6"
                                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                  placeholder="Describe your issue in detail..." />
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</p>

                        <!-- Attachments -->
                        <div class="mt-3">
                            <label class="mb-1 block text-xs font-medium text-gray-500">Attachments (optional, max 5MB each)</label>
                            <input ref="fileInput" type="file" multiple @change="addFiles" class="hidden" />
                            <button type="button" @click="fileInput?.click()"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                Attach files
                            </button>
                            <div v-if="form.attachments.length" class="mt-2 space-y-1">
                                <div v-for="(f, i) in form.attachments" :key="i"
                                     class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs">
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    <span class="flex-1 truncate text-gray-700">{{ f.name }}</span>
                                    <span class="text-gray-400">{{ formatSize(f.size) }}</span>
                                    <button type="button" @click="removeFile(i)" class="text-red-400 hover:text-red-600">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-3">
                    <Link href="/support" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                            class="rounded-lg bg-gradient-to-r from-indigo-500 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md hover:from-indigo-600 hover:to-violet-700 disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : 'Create Ticket' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
