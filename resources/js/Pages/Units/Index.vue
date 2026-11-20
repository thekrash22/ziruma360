<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface BuildingSummary {
  uuid: string;
  name: string;
  code: string;
}

interface FloorSummary {
  uuid: string;
  floor_number: number;
  name: string | null;
  display_name: string;
}

interface Unit {
  uuid: string;
  number: string;
  unit_type: string;
  unit_type_label: string;
  status: string;
  status_label: string;
  internal_code: string | null;
  area_m2: string | null;
  building: BuildingSummary | null;
  building_floor: FloorSummary | null;
  created_at: string | null;
}

interface PageProps {
  units: {
    data: Unit[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: any[];
  };
  filters: {
    search?: string;
    status?: string;
    unit_type?: string;
  };
  unitTypes: Array<{
    value: string;
    label: string;
  }>;
  statuses: Array<{
    value: string;
    label: string;
  }>;
  canDelete: boolean;
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const search = ref(props.filters.search || '');
const showFilters = ref(false);

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    inactive: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    under_review: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
  };
  return colors[status] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const navigateTo = (params: Record<string, string>) => {
  const query: Record<string, string> = {};

  if (params.search) query.search = params.search;
  if (params.status) query.status = params.status;
  if (params.unit_type) query.unit_type = params.unit_type;

  router.get(route('units.index', query), {}, { preserveState: true, replace: true });
};

const handleSearch = () => {
  navigateTo({
    search: search.value,
    status: props.filters.status || '',
    unit_type: props.filters.unit_type || '',
  });
};

const clearFilters = () => {
  search.value = '';
  router.get(route('units.index'), {}, { preserveState: true, replace: true });
};

const deactivate = (unit: Unit) => {
  if (!confirm(`¿Desactivar la unidad ${unit.number}? La unidad se conserva con su historial.`)) {
    return;
  }

  router.delete(route('units.destroy', unit.uuid), {
    preserveScroll: true,
    onSuccess: () => {
      // La recarga de la tabla la hace Inertia con la respuesta del servidor.
    },
  });
};
</script>

<template>
  <Head title="Unidades" />

  <AppLayout>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
          Unidades
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Gestiona las unidades del conjunto: numero, tipo, estado, edificio y piso.
        </p>
      </div>
      <a
        :href="route('units.create')"
        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nueva Unidad
      </a>
    </div>

    <!-- Flash Messages -->
    <div v-if="props.flash?.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
      {{ props.flash.success }}
    </div>
    <div v-if="props.flash?.error" class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
      {{ props.flash.error }}
    </div>

    <!-- Search & Filters -->
    <div class="mb-6 rounded-sm border border-stroke bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <div class="flex-1">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Buscar por numero de unidad..."
              class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              @keyup.enter="handleSearch"
            />
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <button
          @click="showFilters = !showFilters"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          Filtros
        </button>

        <button
          v-if="props.filters.search || props.filters.status || props.filters.unit_type"
          @click="clearFilters"
          class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
        >
          Limpiar
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Expanded Filters -->
      <div v-if="showFilters" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
          <select
            :value="props.filters.unit_type"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            @change="navigateTo({ search: search, status: props.filters.status || '', unit_type: ($event.target as HTMLSelectElement).value })"
          >
            <option value="">Todos los tipos</option>
            <option v-for="type in props.unitTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
          <select
            :value="props.filters.status"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            @change="navigateTo({ search: search, status: ($event.target as HTMLSelectElement).value, unit_type: props.filters.unit_type || '' })"
          >
            <option value="">Todos los estados</option>
            <option v-for="status in props.statuses" :key="status.value" :value="status.value">
              {{ status.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Unidad
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Tipo
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Edificio
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Piso
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
            <tr v-for="unit in props.units.data" :key="unit.uuid" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="whitespace-nowrap px-4 py-3">
                <div>
                  <span class="font-medium text-gray-800 dark:text-white">{{ unit.number }}</span>
                  <span v-if="unit.internal_code" class="ml-2 text-sm text-gray-500">{{ unit.internal_code }}</span>
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ unit.unit_type_label }}
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ unit.building?.name || '-' }}
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ unit.building_floor?.display_name || '-' }}
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(unit.status)]">
                  {{ unit.status_label }}
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="route('units.show', unit.uuid)"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    title="Ver detalles"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </a>
                  <a
                    :href="route('units.edit', unit.uuid)"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    title="Editar"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </a>
                  <button
                    v-if="props.canDelete"
                    class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30"
                    title="Desactivar"
                    @click="deactivate(unit)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a2 2 0 00-1-1h-4a2 2 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="props.units.data.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No se encontraron unidades.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="props.units.last_page > 1" class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Mostrando {{ props.units.from }} a {{ props.units.to }} de {{ props.units.total }} resultados
        </div>
        <div class="flex gap-1">
          <a
            v-for="(link, index) in props.units.links"
            :key="index"
            :href="link.url || '#'"
            :class="[
              'rounded-lg px-3 py-1.5 text-sm',
              link.active
                ? 'bg-brand-500 text-white'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
              !link.url ? 'opacity-50 pointer-events-none' : ''
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
