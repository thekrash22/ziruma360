<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

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

interface Ownership {
  uuid: string;
  person_uuid: string | null;
  person_name: string | null;
  ownership_type: string | null;
  ownership_type_label: string | null;
  percentage: string | null;
  valid_from: string | null;
  valid_until: string | null;
  status: string | null;
}

interface Occupancy {
  uuid: string;
  person_uuid: string | null;
  person_name: string | null;
  occupancy_type: string | null;
  occupancy_type_label: string | null;
  is_primary_contact: boolean;
  valid_from: string | null;
  valid_until: string | null;
  status: string | null;
}

interface Lease {
  uuid: string;
  tenant_uuid: string | null;
  tenant_name: string | null;
  landlord_uuid: string | null;
  landlord_name: string | null;
  starts_on: string | null;
  ends_on: string | null;
  status: string | null;
  status_label: string | null;
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
  coefficient: string | null;
  notes: string | null;
  building: BuildingSummary | null;
  building_floor: FloorSummary | null;
  ownerships: Ownership[];
  occupancies: Occupancy[];
  leases: Lease[];
  created_at: string | null;
  updated_at: string | null;
}

interface PageProps {
  unit: Unit;
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const getStatusColor = (status: string | null) => {
  const colors: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    inactive: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    under_review: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
  };
  return colors[status || ''] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const formatDate = (dateString: string | null) => {
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
  <Head title="Unidad" />

  <AppLayout>
    <!-- Flash Messages -->
    <div v-if="props.flash?.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
      {{ props.flash.success }}
    </div>

    <!-- Breadcrumb -->
    <div class="mb-6">
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a :href="route('units.index')" class="hover:text-gray-700 dark:hover:text-gray-300">Unidades</a>
        <span>/</span>
        <span class="text-gray-800 dark:text-white">{{ props.unit.number }}</span>
      </div>
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="flex items-center gap-3">
          <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
            {{ props.unit.number }}
          </h2>
          <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(props.unit.status)]">
            {{ props.unit.status_label }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-500">
          {{ props.unit.unit_type_label }}
          <span v-if="props.unit.building"> · {{ props.unit.building.name }}</span>
          <span v-if="props.unit.building_floor"> · {{ props.unit.building_floor.display_name }}</span>
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a
          :href="route('units.edit', props.unit.uuid)"
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
          <div v-if="props.unit.ownerships.length > 0" class="space-y-3">
            <div
              v-for="ownership in props.unit.ownerships"
              :key="ownership.uuid"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500 text-sm font-medium text-white">
                  {{ (ownership.person_name || '?').charAt(0) }}
                </div>
                <div>
                  <p class="font-medium text-gray-800 dark:text-white">
                    {{ ownership.person_name || 'Persona sin nombre' }}
                  </p>
                  <p class="text-sm text-gray-500">
                    {{ ownership.ownership_type_label }}
                    <span v-if="ownership.percentage"> · {{ ownership.percentage }}%</span>
                    <span v-if="ownership.valid_from">
                      · desde {{ formatDate(ownership.valid_from) }}
                    </span>
                  </p>
                </div>
              </div>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(ownership.status)]">
                {{ ownership.status }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            Esta unidad no tiene titularidades registradas.
          </p>
        </div>

        <!-- Ocupaciones -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Ocupaciones
          </h3>
          <div v-if="props.unit.occupancies.length > 0" class="space-y-3">
            <div
              v-for="occupancy in props.unit.occupancies"
              :key="occupancy.uuid"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div>
                <p class="font-medium text-gray-800 dark:text-white">
                  {{ occupancy.person_name || 'Persona sin nombre' }}
                  <span v-if="occupancy.is_primary_contact" class="ml-1 text-xs text-brand-500">(Contacto principal)</span>
                </p>
                <p class="text-sm text-gray-500">
                  {{ occupancy.occupancy_type_label }}
                  <span v-if="occupancy.valid_from">
                    · desde {{ formatDate(occupancy.valid_from) }}
                  </span>
                </p>
              </div>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(occupancy.status)]">
                {{ occupancy.status }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            Esta unidad no tiene ocupaciones registradas.
          </p>
        </div>

        <!-- Arrendamientos -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
            Arrendamientos
          </h3>
          <div v-if="props.unit.leases.length > 0" class="space-y-3">
            <div
              v-for="lease in props.unit.leases"
              :key="lease.uuid"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
            >
              <div>
                <p class="font-medium text-gray-800 dark:text-white">
                  {{ lease.tenant_name || 'Arrendatario sin nombre' }}
                </p>
                <p class="text-sm text-gray-500">
                  {{ formatDate(lease.starts_on) }} → {{ lease.ends_on ? formatDate(lease.ends_on) : 'sin fecha de fin' }}
                  <span v-if="lease.landlord_name"> · Titular: {{ lease.landlord_name }}</span>
                </p>
              </div>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(lease.status)]">
                {{ lease.status_label }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            Esta unidad no tiene arrendamientos registrados.
          </p>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Info Card -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Informacion de la Unidad
          </h3>
          <dl class="space-y-3">
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Numero</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.unit.number }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Tipo</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.unit.unit_type_label }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Edificio</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">
                {{ props.unit.building?.name || '-' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Piso</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">
                {{ props.unit.building_floor?.display_name || '-' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Codigo interno</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">
                {{ props.unit.internal_code || '-' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Area</dt>
              <dd class="text-sm font-medium text-gray-800 dark:text-white">
                {{ props.unit.area_m2 ? `${props.unit.area_m2} m2` : '-' }}
              </dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Estado</dt>
              <dd>
                <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(props.unit.status)]">
                  {{ props.unit.status_label }}
                </span>
              </dd>
            </div>
          </dl>
        </div>

        <!-- Notas -->
        <div v-if="props.unit.notes" class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Notas
          </h3>
          <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ props.unit.notes }}</p>
        </div>

        <!-- Historial -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Historial
          </h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Fecha de Creacion</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(props.unit.created_at) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Ultima Actualizacion</p>
              <p class="text-sm text-gray-800 dark:text-white">{{ formatDate(props.unit.updated_at) }}</p>
            </div>
          </div>
        </div>

        <!-- UUID -->
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
            Identificador
          </h3>
          <p class="break-all font-mono text-sm text-gray-600 dark:text-gray-400">{{ props.unit.uuid }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
