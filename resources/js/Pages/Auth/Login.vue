<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { onMounted, onUnmounted } from 'vue';

const page = usePage();

const props = defineProps({
    caseySsoBlocked: {
        type: Boolean,
        default: false,
    },
    caseySsoBlockedReason: {
        type: String,
        default: null,
    },
    caseySignedOut: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    employee_number: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

// Poll the SSO microservice for a new session. When CAPS logs in,
// the session appears and we reload — the server-side create() method
// will see the session and redirect to /auth/casey-sso automatically.
let pollTimer = null;

const startLoginPoll = () => {
    const sso = page.props.sso;
    if (!sso?.enabled || !sso?.serviceUrl) return;

    pollTimer = setInterval(async () => {
        try {
            const res = await fetch(`${sso.serviceUrl}/sessions`, {
                headers: { 'X-SSO-Key': sso.apiSecret || '' },
            });
            if (!res.ok) return;
            const data = await res.json();

            // If any CAPS session exists with a real token, reload the page
            const capsSession = (data.sessions || []).find(
                s => s.source === 'caps' && s.token && !s.token.startsWith('local-session-')
            );
            if (capsSession) {
                clearInterval(pollTimer);
                pollTimer = null;
                window.location.reload();
            }
        } catch {
            // Microservice down — skip
        }
    }, 5000); // Check every 5 seconds on the login page
};

onMounted(() => { startLoginPoll(); });
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });
</script>

<template>
    <GuestLayout>
        <Head title="Sign in" />

        <!-- Green confirmation after logout -->
        <div
            v-if="caseySignedOut"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"
            role="status"
        >
            You have been signed out of CAPS and the Submission Tracker.
        </div>

        <!-- Info banner when SSO is paused — form stays usable -->
        <div
            v-else-if="caseySsoBlocked"
            class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800"
            role="status"
        >
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ caseySsoBlockedReason || 'Auto sign-on via CAPS is paused. You can sign in manually below.' }}</span>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Employee Number -->
            <div>
                <label class="block text-sm font-medium text-slate-700">Employee Number</label>
                <input
                    v-model="form.employee_number"
                    type="text"
                    required
                    autofocus
                    class="mt-1 w-full rounded-xl border px-3 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500"
                />
                <div v-if="form.errors.employee_number" class="mt-1 text-xs text-red-600">
                    {{ form.errors.employee_number }}
                </div>
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-slate-700">Password</label>
                <input
                    v-model="form.password"
                    type="password"
                    required
                    class="mt-1 w-full rounded-xl border px-3 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500"
                />
                <div v-if="form.errors.password" class="mt-1 text-xs text-red-600">
                    {{ form.errors.password }}
                </div>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        v-model="form.remember"
                        class="rounded border-slate-300"
                    />
                    Remember me
                </label>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-3.5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="form.processing"
            >
                <span v-if="form.processing">Signing in...</span>
                <span v-else>Sign in</span>
            </button>
        </form>
    </GuestLayout>
</template>
