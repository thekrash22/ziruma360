<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Floor {
  id: number;
  uuid: string;
  floor_number: number;
  floor_type: string;
  floor_type_label: string;
  name: string | null;
  display_name: string;
  status: string;
  units_count: number;
}

interface Building {
  id: number;
  uuid: string;
  name: string;
  code: string;
  building_type: string;
  building_type_label: string;
  floor_count: number | null;
  apartments_per_floor_reference: number | null;
  has_elevators: boolean;
  elevator_count: number | null;
  status: string;
  status_label: string;
  created_at: string;
  updated_at: string;
  floors: Floor[];
}

interface PageProps {
  building: Building;
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const showFloorForm = ref(false);
const floorForm = ref({
  start_floor: 1,
  end_floor: 10,
  basement_floors: 0,
});

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    inactive: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    basement: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    residential: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    parking: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
  };
  return colors[status] || 'bg-gray-100 text-gray-700';
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
  <Head title="Edificio" />

  <AppLayout>
    <!-- Flash Messages -->
    <div v-if="props.flash?.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
      {{ props.flash.success }}
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="flex items-center gap-3">
          <a
            :href="route('buildings.index')"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </a>
          <div>
            <h2 class="text-title-lg font-semibold text-gray-800 dark:text-white">
              {{ props.building.name }}
            </h2>
            <p class="text-sm text-gray-500">Código: {{ props.building.code }}</p>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a
          :href="route('buildings.edit', props.building.uuid)"
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
      <!-- Info Card -->
      <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
          Información del Edificio
        </h3>
        <dl class="space-y-3">
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Tipo</dt>
            <dd class="text-sm font-medium text-gray-800 dark:text-white">
              {{ props.building.building_type_label }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Estado</dt>
            <dd>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(props.building.status)]">
                {{ props.building.status_label }}
              </span>
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Pisos (ref.)</dt>
            <dd class="text-sm font-medium text-gray-800 dark:text-white">
              {{ props.building.floor_count || '-' }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Apts por piso (ref.)</dt>
            <dd class="text-sm font-medium text-gray-800 dark:text-white">
              {{ props.building.apartments_per_floor_reference || '-' }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Ascensores</dt>
            <dd class="text-sm font-medium text-gray-800 dark:text-white">
              {{ props.building.has_elevators ? `${props.building.elevator_count} ascensor(es)` : 'No' }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Creado</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">
              {{ formatDate(props.building.created_at) }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Floors Section -->
      <div class="lg:col-span-2">
        <div class="rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
            <h3 class="text-sm font-medium text-gray-800 dark:text-white">
              Pisos del Edificio
            </h3>
            <button
              @click="showFloorForm = !showFloorForm"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Generar Pisos
            </button>
          </div>

          <!-- Generate Floors Form -->
          <div v-if="showFloorForm" class="border-b border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
            <form :action="route('buildings.floors.generate', props.building.uuid) + '?start_floor=' + floorForm.start_floor + '&end_floor=' + floorForm.end_floor + '&basement_floors=' + floorForm.basement_floors" method="post" class="flex flex-wrap items-end gap-4">
              <input type="hidden" name="_token" :value="($page.props as any).csrf_token" />
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sótanos</label>
                <input
                  v-model.number="floorForm.basement_floors"
                  type="number"
                  min="0"
                  max="5"
                  class="w-20 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Desde piso</label>
                <input
                  v-model.number="floorForm.start_floor"
                  type="number"
                  min="1"
                  max="100"
                  class="w-20 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Hasta piso</label>
                <input
                  v-model.number="floorForm.end_floor"
                  type="number"
                  min="1"
                  max="100"
                  class="w-20 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
              <button
                type="submit"
                class="rounded-lg bg-brand-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-600"
              >
                Generar
              </button>
              <button
                type="button"
                @click="showFloorForm = false"
                class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
              >
                Cancelar
              </button>
            </form>
          </div>

          <!-- Floors Grid -->
          <div class="p-4">
            <div v-if="props.building.floors && props.building.floors.length > 0" class="grid grid-cols-4 gap-3 sm:grid-cols-6 md:grid-cols-8">
              <div
                v-for="floor in props.building.floors"
                :key="floor.id"
                class="flex flex-col items-center rounded-lg border p-3 text-center dark:border-gray-700"
                :class="{
                  'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20': floor.floor_type === 'basement',
                  'border-purple-200 bg-purple-50 dark:border-purple-800 dark:bg-purple-900/20': floor.floor_type === 'residential',
                }"
              >
                <span class="text-lg font-bold text-gray-800 dark:text-white">{{ floor.floor_number }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ floor.floor_type_label }}</span>
                <span class="mt-1 text-xs text-gray-400">{{ floor.units_count }} unidades</span>
              </div>
            </div>
            <div v-else class="py-8 text-center">
              <p class="text-sm text-gray-500 dark:text-gray-400">
                No hay pisos registrados.
              </p>
              <p class="mt-1 text-xs text-gray-400">
                Usa el botón "Generar Pisos" para crear varios pisos a la vez.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
