<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], default: '-' },
    hint: { type: String, default: '' },
    // emerald | rose | indigo | slate
    variant: { type: String, default: 'emerald' },
    // enable/disable pulsing border
    pulse: { type: Boolean, default: true },
});

const ringMap = {
    emerald: 'from-emerald-400 via-teal-400 to-cyan-400',
    rose: 'from-rose-400 via-orange-400 to-amber-400',
    indigo: 'from-indigo-400 via-violet-400 to-fuchsia-400',
    slate: 'from-slate-300 via-slate-400 to-slate-300',
};
const iconMap = {
    emerald: 'text-emerald-300 border-emerald-500/30 bg-emerald-500/10',
    rose: 'text-rose-300 border-rose-500/30 bg-rose-500/10',
    indigo: 'text-indigo-300 border-indigo-500/30 bg-indigo-500/10',
    slate: 'text-slate-300 border-white/20 bg-white/10',
};

const ringGrad = computed(() => ringMap[props.variant] || ringMap.emerald);
const iconPill = computed(() => iconMap[props.variant] || iconMap.emerald);
</script>

<template>
    <!-- Outer gradient border (p-[1.5px] makes a crisp border) -->
    <div
        class="group relative rounded-2xl p-[1.5px] transition will-change-transform hover:-translate-y-0.5"
        :class="[
            `bg-gradient-to-br ${'bg-clip-border'} ${'from-transparent'} ${'to-transparent'}`,
            `before:absolute before:inset-0 before:-z-10 before:rounded-2xl before:bg-gradient-to-r ${'before:' + 'from-transparent'} ${'before:' + 'to-transparent'}`,
        ]"
    >
        <!-- Animated gradient ring behind (uses ::after to avoid layout shift) -->
        <div
            class="absolute inset-0 -z-10 rounded-2xl opacity-80"
            :class="[`bg-gradient-to-r ${ringGrad}`, pulse ? 'animate-border-pulse' : '']"
        />

        <!-- Card body -->
        <div
            class="rounded-[14px] border border-slate-200/70 bg-white px-5 py-4 shadow-sm transition-shadow group-hover:shadow-emerald-500/10 sm:px-6 sm:py-5 dark:border-slate-800 dark:bg-slate-900/60"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ title }}</p>
                    <p
                        class="mt-2 truncate text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ value }}
                    </p>
                    <span
                        v-if="hint"
                        class="mt-2 inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-700 dark:text-emerald-300"
                    >
                        {{ hint }}
                    </span>
                </div>

                <!-- Icon bubble slot -->
                <div
                    class="grid h-10 w-10 place-content-center rounded-xl border transition group-hover:scale-105"
                    :class="iconPill"
                >
                    <slot name="icon" />
                </div>
            </div>
        </div>
    </div>
</template>
