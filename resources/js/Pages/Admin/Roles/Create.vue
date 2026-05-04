<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    permissions: Object,
});

const form = useForm({
    name: '',
    permissions: [],
});

function submit() {
    form.post('/admin/roles');
}
</script>

<template>
    <AppLayout title="Create Role">
        <template #header>
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Create New Role</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 bg-white p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <div>
                                    <label
                                        for="name"
                                        class="block text-sm font-medium text-gray-700"
                                        >Role Name</label
                                    >
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        id="name"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.name }}
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-3 block text-sm font-medium text-gray-700"
                                        >Permissions</label
                                    >
                                    <div class="space-y-4">
                                        <div
                                            v-for="(groupPermissions, groupName) in permissions"
                                            :key="groupName"
                                            class="rounded-lg border p-4"
                                        >
                                            <h4 class="mb-2 font-medium text-gray-900 capitalize">
                                                {{ groupName }}
                                            </h4>
                                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                                <label
                                                    v-for="permission in groupPermissions"
                                                    :key="permission.id"
                                                    class="flex items-center space-x-2"
                                                >
                                                    <input
                                                        v-model="form.permissions"
                                                        :value="permission.name"
                                                        type="checkbox"
                                                        class="rounded border-gray-300"
                                                    />
                                                    <span class="text-sm text-gray-700">{{
                                                        permission.name
                                                    }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="form.errors.permissions"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.permissions }}
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <Link
                                        href="/admin/roles"
                                        class="rounded-md bg-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-400"
                                    >
                                        Cancel
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        Create Role
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
