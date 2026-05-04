<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, nextTick, onMounted } from 'vue';
import { useForm, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    ticket: Object,
    messages: Array,
    isStaff: Boolean,
    staffUsers: Array,
    currentUserId: Number,
});

const page = usePage();
const messagesEnd = ref(null);
const fileInput = ref(null);

const replyForm = useForm({
    body: '',
    is_internal: false,
    attachments: [],
});

const statusForm = useForm({
    status: props.ticket.status,
    assigned_to: props.ticket.assigned_to || '',
});

const scrollToBottom = () => {
    nextTick(() => messagesEnd.value?.scrollIntoView({ behavior: 'smooth' }));
};

onMounted(scrollToBottom);

const sendReply = () => {
    replyForm.post(`/support/${props.ticket.id}/reply`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            replyForm.attachments = [];
            scrollToBottom();
        },
    });
};

const updateStatus = () => {
    statusForm.put(`/support/${props.ticket.id}/status`, {
        preserveScroll: true,
    });
};

const addFiles = (e) => {
    replyForm.attachments = [...replyForm.attachments, ...Array.from(e.target.files)];
    if (fileInput.value) fileInput.value.value = '';
};

const removeFile = (idx) => {
    replyForm.attachments = replyForm.attachments.filter((_, i) => i !== idx);
};

const statusConfig = {
    open: { label: 'Open', bg: 'bg-blue-100', text: 'text-blue-700' },
    in_progress: { label: 'In Progress', bg: 'bg-indigo-100', text: 'text-indigo-700' },
    waiting_on_company: { label: 'Awaiting Company', bg: 'bg-amber-100', text: 'text-amber-700' },
    waiting_on_casey: { label: 'Awaiting Casey', bg: 'bg-orange-100', text: 'text-orange-700' },
    resolved: { label: 'Resolved', bg: 'bg-emerald-100', text: 'text-emerald-700' },
    closed: { label: 'Closed', bg: 'bg-gray-100', text: 'text-gray-500' },
};

const priorityConfig = {
    low: { label: 'Low', color: 'text-gray-500', ring: 'ring-gray-200' },
    medium: { label: 'Medium', color: 'text-blue-600', ring: 'ring-blue-200' },
    high: { label: 'High', color: 'text-orange-600', ring: 'ring-orange-200' },
    urgent: { label: 'Urgent', color: 'text-red-600', ring: 'ring-red-200' },
};

const categoryLabels = {
    upload_query: 'Upload Query', verification_issue: 'Verification Issue',
    deadline_query: 'Deadline Query', account_issue: 'Account Issue',
    data_correction: 'Data Correction', general: 'General',
};

const formatTime = (ts) => {
    if (!ts) return '';
    return new Date(ts).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' });
};

const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
};

const isMine = (msg) => msg.user_id === props.currentUserId;
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex gap-6">
                <!-- Main Chat Column -->
                <div class="min-w-0 flex-1">
                    <!-- Header -->
                    <div class="mb-4">
                        <Link href="/support" class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                            All Tickets
                        </Link>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-mono text-gray-400">{{ ticket.reference }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                          :class="[statusConfig[ticket.status]?.bg, statusConfig[ticket.status]?.text]">
                                        {{ statusConfig[ticket.status]?.label }}
                                    </span>
                                    <span class="text-[10px] font-medium" :class="priorityConfig[ticket.priority]?.color">
                                        {{ priorityConfig[ticket.priority]?.label }}
                                    </span>
                                </div>
                                <h1 class="mt-1 text-xl font-bold text-gray-900">{{ ticket.subject }}</h1>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="max-h-[60vh] space-y-0 overflow-y-auto p-5" style="scroll-behavior: smooth;">
                            <div v-for="msg in messages" :key="msg.id"
                                 :class="[
                                     'relative mb-4 rounded-xl px-4 py-3',
                                     msg.is_internal
                                         ? 'ml-8 border-2 border-dashed border-yellow-200 bg-yellow-50'
                                         : isMine(msg)
                                             ? 'ml-12 bg-gradient-to-br from-indigo-50 to-violet-50 ring-1 ring-indigo-100'
                                             : 'mr-12 bg-gray-50 ring-1 ring-gray-100'
                                 ]">
                                <!-- Internal note badge -->
                                <div v-if="msg.is_internal"
                                     class="absolute -top-2 left-3 rounded-full bg-yellow-400 px-2 py-0.5 text-[9px] font-bold text-yellow-900">
                                    INTERNAL NOTE
                                </div>

                                <!-- Sender -->
                                <div class="mb-2 flex items-center gap-2">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-bold text-white"
                                         :class="isMine(msg) ? 'bg-indigo-500' : 'bg-emerald-500'">
                                        {{ getInitials(msg.user?.name) }}
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700">{{ msg.user?.name || 'Unknown' }}</span>
                                    <span class="text-[10px] text-gray-400">{{ formatTime(msg.created_at) }}</span>
                                </div>

                                <!-- Body -->
                                <div class="whitespace-pre-wrap text-sm text-gray-700 leading-relaxed">{{ msg.body }}</div>

                                <!-- Attachments -->
                                <div v-if="msg.attachments?.length" class="mt-3 space-y-1">
                                    <a v-for="(att, idx) in msg.attachments" :key="idx"
                                       :href="`/support/${ticket.id}/messages/${msg.id}/attachments/${idx}`"
                                       class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-indigo-600 ring-1 ring-indigo-100 hover:bg-indigo-50">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32"/></svg>
                                        {{ att.name }}
                                    </a>
                                </div>
                            </div>
                            <div ref="messagesEnd"></div>
                        </div>

                        <!-- Reply Box -->
                        <div v-if="!['closed'].includes(ticket.status)"
                             class="border-t border-gray-100 bg-gray-50/50 p-4">
                            <form @submit.prevent="sendReply">
                                <!-- Internal note toggle (staff only) -->
                                <div v-if="isStaff" class="mb-2">
                                    <label class="inline-flex items-center gap-2 text-xs">
                                        <input type="checkbox" v-model="replyForm.is_internal" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400" />
                                        <span :class="replyForm.is_internal ? 'font-semibold text-yellow-700' : 'text-gray-500'">
                                            Internal note (not visible to company)
                                        </span>
                                    </label>
                                </div>

                                <div class="flex gap-2">
                                    <div class="flex-1">
                                        <textarea v-model="replyForm.body" rows="3" required
                                                  :placeholder="replyForm.is_internal ? 'Add an internal note...' : 'Type your reply...'"
                                                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                                  :class="replyForm.is_internal ? 'bg-yellow-50 border-yellow-200' : ''" />

                                        <!-- Attachments preview -->
                                        <div v-if="replyForm.attachments.length" class="mt-1 flex flex-wrap gap-1">
                                            <span v-for="(f, i) in replyForm.attachments" :key="i"
                                                  class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px]">
                                                {{ f.name }}
                                                <button type="button" @click="removeFile(i)" class="text-red-400">&times;</button>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <input ref="fileInput" type="file" multiple @change="addFiles" class="hidden" />
                                        <button type="button" @click="fileInput?.click()"
                                                class="rounded-lg border border-gray-200 p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Attach files">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32"/></svg>
                                        </button>
                                        <button type="submit" :disabled="replyForm.processing || !replyForm.body.trim()"
                                                class="rounded-lg p-2 text-white shadow-sm disabled:opacity-40"
                                                :class="replyForm.is_internal ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-indigo-500 hover:bg-indigo-600'">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Closed Banner -->
                        <div v-else class="border-t border-gray-100 bg-gray-50 px-4 py-3 text-center text-sm text-gray-500">
                            This ticket is closed.
                            <button v-if="isStaff" @click="statusForm.status = 'open'; updateStatus()"
                                    class="ml-2 text-indigo-600 hover:text-indigo-800 font-medium">Reopen</button>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="hidden w-72 flex-shrink-0 lg:block">
                    <div class="sticky top-6 space-y-4">
                        <!-- Ticket Details Card -->
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Details</h3>
                            <dl class="space-y-3 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-400">Created by</dt>
                                    <dd class="font-medium text-gray-900">{{ ticket.creator?.name }}</dd>
                                    <dd class="text-xs text-gray-400">{{ ticket.creator?.email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-400">Category</dt>
                                    <dd class="font-medium text-gray-700">{{ categoryLabels[ticket.category] }}</dd>
                                </div>
                                <div v-if="ticket.company">
                                    <dt class="text-xs text-gray-400">Company</dt>
                                    <dd class="font-medium text-gray-700">{{ ticket.company.name }}</dd>
                                </div>
                                <div v-if="ticket.municipality">
                                    <dt class="text-xs text-gray-400">Municipality</dt>
                                    <dd class="font-medium text-gray-700">{{ ticket.municipality.name }}</dd>
                                </div>
                                <div v-if="ticket.upload">
                                    <dt class="text-xs text-gray-400">Linked Upload</dt>
                                    <dd>
                                        <Link :href="`/uploads/history?search=${ticket.upload.reference}`"
                                              class="font-mono text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ ticket.upload.reference }}
                                        </Link>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-400">Created</dt>
                                    <dd class="text-xs text-gray-600">{{ formatTime(ticket.created_at) }}</dd>
                                </div>
                                <div v-if="ticket.resolved_at">
                                    <dt class="text-xs text-gray-400">Resolved</dt>
                                    <dd class="text-xs text-gray-600">{{ formatTime(ticket.resolved_at) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Staff Actions -->
                        <div v-if="isStaff" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Manage</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">Status</label>
                                    <select v-model="statusForm.status"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm">
                                        <option value="open">Open</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="waiting_on_company">Awaiting Company</option>
                                        <option value="waiting_on_casey">Awaiting Casey</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Assigned To</label>
                                    <select v-model="statusForm.assigned_to"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm">
                                        <option value="">Unassigned</option>
                                        <option v-for="u in staffUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <button @click="updateStatus" :disabled="statusForm.processing"
                                        class="w-full rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-800 disabled:opacity-50">
                                    Update Ticket
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
