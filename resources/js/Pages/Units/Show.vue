<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Persona {
  uuid: string;
  nombre_completo: string;
  tipo_documento: string;
  numero_documento: string;
  pivot: {
    tipo_relacion: string;
    es_principal: boolean;
  };
}

interface Unidad {
  id: number;
  uuid: string;
  numero: string;
  piso: string | null;
  torre: string | null;
  tipo: string;
  area_m2: number | null;
  habitaciones: number | null;
  banos: number | null;
  status: string;
  status_label: string;
  settings: any;
  residential_complex: {
    uuid: string;
    name: string;
    code: string;
  };
  personas: Persona[];
  created_at: string;
  updated_at: string;
}

interface Props {
  unidad: Unidad;
}

const props = defineProps<Props>();

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    inactive: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    maintenance: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
  };
  return colors[status] || colors.draft;
};

const getRelationLabel = (relacion: string) => {
  const labels: Record<string, string> = {
    propietario: 'Propietario',
    inquilino: 'Inquilino',
    residente: 'Residente',
    responsable: 'Responsable',
  };
  return labels[relacion] || relacion;
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<template>
  <Head title="Unidad" />

  <AppLayout>
    <div class="mb-6">
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a :href="route('units.index')" class="hover:text-gray-700 dark:hover:text-gray-300">Unidades</a>
        <span>/</span>
        <span class="text-gray-800 dark:text-white">{{ unidad.numero }}</span>
      </div>
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="flex items-center gap-3">
          <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
            {{ unidad.numero }}
            <span v-if="unidad.torre" class="text-gray-500">/ {{ unidad.torre }}</span>
          </h2>
          <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(unidad.status)]">
            {{ unidad.status_label }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ unidad.residential_complex?.name }} • {{ unidad.tipo }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a
          :href="route('units.edit', unidad.uuid)"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
          Editar
        </a>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Main Info -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Basic Details -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Información de la Unidad
          </h3>
          <div class="grid grid-cols-2 gap-6">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Conjunto Residencial</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">
                {{ unidad.residential_complex?.name || '-' }}
              </p>
              <p class="text-sm text-gray-500">{{ unidad.residential_complex?.code }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Tipo</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">{{ unidad.tipo }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Torre</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">{{ unidad.torre || '-' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Piso</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">{{ unidad.piso || '-' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Área</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">
                {{ unidad.area_m2 ? `${unidad.area_m2} m²` : '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Habitaciones / Baños</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">
                {{ unidad.habitaciones || '-' }} / {{ unidad.banos || '-' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Personas -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Personas Relacionadas
          </h3>
          <div v-if="props.unidad.personas && props.unidad.personas.length > 0" class="space-y-3">
            <div
              v-for="persona in props.unidad.personas"
              :key="persona.uuid"
              class="flex items-center justify-between rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500 text-sm font-medium text-white">
                  {{ persona.nombre_completo.charAt(0) }}
                </div>
                <div>
                  <p class="font-medium text-gray-800 dark:text-white">
                    {{ persona.nombre_completo }}
                    <span v-if="persona.pivot?.es_principal" class="ml-1 text-xs text-brand-500">(Principal)</span>
                  </p>
                  <p class="text-sm text-gray-500">
                    {{ persona.tipo_documento }} {{ persona.numero_documento }}
                  </p>
                </div>
              </div>
              <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ getRelationLabel(persona.pivot?.tipo_relacion || '') }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            No hay personas relacionadas con esta unidad.
          </p>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Timestamps -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Historial
          </h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Fecha de Creación</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(unidad.created_at) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Última Actualización</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(unidad.updated_at) }}</p>
            </div>
          </div>
        </div>

        <!-- UUID -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Identificador
          </h3>
          <p class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ unidad.uuid }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
