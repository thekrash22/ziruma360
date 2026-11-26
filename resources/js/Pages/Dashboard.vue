<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface Stats {
  buildings: number;
  units: number;
  people: number;
  members: number;
}

interface ActiveComplex {
  uuid: string;
  name: string;
  code: string;
  status: string;
  status_label: string;
  is_current: boolean;
}

const props = defineProps<{
  stats: Stats | null;
  needsComplex: boolean;
  complex?: ActiveComplex | null;
}>();

const quickLinks = [
  {
    label: 'Edificios',
    description: 'Torres, pisos y estructura física',
    route: 'buildings.index',
    color: 'bg-brand-500/10 text-brand-500',
  },
  {
    label: 'Unidades',
    description: 'Apartamentos, parqueaderos y depósitos',
    route: 'units.index',
    color: 'bg-success-500/10 text-success-500',
  },
  {
    label: 'Personas',
    description: 'Propietarios, residentes y visitantes',
    route: 'people.index',
    color: 'bg-warning-500/10 text-warning-500',
  },
];
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout>
    <div class="mb-6">
      <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
        Dashboard
      </h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        <template v-if="props.complex">
          Datos del conjunto <span class="font-medium text-gray-700 dark:text-gray-300">{{ props.complex.name }}</span>.
        </template>
        <template v-else>
          Todavía no hay un conjunto activo.
        </template>
      </p>
    </div>

    <!-- Sin conjunto activo: no hay cifras que mostrar -->
    <div
      v-if="props.needsComplex || !props.stats"
      class="rounded-sm border border-stroke bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
        Elige un conjunto para ver sus datos
      </h3>
      <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
        Los edificios, las unidades, las personas y los miembros se cuentan por conjunto.
        Selecciona el conjunto con el que vas a trabajar y aquí verás sus cifras reales.
      </p>
      <Link
        :href="route('complexes.index')"
        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        Elegir conjunto
      </Link>
    </div>

    <div v-else class="grid grid-cols-12 gap-4 md:gap-6">
      <!-- Conteos reales del conjunto activo -->
      <div
        v-for="stat in [
          { label: 'Edificios', value: props.stats.buildings, icon: 'building' },
          { label: 'Unidades', value: props.stats.units, icon: 'door' },
          { label: 'Personas', value: props.stats.people, icon: 'people' },
          { label: 'Miembros activos', value: props.stats.members, icon: 'users' },
        ]"
        :key="stat.label"
        class="col-span-12 sm:col-span-6 xl:col-span-3"
      >
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="text-title-md font-semibold text-gray-800 dark:text-white">
                {{ stat.value }}
              </h4>
              <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ stat.label }}
              </span>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-500/10 text-brand-500">
              <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                <path d="M10 2L5 8H8V14H12V8H15L10 2Z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Accesos rápidos -->
      <div class="col-span-12 xl:col-span-8">
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white">
            Accesos rápidos
          </h3>

          <div class="flex flex-col gap-4">
            <Link
              v-for="link in quickLinks"
              :key="link.route"
              :href="route(link.route)"
              class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-left transition hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700"
            >
              <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="link.color">
                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                  <path d="M10 2L5 8H8V14H12V8H15L10 2Z" />
                </svg>
              </div>
              <div>
                <span class="block text-sm font-medium text-gray-800 dark:text-white">
                  {{ link.label }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ link.description }}
                </span>
              </div>
            </Link>
          </div>
        </div>
      </div>

      <!-- Conjunto activo -->
      <div class="col-span-12 xl:col-span-4">
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white">
            Conjunto activo
          </h3>

          <dl v-if="props.complex" class="space-y-3">
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Nombre</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.complex.name }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Código</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.complex.code }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Estado</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.complex.status_label }}</dd>
            </div>
          </dl>

          <Link
            :href="route('complexes.index')"
            class="mt-6 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cambiar de conjunto
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
