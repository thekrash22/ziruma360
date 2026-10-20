<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ResidentialComplex {
  id: number;
  uuid: string;
  name: string;
  code: string;
}

interface Unidad {
  id: number;
  uuid: string;
  numero: string;
  piso: string | null;
  torre: string | null;
  tipo: string;
  status: string;
  status_label: string;
  residential_complex: {
    uuid: string;
    name: string;
    code: string;
  };
  created_at: string;
}

interface PageProps {
  unidades: {
    data: Unidad[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: any[];
  };
  complexes: ResidentialComplex[];
  filters: {
    search?: string;
    status?: string;
    torre?: string;
    residential_complex_id?: string;
    per_page?: string;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const search = ref(props.filters.search || '');
const showFilters = ref(false);

const handleDelete = (uuid: string, numero: string) => {
  if (confirm(`¿Estás seguro de eliminar la unidad ${numero}?`)) {
    window.location.href = route('units.destroy', uuid);
  }
};

const navigateTo = (name: string, params?: Record<string, any>) => {
  window.location.href = route(name, params);
};

// Status options
const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'draft', label: 'Borrador' },
  { value: 'active', label: 'Activo' },
  { value: 'inactive', label: 'Inactivo' },
  { value: 'maintenance', label: 'En mantenimiento' },
];

// Status badge colors
const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    inactive: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    maintenance: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
  };
  return colors[status] || colors.draft;
};

const handleSearch = () => {
  const params: Record<string, string> = {};
  if (search.value) params.search = search.value;
  if (props.filters.status) params.status = props.filters.status;
  if (props.filters.residential_complex_id) params.residential_complex_id = props.filters.residential_complex_id;
  if (props.filters.torre) params.torre = props.filters.torre;
  
  navigateTo('units.index', params);
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
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
          Gestiona las unidades de los edificios y conjuntos residenciales.
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
              placeholder="Buscar por número de unidad..."
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
          v-if="props.filters.search || props.filters.status || props.filters.residential_complex_id"
          @click="navigateTo('units.index')"
          class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
        >
          Limpiar
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Expanded Filters -->
      <div v-if="showFilters" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Conjunto</label>
          <select
            :value="props.filters.residential_complex_id"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            @change="navigateTo('units.index', { ...props.filters, residential_complex_id: ($event.target as HTMLSelectElement).value })"
          >
            <option value="">Todos los conjuntos</option>
            <option v-for="complex in props.complexes" :key="complex.id" :value="complex.id">
              {{ complex.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
          <select
            :value="props.filters.status"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            @change="navigateTo('units.index', { ...props.filters, status: ($event.target as HTMLSelectElement).value })"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Torre</label>
          <input
            :value="props.filters.torre"
            type="text"
            placeholder="Ej: Torre A"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            @change="navigateTo('units.index', { ...props.filters, torre: ($event.target as HTMLInputElement).value })"
          />
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
                Conjunto
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Tipo
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Estado
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Fecha Creación
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr v-for="unidad in props.unidades.data" :key="unidad.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="whitespace-nowrap px-4 py-3">
                <div>
                  <span class="font-medium text-gray-800 dark:text-white">{{ unidad.numero }}</span>
                  <span v-if="unidad.torre" class="ml-1 text-sm text-gray-500">/ {{ unidad.torre }}</span>
                  <span v-if="unidad.piso" class="ml-1 text-sm text-gray-500">- Piso {{ unidad.piso }}</span>
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ unidad.residential_complex?.name || '-' }}
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ unidad.tipo }}
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(unidad.status)]">
                  {{ unidad.status_label }}
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ formatDate(unidad.created_at) }}
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="route('units.show', unidad.uuid)"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    title="Ver"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </a>
                  <a
                    :href="route('units.edit', unidad.uuid)"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    title="Editar"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </a>
                  <button
                    class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30"
                    title="Eliminar"
                    @click="handleDelete(unidad.uuid, unidad.numero)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="props.unidades.data.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No se encontraron unidades.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="props.unidades.last_page > 1" class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Mostrando {{ props.unidades.from }} a {{ props.unidades.to }} de {{ props.unidades.total }} resultados
        </div>
        <div class="flex gap-1">
          <a
            v-for="(link, index) in props.unidades.links"
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
