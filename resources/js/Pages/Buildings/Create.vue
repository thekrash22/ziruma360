<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface PageProps {
  buildingTypes: Array<{
    value: string;
    label: string;
  }>;
  statuses: Array<{
    value: string;
    label: string;
  }>;
  errors?: Record<string, string>;
}

const props = defineProps<PageProps>();

const form = ref({
  name: '',
  code: '',
  building_type: 'tower',
  floor_count: null as number | null,
  apartments_per_floor_reference: null as number | null,
  has_elevators: false,
  elevator_count: null as number | null,
  status: 'active',
});

const hasElevators = ref(false);

const updateElevators = () => {
  form.value.has_elevators = hasElevators.value;
  if (!hasElevators.value) {
    form.value.elevator_count = null;
  }
};
</script>

<template>
  <Head title="Nuevo Edificio" />

  <AppLayout>
    <div class="mb-6">
      <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
        Nuevo Edificio
      </h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Completa el formulario para crear un nuevo edificio.
      </p>
    </div>

    <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <form :action="route('buildings.store')" method="post">
        <input type="hidden" name="_token" :value="($page.props as any).csrf_token" />
        
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <!-- Nombre -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Nombre <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              name="name"
              required
              placeholder="Ej: Torre A"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <!-- Código -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Código <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.code"
              type="text"
              name="code"
              required
              placeholder="Ej: A"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <!-- Tipo de edificio -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tipo de edificio <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.building_type"
              name="building_type"
              required
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option v-for="type in props.buildingTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </div>

          <!-- Estado -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Estado
            </label>
            <select
              v-model="form.status"
              name="status"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option v-for="status in props.statuses" :key="status.value" :value="status.value">
                {{ status.label }}
              </option>
            </select>
          </div>

          <!-- Cantidad de pisos (referencia) -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Cantidad de pisos (referencia)
            </label>
            <input
              v-model.number="form.floor_count"
              type="number"
              name="floor_count"
              min="1"
              max="100"
              placeholder="Ej: 15"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p class="mt-1 text-xs text-gray-500">Valor informativo, no genera pisos automáticamente.</p>
          </div>

          <!-- Apartamentos por piso (referencia) -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Apartamentos por piso (referencia)
            </label>
            <input
              v-model.number="form.apartments_per_floor_reference"
              type="number"
              name="apartments_per_floor_reference"
              min="1"
              max="50"
              placeholder="Ej: 4"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>
        </div>

        <!-- Ascensores -->
        <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-800">
          <div class="flex items-center gap-3">
            <input
              v-model="hasElevators"
              type="checkbox"
              id="has_elevators"
              class="h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
              @change="updateElevators"
            />
            <label for="has_elevators" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              El edificio tiene ascensores
            </label>
          </div>

          <div v-if="hasElevators" class="mt-4">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Cantidad de ascensores
            </label>
            <input
              v-model.number="form.elevator_count"
              type="number"
              name="elevator_count"
              min="1"
              max="20"
              placeholder="Ej: 2"
              class="w-32 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>
        </div>

        <!-- Buttons -->
        <div class="mt-8 flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
          <a
            :href="route('buildings.index')"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancelar
          </a>
          <button
            type="submit"
            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
          >
            Crear Edificio
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
