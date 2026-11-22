<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Complex {
  uuid: string;
  name: string;
  code: string;
  status: string;
  status_label: string;
  is_current: boolean;
  is_member: boolean;
}

interface PageProps {
  complexes: Complex[];
  activeComplexUuid: string | null;
  selectionRequired: boolean;
  canSelect: boolean;
  canCreate: boolean;
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const page = usePage();

const selected = ref(props.activeComplexUuid ?? '');

// Solo se puede elegir un conjunto del que el actor es miembro activo.
const selectable = computed(() => props.complexes.filter((complex) => complex.is_member));

// El selector tiene sentido cuando hay mas de un conjunto elegible.
const canSwitch = computed(() => props.canSelect && selectable.value.length > 1);

// Si el conjunto activo no es elegible, el selector arranca en el primero.
watch(
  () => selectable.value.map((complex) => complex.uuid),
  (uuids) => {
    if (!uuids.includes(selected.value)) {
      selected.value = uuids[0] ?? '';
    }
  },
  { immediate: true }
);

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    suspended: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    archived: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
  };
  return colors[status] || 'bg-gray-100 text-gray-700';
};

const selectComplex = (uuid: string) => {
  router.post(
    route('complexes.select', uuid),
    { redirect: page.url },
    { preserveScroll: true }
  );
};
</script>

<template>
  <Head title="Conjuntos" />

  <AppLayout>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
          Conjuntos
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Elige el conjunto con el que vas a trabajar y gestiona sus conjuntos.
        </p>
      </div>
      <a
        v-if="props.canCreate"
        :href="route('complexes.create')"
        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Conjunto
      </a>
    </div>

    <!-- Flash Messages -->
    <div v-if="props.flash?.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
      {{ props.flash.success }}
    </div>
    <div v-if="props.flash?.error" class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
      {{ props.flash.error }}
    </div>

    <!-- Aviso de seleccion pendiente -->
    <div
      v-if="props.selectionRequired"
      class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 dark:border-yellow-900/50 dark:bg-yellow-900/20 dark:text-yellow-300"
    >
      Tienes más de un conjunto. Elige con cuál quieres trabajar antes de continuar.
    </div>

    <!-- Selector de conjunto activo -->
    <div
      v-if="canSwitch"
      class="mb-6 rounded-sm border border-stroke bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex-1">
          <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Conjunto activo
          </label>
          <select
            v-model="selected"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          >
            <option v-for="complex in selectable" :key="complex.uuid" :value="complex.uuid">
              {{ complex.name }}
            </option>
          </select>
        </div>
        <button
          type="button"
          :disabled="!selected"
          class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
          @click="selectComplex(selected)"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Usar este conjunto
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Conjunto
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Estado
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr
              v-for="complex in props.complexes"
              :key="complex.uuid"
              class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
            >
              <td class="whitespace-nowrap px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="font-medium text-gray-800 dark:text-white">{{ complex.name }}</span>
                  <span class="text-sm text-gray-500">#{{ complex.code }}</span>
                  <span
                    v-if="complex.is_current"
                    class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300"
                  >
                    Activo
                  </span>
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(complex.status)]">
                  {{ complex.status_label }}
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="route('complexes.show', complex.uuid)"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    title="Ver detalles"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </a>
                  <button
                    v-if="!complex.is_current && complex.is_member"
                    type="button"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    title="Usar este conjunto"
                    @click="selectComplex(complex.uuid)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="props.complexes.length === 0">
              <td colspan="3" class="px-4 py-8 text-center">
                <p class="text-sm font-medium text-gray-800 dark:text-white">
                  Todavía no tienes conjuntos.
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  {{
                    props.canCreate
                      ? 'Crea el primer conjunto para empezar a gestionarlo.'
                      : 'Un administrador de plataforma debe invitarte a un conjunto.'
                  }}
                </p>
                <a
                  v-if="props.canCreate"
                  :href="route('complexes.create')"
                  class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Nuevo Conjunto
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
