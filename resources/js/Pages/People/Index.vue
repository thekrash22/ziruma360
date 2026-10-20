<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface TipoDocumento {
  value: string;
  label: string;
}

interface Persona {
  id: number;
  uuid: string;
  nombre: string;
  apellido: string;
  nombre_completo: string;
  tipo_documento: string;
  tipo_documento_label: string;
  numero_documento: string;
  email: string | null;
  telefono: string | null;
  has_user_account: boolean;
  created_at: string;
}

interface PageProps {
  personas: {
    data: Persona[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: any[];
  };
  documentTypes: TipoDocumento[];
  filters: {
    search?: string;
    tipo_documento?: string;
    has_user?: string;
    per_page?: string;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const search = ref(props.filters.search || '');

const handleDelete = (uuid: string, name: string) => {
  if (confirm(`¿Estás seguro de eliminar a ${name}?`)) {
    window.location.href = route('people.destroy', uuid);
  }
};

const navigateTo = (name: string, params?: Record<string, any>) => {
  window.location.href = route(name, params);
};

const handleSearch = () => {
  const params: Record<string, string> = {};
  if (search.value) params.search = search.value;
  if (props.filters.tipo_documento) params.tipo_documento = props.filters.tipo_documento;
  if (props.filters.has_user) params.has_user = props.filters.has_user;
  
  navigateTo('people.index', params);
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
  <Head title="Personas" />

  <AppLayout>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
          Personas
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Gestiona propietarios, residentes y otras personas del sistema.
        </p>
      </div>
      <a
        :href="route('people.create')"
        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nueva Persona
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
              placeholder="Buscar por nombre o número de documento..."
              class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              @keyup.enter="handleSearch"
            />
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <button
          v-if="props.filters.search || props.filters.tipo_documento || props.filters.has_user"
          @click="navigateTo('people.index')"
          class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
        >
          Limpiar
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
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
                Nombre
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Documento
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Contacto
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Cuenta
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
            <tr v-for="persona in props.personas.data" :key="persona.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="whitespace-nowrap px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500 text-sm font-medium text-white">
                    {{ persona.nombre.charAt(0) }}{{ persona.apellido.charAt(0) }}
                  </div>
                  <span class="font-medium text-gray-800 dark:text-white">
                    {{ persona.nombre_completo }}
                  </span>
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <div>
                  <p class="text-sm text-gray-800 dark:text-white">{{ persona.tipo_documento_label }}</p>
                  <p class="text-sm text-gray-500">{{ persona.numero_documento }}</p>
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                <div>
                  <p v-if="persona.email">{{ persona.email }}</p>
                  <p v-if="persona.telefono" class="text-gray-500">{{ persona.telefono }}</p>
                  <span v-if="!persona.email && !persona.telefono">-</span>
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <span
                  :class="[
                    'rounded-full px-2.5 py-0.5 text-xs font-medium',
                    persona.has_user_account
                      ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                      : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                  ]"
                >
                  {{ persona.has_user_account ? 'Vinculada' : 'Sin cuenta' }}
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ formatDate(persona.created_at) }}
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="route('people.show', persona.uuid)"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    title="Ver"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </a>
                  <a
                    :href="route('people.edit', persona.uuid)"
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
                    @click="handleDelete(persona.uuid, persona.nombre_completo)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="props.personas.data.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No se encontraron personas.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="props.personas.last_page > 1" class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Mostrando {{ props.personas.from }} a {{ props.personas.to }} de {{ props.personas.total }} resultados
        </div>
        <div class="flex gap-1">
          <a
            v-for="(link, index) in props.personas.links"
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
