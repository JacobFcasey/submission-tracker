<script setup></script>
<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <img
                    :src="'/images/casey_logo.png'"
                    alt="Casey & Associates"
                    class="mx-auto h-12 w-auto"
                />
                <h1 class="mt-3 text-lg font-semibold">Casey & Associates</h1>
                <p class="text-xs text-slate-500">Premium Submissions Platform</p>
            </div>

            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <slot />
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">
                © {{ new Date().getFullYear() }} Casey & Associates. All rights reserved.
            </p>
        </div>
    </div>
</template>
