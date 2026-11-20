<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Ownership {
  uuid: string;
  unit_uuid: string | null;
  unit_number: string | null;
  ownership_type: string | null;
  ownership_type_label: string | null;
  percentage: string | null;
  valid_from: string | null;
  valid_until: string | null;
  status: string | null;
}

interface Occupancy {
  uuid: string;
  unit_uuid: string | null;
  unit_number: string | null;
  occupancy_type: string | null;
  occupancy_type_label: string | null;
  is_primary_contact: boolean;
  valid_from: string | null;
  valid_until: string | null;
  status: string | null;
}

interface Lease {
  uuid: string;
  unit_uuid: string | null;
  unit_number: string | null;
  tenant_name: string | null;
  landlord_name: string | null;
  starts_on: string | null;
  ends_on: string | null;
  status: string | null;
  status_label: string | null;
}

interface Person {
  uuid: string;
  full_name: string;
  first_name?: string | null;
  last_name?: string | null;
  document_type?: string | null;
  document_type_label?: string | null;
  document_number?: string | null;
  phone?: string | null;
  email?: string | null;
  birth_date?: string | null;
  status: string;
  status_label?: string;
  notes?: string | null;
  ownerships?: Ownership[];
  occupancies?: Occupancy[];
  leases?: Lease[];
  created_at?: string | null;
  updated_at?: string | null;
}

interface PageProps {
  person: Person;
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const getStatusColor = (status: string | null | undefined) => {
  const colors: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    inactive: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    ended: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    terminated: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
  };
  return colors[status || ''] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const formatDate = (dateString: string | null | undefined) => {
  if (!dateString) {
    return '-';
  }

  return new Date(dateString).toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};
</script>

<template>
  <Head title="Persona" />

  <AppLayout>
    <!-- Flash Messages -->
    <div v-if="props.flash?.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
      {{ props.flash.success }}
    </div>

    <!-- Breadcrumb -->
    <div class="mb-6">
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a :href="route('people.index')" class="hover:text-gray-700 dark:hover:text-gray-300">Personas</a>
        <span>/</span>
        <span class="text-gray-800 dark:text-white">{{ props.person.full_name }}</span>
      </div>
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="flex items-center gap-3">
          <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
            {{ props.person.full_name }}
          </h2>
          <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(props.person.status)]">
            {{ props.person.status_label || props.person.status }}
          </span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <a
          :href="route('people.edit', props.person.uuid)"
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
      <!-- Relations -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Titularidades -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Titularidades
          </h3>
          <div v-if="(props.person.ownerships || []).length > 0" class="space-y-3">
            <div
              v-for="ownership in props.person.ownerships"
              :key="ownership.uuid"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div>
                <p class="font-medium text-gray-800 dark:text-white">
                  <a v-if="ownership.unit_uuid" :href="route('units.show', ownership.unit_uuid)" class="hover:text-brand-500">
                    {{ ownership.unit_number || 'Unidad' }}
                  </a>
                  <span v-else>{{ ownership.unit_number || 'Unidad' }}</span>
                </p>
                <p class="text-sm text-gray-500">
                  {{ ownership.ownership_type_label }}
                  <span v-if="ownership.percentage"> · {{ ownership.percentage }}%</span>
                  <span v-if="ownership.valid_from"> · desde {{ formatDate(ownership.valid_from) }}</span>
                </p>
              </div>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(ownership.status)]">
                {{ ownership.status }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            Esta persona no tiene titularidades registradas.
          </p>
        </div>

        <!-- Ocupaciones -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Ocupaciones
          </h3>
          <div v-if="(props.person.occupancies || []).length > 0" class="space-y-3">
            <div
              v-for="occupancy in props.person.occupancies"
              :key="occupancy.uuid"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div>
                <p class="font-medium text-gray-800 dark:text-white">
                  <a v-if="occupancy.unit_uuid" :href="route('units.show', occupancy.unit_uuid)" class="hover:text-brand-500">
                    {{ occupancy.unit_number || 'Unidad' }}
                  </a>
                  <span v-else>{{ occupancy.unit_number || 'Unidad' }}</span>
                </p>
                <p class="text-sm text-gray-500">
                  {{ occupancy.occupancy_type_label }}
                  <span v-if="occupancy.is_primary_contact"> · Contacto principal</span>
                </p>
              </div>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(occupancy.status)]">
                {{ occupancy.status }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            Esta persona no tiene ocupaciones registradas.
          </p>
        </div>

        <!-- Arrendamientos -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Arrendamientos
          </h3>
          <div v-if="(props.person.leases || []).length > 0" class="space-y-3">
            <div
              v-for="lease in props.person.leases"
              :key="lease.uuid"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div>
                <p class="font-medium text-gray-800 dark:text-white">
                  <a v-if="lease.unit_uuid" :href="route('units.show', lease.unit_uuid)" class="hover:text-brand-500">
                    {{ lease.unit_number || 'Unidad' }}
                  </a>
                  <span v-else>{{ lease.unit_number || 'Unidad' }}</span>
                </p>
                <p class="text-sm text-gray-500">
                  {{ formatDate(lease.starts_on) }} → {{ lease.ends_on ? formatDate(lease.ends_on) : 'sin fecha de fin' }}
                  <span v-if="lease.landlord_name"> · Titular: {{ lease.landlord_name }}</span>
                </p>
              </div>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(lease.status)]">
                {{ lease.status_label || lease.status }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            Esta persona no tiene arrendamientos registrados.
          </p>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Info Card -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Informacion de la Persona
          </h3>
          <dl class="space-y-3">
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Nombre</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.person.full_name }}</dd>
            </div>
            <div v-if="props.person.document_number !== undefined" class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Documento</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">
                {{ props.person.document_type_label || props.person.document_type }} {{ props.person.document_number }}
              </dd>
            </div>
            <div v-if="props.person.phone !== undefined" class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Telefono</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.person.phone || '-' }}</dd>
            </div>
            <div v-if="props.person.email !== undefined" class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Email</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.person.email || '-' }}</dd>
            </div>
            <div v-if="props.person.birth_date !== undefined" class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Nacimiento</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ formatDate(props.person.birth_date) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Estado</dt>
              <dd>
                <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(props.person.status)]">
                  {{ props.person.status_label || props.person.status }}
                </span>
              </dd>
            </div>
          </dl>
        </div>

        <!-- Notas -->
        <div v-if="props.person.notes" class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Notas
          </h3>
          <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ props.person.notes }}</p>
        </div>

        <!-- Historial -->
        <div v-if="props.person.created_at" class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Historial
          </h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Fecha de Creacion</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(props.person.created_at) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Ultima Actualizacion</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(props.person.updated_at) }}</p>
            </div>
          </div>
        </div>

        <!-- UUID -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Identificador
          </h3>
          <p class="break-all font-mono text-sm text-gray-600 dark:text-gray-400">{{ props.person.uuid }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
