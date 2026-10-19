<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white lg:flex-row dark:bg-gray-950">
        <!-- Brand panel (hidden on small screens) -->
        <aside
            class="hidden flex-col justify-between bg-brand-500 p-12 lg:flex lg:w-1/2 xl:w-2/5"
        >
            <Link href="/" class="inline-flex items-center">
                <img
                    src="/images/logo/logo.svg"
                    alt="Ziruma360"
                    class="h-9 brightness-0 invert"
                />
            </Link>

            <div class="max-w-md text-white">
                <h2 class="text-title-lg font-semibold leading-tight">
                    Administra tu conjunto residencial en un solo lugar.
                </h2>
                <p class="mt-4 text-base text-white/80">
                    Visitantes, vehículos, rentas cortas y reservas — todo
                    coordinado para tu operación diaria.
                </p>
            </div>

            <p class="text-sm text-white/70">
                © {{ new Date().getFullYear() }} Ziruma360
            </p>
        </aside>

        <!-- Form panel -->
        <main
            class="flex flex-1 items-center justify-center bg-gray-50 px-6 py-12 sm:px-12 lg:bg-white dark:bg-gray-950 lg:dark:bg-gray-900"
        >
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <Link href="/">
                        <img
                            src="/images/logo/logo-dark.svg"
                            alt="Ziruma360"
                            class="h-9 dark:hidden"
                        />
                        <img
                            src="/images/logo/logo.svg"
                            alt="Ziruma360"
                            class="hidden h-9 dark:block"
                        />
                    </Link>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
