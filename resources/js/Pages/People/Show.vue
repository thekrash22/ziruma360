<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Unidad {
  uuid: string;
  numero: string;
  tipo: string;
  pivot: {
    tipo_relacion: string;
    es_principal: boolean;
    fecha_inicio: string | null;
    fecha_fin: string | null;
  };
}

interface User {
  id: number;
  name: string;
  email: string;
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
  telefono_alternativo: string | null;
  has_user_account: boolean;
  user: User | null;
  notas: string | null;
  unidades: Unidad[];
  created_at: string;
  updated_at: string;
}

interface Props {
  persona: Persona;
}

const props = defineProps<Props>();

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
  <Head title="Persona" />

  <AppLayout>
    <div class="mb-6">
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a :href="route('people.index')" class="hover:text-gray-700 dark:hover:text-gray-300">Personas</a>
        <span>/</span>
        <span class="text-gray-800 dark:text-white">{{ persona.nombre_completo }}</span>
      </div>
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-4">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-500 text-xl font-medium text-white">
          {{ persona.nombre.charAt(0) }}{{ persona.apellido.charAt(0) }}
        </div>
        <div>
          <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
            {{ persona.nombre_completo }}
          </h2>
          <p class="text-sm text-gray-500">
            {{ persona.tipo_documento_label }} {{ persona.numero_documento }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <a
          :href="route('people.edit', persona.uuid)"
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
        <!-- Contact Info -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Información de Contacto
          </h3>
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Correo Electrónico</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">
                {{ persona.email || '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Teléfono Principal</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">
                {{ persona.telefono || '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Teléfono Alternativo</p>
              <p class="mt-1 font-medium text-gray-800 dark:text-white">
                {{ persona.telefono_alternativo || '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Cuenta de Usuario</p>
              <p class="mt-1">
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
              </p>
            </div>
          </div>
        </div>

        <!-- Linked User -->
        <div v-if="persona.user" class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Cuenta de Usuario Vinculada
          </h3>
          <div class="flex items-center gap-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-sm font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
              {{ persona.user.name.charAt(0) }}
            </div>
            <div>
              <p class="font-medium text-gray-800 dark:text-white">{{ persona.user.name }}</p>
              <p class="text-sm text-gray-500">{{ persona.user.email }}</p>
            </div>
          </div>
        </div>

        <!-- Unidades -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Unidades Relacionadas
          </h3>
          <div v-if="props.persona.unidades && props.persona.unidades.length > 0" class="space-y-3">
            <div
              v-for="unidad in props.persona.unidades"
              :key="unidad.uuid"
              class="flex items-center justify-between rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div>
                <p class="font-medium text-gray-800 dark:text-white">
                  {{ unidad.numero }}
                  <span v-if="unidad.pivot?.es_principal" class="ml-1 text-xs text-brand-500">(Principal)</span>
                </p>
                <p class="text-sm text-gray-500">{{ unidad.tipo }}</p>
              </div>
              <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ getRelationLabel(unidad.pivot?.tipo_relacion || '') }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            No hay unidades relacionadas con esta persona.
          </p>
        </div>

        <!-- Notes -->
        <div v-if="persona.notas" class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Notas
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">{{ persona.notas }}</p>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Document Info -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Documento de Identidad
          </h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Tipo</p>
              <p class="font-medium text-gray-800 dark:text-white">{{ persona.tipo_documento_label }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Número</p>
              <p class="font-medium text-gray-800 dark:text-white">{{ persona.numero_documento }}</p>
            </div>
          </div>
        </div>

        <!-- Timestamps -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Historial
          </h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Fecha de Creación</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(persona.created_at) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Última Actualización</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(persona.updated_at) }}</p>
            </div>
          </div>
        </div>

        <!-- UUID -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Identificador
          </h3>
          <p class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ persona.uuid }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
