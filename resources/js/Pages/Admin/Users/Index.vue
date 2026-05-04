<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchFilter from '@/Components/SearchFilter.vue';

defineProps({
    users: Object,
    roles: Array,
});

const search = ref('');
const roleFilter = ref('');

watch([search, roleFilter], () => {
    router.get(
        '/admin/users',
        {
            search: search.value,
            role: roleFilter.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
});

function deleteUser(user) {
    if (confirm('Are you sure you want to delete this user?')) {
        router.delete(`/admin/users/${user.id}`);
    }
}
</script>

<template>
    <AppLayout title="Users Management">
        <template #header>
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Users Management</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 bg-white p-6">
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-lg font-medium">Manage Users</h3>
                        </div>

                        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <SearchFilter v-model="search" placeholder="Search users..." />
                            <select
                                v-model="roleFilter"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Roles</option>
                                <option v-for="role in roles" :key="role" :value="role">
                                    {{ role }}
                                </option>
                            </select>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                        >
                                            User
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                        >
                                            Roles
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                        >
                                            Assignments
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="user in users.data" :key="user.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div
                                                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100"
                                                >
                                                    <span class="font-medium text-indigo-800">
                                                        {{
                                                            user.name
                                                                .split(' ')
                                                                .map((n) => n[0])
                                                                .join('')
                                                                .toUpperCase()
                                                        }}
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ user.name }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ user.email }}
                                                    </div>
                                                    <div class="text-xs text-gray-400">
                                                        {{ user.employee_number }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                v-for="role in user.roles"
                                                :key="role.id"
                                                class="mr-1 inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800"
                                            >
                                                {{ role.name }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm whitespace-nowrap text-gray-500"
                                        >
                                            {{ user.assignments_count }} assignments
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                                            <Link
                                                :href="`/admin/users/${user.id}/edit`"
                                                class="mr-3 text-indigo-600 hover:text-indigo-900"
                                                >Edit</Link
                                            >
                                            <button
                                                @click="deleteUser(user)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <Pagination class="mt-6" :links="users.links" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
