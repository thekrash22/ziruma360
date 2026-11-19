<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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

interface Unit {
  uuid: string;
  number: string;
  unit_type: string;
  unit_type_label: string;
  status: string;
  status_label: string;
  internal_code: string | null;
  area_m2: string | null;
  building_id: number | null;
  building_floor_id: number | null;
}

interface PageProps {
  unit: Unit;
  unitTypes: Array<{
    value: string;
    label: string;
  }>;
  statuses: Array<{
    value: string;
    label: string;
  }>;
  buildings: BuildingOption[];
  editableFields: string[];
}

const props = defineProps<PageProps>();

const form = useForm({
  number: props.unit.number,
  unit_type: props.unit.unit_type,
  status: props.unit.status,
  building_id: props.unit.building_id === null ? '' : String(props.unit.building_id),
  building_floor_id: props.unit.building_floor_id === null ? '' : String(props.unit.building_floor_id),
  internal_code: props.unit.internal_code || '',
  area_m2: props.unit.area_m2 === null ? '' : String(props.unit.area_m2),
  notes: '',
});

const selectedBuilding = computed(() =>
  props.buildings.find((building) => String(building.id) === String(form.building_id)),
);

// El propietario solo administra los datos limitados de su unidad: el
// formulario solo envia los campos que el servidor acepta.
const canEdit = (field: string) => props.editableFields.includes(field);

const submit = () => {
  // El propietario solo administra los datos limitados de su unidad: el
  // formulario solo envia los campos que el servidor acepta.
  form.transform((data) => {
    const payload: Record<string, string> = {};

    if (canEdit('number')) payload.number = data.number;
    if (canEdit('unit_type')) payload.unit_type = data.unit_type;
    if (canEdit('status')) payload.status = data.status;
    if (canEdit('building_id')) payload.building_id = data.building_id;
    if (canEdit('building_floor_id')) payload.building_floor_id = data.building_floor_id;
    if (canEdit('internal_code')) payload.internal_code = data.internal_code;
    if (canEdit('area_m2')) payload.area_m2 = data.area_m2;
    if (canEdit('notes')) payload.notes = data.notes;

    return payload;
  });

  form.put(route('units.update', props.unit.uuid), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Editar Unidad" />

  <AppLayout>
    <div class="mb-6">
      <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
        Editar Unidad {{ props.unit.number }}
      </h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Actualiza los datos de la unidad. La baja logica se hace desde el listado.
      </p>
    </div>

    <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <!-- Numero -->
          <div v-if="canEdit('number')">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Numero <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.number"
              type="text"
              required
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.number" class="mt-1 text-xs text-red-500">{{ form.errors.number }}</p>
          </div>

          <!-- Tipo -->
          <div v-if="canEdit('unit_type')">
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
          <div v-if="canEdit('building_id')">
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
          <div v-if="canEdit('building_floor_id')">
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
          <div v-if="canEdit('internal_code')">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Codigo interno
            </label>
            <input
              v-model="form.internal_code"
              type="text"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.internal_code" class="mt-1 text-xs text-red-500">{{ form.errors.internal_code }}</p>
          </div>

          <!-- Area -->
          <div v-if="canEdit('area_m2')">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Area (m2)
            </label>
            <input
              v-model="form.area_m2"
              type="number"
              step="0.01"
              min="0"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.area_m2" class="mt-1 text-xs text-red-500">{{ form.errors.area_m2 }}</p>
          </div>

          <!-- Estado -->
          <div v-if="canEdit('status')">
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
            :href="route('units.show', props.unit.uuid)"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancelar
          </a>
          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-60"
          >
            Guardar Cambios
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
