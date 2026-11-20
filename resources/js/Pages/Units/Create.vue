<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface FloorOption {
  id: number;
  uuid: string;
  floor_number: number;
  name: string | null;
  display_name: string;
}

interface BuildingOption {
  id: number;
  uuid: string;
  name: string;
  code: string;
  floors: FloorOption[];
}

interface PageProps {
  unitTypes: Array<{
    value: string;
    label: string;
  }>;
  statuses: Array<{
    value: string;
    label: string;
  }>;
  buildings: BuildingOption[];
}

const props = defineProps<PageProps>();

const form = useForm({
  number: '',
  unit_type: 'apartment',
  status: 'active',
  building_id: '',
  building_floor_id: '',
  internal_code: '',
  area_m2: '',
  notes: '',
});

const selectedBuilding = computed(() =>
  props.buildings.find((building) => String(building.id) === String(form.building_id)),
);

// El piso pertenece al edificio: al cambiar de edificio se limpia el piso.
watch(
  () => form.building_id,
  () => {
    form.building_floor_id = '';
  },
);

const submit = () => {
  form.post(route('units.store'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Nueva Unidad" />

  <AppLayout>
    <div class="mb-6">
      <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
        Nueva Unidad
      </h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Registra una unidad del conjunto. El numero es unico por conjunto, edificio y tipo.
      </p>
    </div>

    <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <!-- Numero -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Numero <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.number"
              type="text"
              required
              placeholder="Ej: A-301"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.number" class="mt-1 text-xs text-red-500">{{ form.errors.number }}</p>
          </div>

          <!-- Tipo -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tipo <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.unit_type"
              required
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option v-for="type in props.unitTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
            <p v-if="form.errors.unit_type" class="mt-1 text-xs text-red-500">{{ form.errors.unit_type }}</p>
          </div>

          <!-- Edificio -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Edificio
            </label>
            <select
              v-model="form.building_id"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="">Sin edificio</option>
              <option v-for="building in props.buildings" :key="building.uuid" :value="building.id">
                {{ building.name }} ({{ building.code }})
              </option>
            </select>
            <p v-if="form.errors.building_id" class="mt-1 text-xs text-red-500">{{ form.errors.building_id }}</p>
          </div>

          <!-- Piso -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Piso
            </label>
            <select
              v-model="form.building_floor_id"
              :disabled="!selectedBuilding"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="">Sin piso</option>
              <option v-for="floor in selectedBuilding?.floors || []" :key="floor.uuid" :value="floor.id">
                {{ floor.display_name }}
              </option>
            </select>
            <p v-if="form.errors.building_floor_id" class="mt-1 text-xs text-red-500">{{ form.errors.building_floor_id }}</p>
          </div>

          <!-- Codigo interno -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Codigo interno
            </label>
            <input
              v-model="form.internal_code"
              type="text"
              placeholder="Ej: AP-T1-301"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.internal_code" class="mt-1 text-xs text-red-500">{{ form.errors.internal_code }}</p>
          </div>

          <!-- Area -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Area (m2)
            </label>
            <input
              v-model="form.area_m2"
              type="number"
              step="0.01"
              min="0"
              placeholder="Ej: 78.50"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.area_m2" class="mt-1 text-xs text-red-500">{{ form.errors.area_m2 }}</p>
          </div>

          <!-- Estado -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Estado
            </label>
            <select
              v-model="form.status"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option v-for="status in props.statuses" :key="status.value" :value="status.value">
                {{ status.label }}
              </option>
            </select>
            <p v-if="form.errors.status" class="mt-1 text-xs text-red-500">{{ form.errors.status }}</p>
          </div>

          <!-- Notas -->
          <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Notas
            </label>
            <textarea
              v-model="form.notes"
              rows="3"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.notes" class="mt-1 text-xs text-red-500">{{ form.errors.notes }}</p>
          </div>
        </div>

        <!-- Buttons -->
        <div class="mt-8 flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
          <a
            :href="route('units.index')"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancelar
          </a>
          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-60"
          >
            Crear Unidad
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
