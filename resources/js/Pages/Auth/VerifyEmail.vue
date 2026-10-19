<script setup lang="ts">
import { computed } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <AuthLayout>
        <Head title="Email Verification" />

        <div class="mb-6">
            <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white">
                Verificá tu email
            </h1>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <p>
                Antes de seguir, abrí el enlace que te enviamos por email para
                verificar tu dirección. Si no lo recibiste, podemos reenviarlo.
            </p>

            <div
                class="mt-3 font-medium text-success-500"
                v-if="verificationLinkSent"
            >
                Te enviamos un nuevo enlace de verificación.
            </div>
        </div>

        <form @submit.prevent="submit" class="mt-6 space-y-4">
            <PrimaryButton
                class="w-full"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                Reenviar email de verificación
            </PrimaryButton>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="block w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                Cerrar sesión
            </Link>
        </form>
    </AuthLayout>
</template>
