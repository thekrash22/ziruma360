<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout>
        <Head title="Forgot Password" />

        <div class="mb-6">
            <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white">
                Recuperar contraseña
            </h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Ingresá tu email y te enviaremos un link para que elijas una
                nueva contraseña.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-lg bg-success-500/10 px-4 py-3 text-sm font-medium text-success-500"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <PrimaryButton
                class="w-full"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                Enviar link de recuperación
            </PrimaryButton>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                <Link
                    :href="route('login')"
                    class="font-medium text-brand-500 hover:text-brand-600"
                >
                    Volver a iniciar sesión
                </Link>
            </p>
        </form>
    </AuthLayout>
</template>
