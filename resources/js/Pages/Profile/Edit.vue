<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Profile" />

    <AppLayout>
        <div class="mb-6">
            <h2 class="text-title-sm font-semibold text-gray-800 dark:text-white">
                Profile
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage your account information, password and session.
            </p>
        </div>

        <div class="flex flex-col gap-6">
            <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <UpdatePasswordForm />
            </div>

            <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <DeleteUserForm />
            </div>
        </div>
    </AppLayout>
</template>
