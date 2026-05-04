<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import {
    format,
    startOfMonth,
    endOfMonth,
    eachDayOfInterval,
    isSameMonth,
    isSameDay,
} from 'date-fns';
import { route } from 'ziggy-js';

const props = defineProps({
    municipalities: Array,
});

const currentMonth = ref(new Date());
const selectedDate = ref(null);
const selectedMunicipalityId = ref('');
const notes = ref('');

const days = computed(() => {
    const start = startOfMonth(currentMonth.value);
    const end = endOfMonth(currentMonth.value);
    const startDate = startOfMonth(start);
    const endDate = endOfMonth(end);

    return eachDayOfInterval({ start: startDate, end: endDate });
});

const formattedDays = computed(() => {
    return days.value.map((date) => {
        const day = format(date, 'd');
        return { date, day };
    });
});

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const previousMonth = () => {
    currentMonth.value = new Date(
        currentMonth.value.getFullYear(),
        currentMonth.value.getMonth() - 1,
    );
};

const nextMonth = () => {
    currentMonth.value = new Date(
        currentMonth.value.getFullYear(),
        currentMonth.value.getMonth() + 1,
    );
};

const selectDate = (date) => {
    selectedDate.value = date;
};

const getDeadlinesForDate = (date) => {
    const dateString = format(date, 'yyyy-MM-dd');
    const deadlines = [];
    props.municipalities.forEach((municipality) => {
        const deadline = municipality.deadlines.find(
            (d) => format(new Date(d.deadline_date), 'yyyy-MM-dd') === dateString,
        );
        if (deadline) {
            deadlines.push({
                ...deadline,
                municipality: municipality.name,
                municipality_id: municipality.id,
            });
        }
    });
    return deadlines;
};

const createDeadline = () => {
    if (!selectedDate.value || !selectedMunicipalityId.value) {
        alert('Please select a date and municipality.');
        return;
    }
    router.post(
        route('deadlines.municipalities.store'),
        {
            municipality_id: selectedMunicipalityId.value,
            deadline_date: format(selectedDate.value, 'yyyy-MM-dd'),
            notes: notes.value,
        },
        {
            onSuccess: () => {
                selectedDate.value = null;
                selectedMunicipalityId.value = '';
                notes.value = '';
            },
        },
    );
};

const deleteDeadline = (id) => {
    if (confirm('Are you sure you want to delete this deadline?')) {
        router.delete(route('deadlines.municipalities.destroy', id), {
            onSuccess: () => {
                // Refresh the page or update props
                router.reload();
            },
        });
    }
};

// Check if a date has any deadlines
const hasDeadlines = (date) => {
    return getDeadlinesForDate(date).length > 0;
};
</script>

<template>
    <AppLayout title="Municipality Deadlines">
        <div class="p-6">
            <h1 class="mb-6 text-2xl font-bold">Municipality Deadlines</h1>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow md:col-span-2">
                    <div class="mb-4 flex items-center justify-between">
                        <button @click="previousMonth" class="rounded-full p-2 hover:bg-gray-100">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                        <h2 class="text-lg font-semibold">
                            {{ format(currentMonth, 'MMMM yyyy') }}
                        </h2>
                        <button @click="nextMonth" class="rounded-full p-2 hover:bg-gray-100">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 text-center text-sm font-semibold text-gray-500">
                        <div v-for="day in weekDays" :key="day">{{ day }}</div>
                    </div>

                    <div class="mt-2 grid grid-cols-7">
                        <div
                            v-for="day in formattedDays"
                            :key="day.date"
                            class="relative cursor-pointer p-2 text-center"
                            :class="{
                                'text-gray-400': !isSameMonth(day.date, currentMonth),
                                'text-black': isSameMonth(day.date, currentMonth),
                                'rounded-full bg-emerald-200': isSameDay(day.date, new Date()),
                                'rounded-full hover:bg-gray-200': isSameMonth(
                                    day.date,
                                    currentMonth,
                                ),
                            }"
                            @click="selectDate(day.date)"
                        >
                            <div class="relative flex h-12 w-full items-center justify-center">
                                <span class="text-sm font-medium">{{ day.day }}</span>
                                <div
                                    v-if="hasDeadlines(day.date)"
                                    class="absolute right-1 bottom-1 h-2 w-2 rounded-full bg-red-500"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow">
                    <h2 class="mb-4 text-xl font-bold">
                        {{
                            selectedDate
                                ? 'Add Deadline for ' + format(selectedDate, 'PPP')
                                : 'Select a date'
                        }}
                    </h2>

                    <div v-if="selectedDate">
                        <form @submit.prevent="createDeadline">
                            <div class="mb-4">
                                <label
                                    for="municipality"
                                    class="block text-sm font-medium text-gray-700"
                                    >Municipality</label
                                >
                                <select
                                    id="municipality"
                                    v-model="selectedMunicipalityId"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                >
                                    <option value="" disabled>Select a municipality</option>
                                    <option
                                        v-for="municipality in municipalities"
                                        :key="municipality.id"
                                        :value="municipality.id"
                                    >
                                        {{ municipality.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="notes" class="block text-sm font-medium text-gray-700"
                                    >Notes (optional)</label
                                >
                                <textarea
                                    id="notes"
                                    v-model="notes"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                ></textarea>
                            </div>
                            <button
                                type="submit"
                                class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                            >
                                Create Deadline
                            </button>
                        </form>
                    </div>

                    <div v-else>
                        <p class="text-gray-500">
                            Click on a date in the calendar to add a new deadline.
                        </p>
                    </div>

                    <div
                        v-if="selectedDate && getDeadlinesForDate(selectedDate).length > 0"
                        class="mt-6"
                    >
                        <h3 class="mb-2 text-lg font-bold">Deadlines on this date:</h3>
                        <ul>
                            <li
                                v-for="deadline in getDeadlinesForDate(selectedDate)"
                                :key="deadline.id"
                                class="flex items-center justify-between rounded-md p-2 hover:bg-gray-100"
                            >
                                <div class="flex-1">
                                    <span class="font-semibold">{{ deadline.municipality }}</span>
                                    <p v-if="deadline.notes" class="text-sm text-gray-500">
                                        {{ deadline.notes }}
                                    </p>
                                </div>
                                <button
                                    @click="deleteDeadline(deadline.id)"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 112 0v6a1 1 0 11-2 0V8z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
