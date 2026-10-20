<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface TipoDocumento {
  value: string;
  label: string;
}

interface Persona {
  id: number;
  uuid: string;
  nombre: string;
  apellido: string;
  tipo_documento: string;
  numero_documento: string;
  email: string | null;
  telefono: string | null;
  telefono_alternativo: string | null;
  notas: string | null;
  user_id: number | null;
}

interface Props {
  persona: Persona;
  documentTypes: TipoDocumento[];
}

const props = defineProps<Props>();

const form = useForm({
  nombre: props.persona.nombre,
  apellido: props.persona.apellido,
  tipo_documento: props.persona.tipo_documento,
  numero_documento: props.persona.numero_documento,
  email: props.persona.email || '',
  telefono: props.persona.telefono || '',
  telefono_alternativo: props.persona.telefono_alternativo || '',
  notas: props.persona.notas || '',
});

const isSubmitting = computed(() => form.processing);

const handleSubmit = () => {
  form.put(route('people.update', props.persona.uuid), {
    onSuccess: () => {
      // Redirect handled by controller
    },
  });
};
</script>

<template>
  <Head title="Editar Persona" />

  <AppLayout>
    <div class="mb-6">
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a :href="route('people.index')" class="hover:text-gray-700 dark:hover:text-gray-300">Personas</a>
        <span>/</span>
        <span class="text-gray-800 dark:text-white">{{ persona.nombre }} {{ persona.apellido }}</span>
        <span>/</span>
        <span>Editar</span>
      </div>
    </div>

    <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <h2 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white">
        Editar Persona
      </h2>

      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Personal Info -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Nombre <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.nombre"
              type="text"
              placeholder="Nombre(s)"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.nombre
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            />
            <p v-if="form.errors.nombre" class="mt-1 text-sm text-red-500">
              {{ form.errors.nombre }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Apellido <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.apellido"
              type="text"
              placeholder="Apellido(s)"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.apellido
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            />
            <p v-if="form.errors.apellido" class="mt-1 text-sm text-red-500">
              {{ form.errors.apellido }}
            </p>
          </div>
        </div>

        <!-- Document Info -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tipo de Documento <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.tipo_documento"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.tipo_documento
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            >
              <option v-for="doc in props.documentTypes" :key="doc.value" :value="doc.value">
                {{ doc.label }} ({{ doc.value }})
              </option>
            </select>
            <p v-if="form.errors.tipo_documento" class="mt-1 text-sm text-red-500">
              {{ form.errors.tipo_documento }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Número de Documento <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.numero_documento"
              type="text"
              placeholder="Número sin puntos ni espacios"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.numero_documento
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            />
            <p v-if="form.errors.numero_documento" class="mt-1 text-sm text-red-500">
              {{ form.errors.numero_documento }}
            </p>
          </div>
        </div>

        <!-- Contact Info -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Correo Electrónico
            </label>
            <input
              v-model="form.email"
              type="email"
              placeholder="correo@ejemplo.com"
              :class="[
                'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1',
                form.errors.email
                  ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                  : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
              ]"
            />
            <p v-if="form.errors.email" class="mt-1 text-sm text-red-500">
              {{ form.errors.email }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Teléfono Principal
            </label>
            <input
              v-model="form.telefono"
              type="text"
              placeholder="300 123 4567"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Teléfono Alternativo
          </label>
          <input
            v-model="form.telefono_alternativo"
            type="text"
            placeholder="Fijo u otro móvil"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </div>

        <!-- Notes -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Notas
          </label>
          <textarea
            v-model="form.notas"
            rows="3"
            placeholder="Información adicional sobre la persona..."
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
          <a
            :href="route('people.index')"
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
            {{ isSubmitting ? 'Guardando...' : 'Actualizar Persona' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
