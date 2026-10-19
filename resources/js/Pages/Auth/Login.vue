<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Log in" />

        <div class="mb-6">
            <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white">
                Iniciar sesión
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Ingresá con tu cuenta de Ziruma360.
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

            <div>
                <InputLabel for="password" value="Contraseña" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                        Recordarme
                    </span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-brand-500 hover:text-brand-600"
                >
                    ¿Olvidaste tu contraseña?
                </Link>
            </div>

            <PrimaryButton
                class="w-full"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                Iniciar sesión
            </PrimaryButton>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                ¿No tenés cuenta?
                <Link
                    :href="route('register')"
                    class="font-medium text-brand-500 hover:text-brand-600"
                >
                    Registrarme
                </Link>
            </p>
        </form>
    </AuthLayout>
</template>
