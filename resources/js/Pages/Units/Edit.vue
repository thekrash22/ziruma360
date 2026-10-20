<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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
  area_m2: number | null;
  habitaciones: number | null;
  banos: number | null;
  status: string;
  residential_complex_id: number;
}

interface Props {
  unidad: Unidad;
  complexes: ResidentialComplex[];
}

const props = defineProps<Props>();

const form = useForm({
  residential_complex_id: props.unidad.residential_complex_id,
  numero: props.unidad.numero,
  piso: props.unidad.piso || '',
  torre: props.unidad.torre || '',
  tipo: props.unidad.tipo,
  area_m2: props.unidad.area_m2 || '',
  habitaciones: props.unidad.habitaciones || '',
  banos: props.unidad.banos || '',
  status: props.unidad.status,
});

const tipoOptions = [
  'Apartamento',
  'Casa',
  'Local',
  'Parqueadero',
  'Bodega',
  'Oficina',
  'Penthouse',
];

const statusOptions = [
  { value: 'draft', label: 'Borrador' },
  { value: 'active', label: 'Activo' },
  { value: 'inactive', label: 'Inactivo' },
  { value: 'maintenance', label: 'En mantenimiento' },
];

const isSubmitting = computed(() => form.processing);

const handleSubmit = () => {
  form.put(route('units.update', props.unidad.uuid), {
    onSuccess: () => {
      // Redirect handled by controller
    },
  });
};
</script>

<template>
  <Head title="Editar Unidad" />

  <AppLayout>
    <div class="mb-6">
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a :href="route('units.index')" class="hover:text-gray-700 dark:hover:text-gray-300">Unidades</a>
        <span>/</span>
        <span class="text-gray-800 dark:text-white">{{ unidad.numero }}</span>
        <span>/</span>
        <span>Editar</span>
      </div>
    </div>

    <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <h2 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white">
        Editar Unidad
      </h2>

      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Basic Info -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Conjunto Residencial <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.residential_complex_id"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.residential_complex_id
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            >
              <option value="">Seleccionar conjunto...</option>
              <option v-for="complex in props.complexes" :key="complex.id" :value="complex.id">
                {{ complex.name }} ({{ complex.code }})
              </option>
            </select>
            <p v-if="form.errors.residential_complex_id" class="mt-1 text-sm text-red-500">
              {{ form.errors.residential_complex_id }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Número de Unidad <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.numero"
              type="text"
              placeholder="Ej: 101, 2A, PH-01"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.numero
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            />
            <p v-if="form.errors.numero" class="mt-1 text-sm text-red-500">
              {{ form.errors.numero }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Torre
            </label>
            <input
              v-model="form.torre"
              type="text"
              placeholder="Ej: Torre A, Torre B"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Piso
            </label>
            <input
              v-model="form.piso"
              type="text"
              placeholder="Ej: 1, 2, PB, PH"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tipo de Unidad <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.tipo"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.tipo
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            >
              <option v-for="tipo in tipoOptions" :key="tipo" :value="tipo">
                {{ tipo }}
              </option>
            </select>
            <p v-if="form.errors.tipo" class="mt-1 text-sm text-red-500">
              {{ form.errors.tipo }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Estado
            </label>
            <select
              v-model="form.status"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Additional Info -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Área (m²)
            </label>
            <input
              v-model="form.area_m2"
              type="number"
              min="0"
              placeholder="Ej: 85"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Habitaciones
            </label>
            <input
              v-model="form.habitaciones"
              type="number"
              min="0"
              placeholder="Ej: 3"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Baños
            </label>
            <input
              v-model="form.banos"
              type="number"
              min="0"
              placeholder="Ej: 2"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
          <a
            :href="route('units.index')"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancelar
          </a>
          <button
            type="submit"
            :disabled="isSubmitting"
            :class="[
              'rounded-lg px-4 py-2.5 text-sm font-medium text-white',
              isSubmitting
                ? 'bg-brand-400 cursor-not-allowed'
                : 'bg-brand-500 hover:bg-brand-600'
            ]"
          >
            {{ isSubmitting ? 'Guardando...' : 'Actualizar Unidad' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
