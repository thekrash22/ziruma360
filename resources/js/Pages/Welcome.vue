<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion?: string;
    phpVersion?: string;
}>();

const user = (usePage().props.auth as any)?.user ?? null;

interface Feature {
    title: string;
    description: string;
    iconPath: string;
}

const features: Feature[] = [
    {
        title: 'Visitantes y vehículos',
        description:
            'Registrá ingresos, controlá autorizaciones y mantené un historial completo por unidad.',
        iconPath:
            'M12 2.25C6.61522 2.25 2.25 6.61522 2.25 12C2.25 17.3848 6.61522 21.75 12 21.75C17.3848 21.75 21.75 17.3848 21.75 12C21.75 6.61522 17.3848 2.25 12 2.25ZM9 8.25C9 7.42157 9.67157 6.75 10.5 6.75H13.5C14.3284 6.75 15 7.42157 15 8.25C15 8.65482 14.8465 9.02639 14.591 9.3L13.5 10.5V12.75C13.5 13.1642 13.1642 13.5 12.75 13.5H11.25C10.8358 13.5 10.5 13.1642 10.5 12.75V10.5L9.40899 9.3C9.15351 9.02639 9 8.65482 9 8.25Z',
    },
    {
        title: 'Rentas cortas',
        description:
            'Reservas, estancias y huéspedes sincronizados con tus unidades disponibles.',
        iconPath:
            'M7.5 4.5C7.5 3.67157 8.17157 3 9 3H15C15.8284 3 16.5 3.67157 16.5 4.5V5.25H18.75C19.9926 5.25 21 6.25736 21 7.5V18.75C21 19.9926 19.9926 21 18.75 21H5.25C4.00736 21 3 19.9926 3 18.75V7.5C3 6.25736 4.00736 5.25 5.25 5.25H7.5V4.5ZM9 4.5V5.25H15V4.5H9Z',
    },
    {
        title: 'Unidades y propietarios',
        description:
            'Edificios, unidades, propietarios y residentes con permisos y roles finos.',
        iconPath:
            'M3.75 4.5C3.75 4.08579 4.08579 3.75 4.5 3.75H7.5C7.91421 3.75 8.25 4.08579 8.25 4.5V19.5C8.25 19.9142 7.91421 20.25 7.5 20.25H4.5C4.08579 20.25 3.75 19.9142 3.75 19.5V4.5ZM10.5 4.5C10.5 4.08579 10.8358 3.75 11.25 3.75H13.5C13.9142 3.75 14.25 4.08579 14.25 4.5V19.5C14.25 19.9142 13.9142 20.25 13.5 20.25H11.25C10.8358 20.25 10.5 19.9142 10.5 19.5V4.5ZM17.25 4.5C17.25 4.08579 17.5858 3.75 18 3.75H19.5C19.9142 3.75 20.25 4.08579 20.25 4.5V19.5C20.25 19.9142 19.9142 20.25 19.5 20.25H18C17.5858 20.25 17.25 19.9142 17.25 19.5V4.5Z',
    },
];
</script>

<template>
    <Head title="Ziruma360" />

    <div class="min-h-screen bg-gray-50 text-gray-800 dark:bg-gray-950 dark:text-white">
        <!-- Top nav -->
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="flex items-center gap-2">
                    <img
                        src="/images/logo/logo-dark.svg"
                        alt="Ziruma360"
                        class="h-8 dark:hidden"
                    />
                    <img
                        src="/images/logo/logo.svg"
                        alt="Ziruma360"
                        class="hidden h-8 dark:block"
                    />
                </Link>

                <nav v-if="canLogin" class="flex items-center gap-2">
                    <Link
                        v-if="user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600"
                    >
                        Ir al panel
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-500/10 via-transparent to-brand-500/5" />
            <div class="relative mx-auto max-w-6xl px-6 py-20 lg:py-32">
                <div class="max-w-3xl">
                    <span class="inline-block rounded-full bg-brand-500/10 px-4 py-1.5 text-xs font-medium uppercase tracking-wider text-brand-500">
                        Conjunto residencial · Rentas cortas
                    </span>
                    <h1 class="mt-6 text-title-xl font-semibold leading-tight text-gray-900 lg:text-5xl dark:text-white">
                        Una plataforma para tu conjunto residencial.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                        Ziruma360 coordina visitantes, vehículos, unidades y
                        reservas en un solo panel. Diseñado para administradores
                        que quieren operar un conjunto moderno y flexible.
                    </p>

                    <div v-if="!user" class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center rounded-lg bg-brand-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-2"
                        >
                            Empezar gratis
                            <svg
                                class="ms-2 h-4 w-4"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </Link>
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-6 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            Iniciar sesión
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="border-t border-gray-200 bg-white py-20 dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto max-w-6xl px-6">
                <div class="mb-12 max-w-2xl">
                    <h2 class="text-title-sm font-semibold text-gray-900 dark:text-white">
                        Lo que vas a poder hacer
                    </h2>
                    <p class="mt-2 text-base text-gray-600 dark:text-gray-400">
                        Funcionalidades pensadas para el día a día de un conjunto residencial moderno.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-500/10 text-brand-500">
                            <svg
                                class="h-6 w-6"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path :d="feature.iconPath" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ feature.title }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ feature.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="border-t border-gray-200 bg-white py-8 dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-6 text-sm text-gray-500 sm:flex-row dark:text-gray-400">
                <p>© {{ new Date().getFullYear() }} Ziruma360</p>
                <p v-if="laravelVersion || phpVersion">
                    Laravel v{{ laravelVersion }} · PHP v{{ phpVersion }}
                </p>
            </div>
        </footer>
    </div>
</template>
