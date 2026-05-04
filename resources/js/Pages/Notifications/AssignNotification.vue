<template>
    <Transition name="slide-fade">
        <div v-if="visible" class="fixed bottom-4 right-4 z-50">
            <div class="bg-white rounded-lg shadow-lg border border-blue-200 p-4 max-w-sm">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-gray-900">New Assignment</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ message }}
                        </p>
                        <div class="mt-2 text-xs text-gray-500">
                            {{ formatTime(createdAt) }}
                        </div>
                        <button
                            @click="viewDetails"
                            class="mt-2 text-xs text-blue-600 hover:text-blue-800 font-medium"
                        >
                            View Details →
                        </button>
                    </div>
                    <button
                        @click="dismiss"
                        class="ml-4 text-gray-400 hover:text-gray-500"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    assignment: Object,
});

const visible = ref(false);
const message = ref('');
const createdAt = ref(null);

const formatTime = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
};

const dismiss = () => {
    visible.value = false;
};

const viewDetails = () => {
    if (props.assignment?.municipality_id && props.assignment?.deadline_date) {
        router.visit(`/deadlines/municipalities?municipality_id=${props.assignment.municipality_id}&date=${props.assignment.deadline_date}`);
    }
    dismiss();
};

// Listen for assignment events
onMounted(() => {
    window.addEventListener('assignment-created', handleAssignmentEvent);

    // Also listen for Echo events if available
    if (window.Echo) {
        window.Echo.channel('assignments')
            .listen('.assignment.created', (event) => {
                showNotification(event.data);
            });
    }
});

onUnmounted(() => {
    window.removeEventListener('assignment-created', handleAssignmentEvent);
});

const handleAssignmentEvent = (event) => {
    showNotification(event.detail);
};

const showNotification = (data) => {
    message.value = `New assignment for ${data.user_name || 'user'}`;
    createdAt.value = data.created_at || new Date().toISOString();
    visible.value = true;

    // Auto-dismiss after 10 seconds
    setTimeout(() => {
        if (visible.value) {
            dismiss();
        }
    }, 10000);
};

// Expose methods
defineExpose({
    showNotification,
    dismiss
});
</script>

<style scoped src="@/../css/Centralized/Pages/Notifications/AssignNotification.css"></style>

