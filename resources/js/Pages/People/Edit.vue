<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface Person {
  uuid: string;
  full_name: string;
  first_name: string | null;
  last_name: string | null;
  document_type: string | null;
  document_type_label: string | null;
  document_number: string | null;
  phone: string | null;
  email: string | null;
  birth_date: string | null;
  status: string;
  status_label: string;
  notes: string | null;
}

interface PageProps {
  person: Person;
  documentTypes: Array<{
    value: string;
    label: string;
  }>;
  statuses: Array<{
    value: string;
    label: string;
  }>;
  canViewDocument: boolean;
}

const props = defineProps<PageProps>();

const form = useForm({
  full_name: props.person.full_name,
  first_name: props.person.first_name || '',
  last_name: props.person.last_name || '',
  document_type: props.person.document_type || '',
  // El documento solo se edita con el permiso explicito; sin el, la UI
  // muestra el valor enmascarado y no lo envia (SEG-P01).
  document_number: props.canViewDocument ? props.person.document_number || '' : '',
  phone: props.person.phone || '',
  email: props.person.email || '',
  birth_date: props.person.birth_date || '',
  status: props.person.status,
  notes: props.person.notes || '',
});

const submit = () => {
  form.transform((data) => {
    const payload: Record<string, string> = { ...data };

    if (!props.canViewDocument) {
      delete payload.document_number;
    }

    return payload;
  });

  form.put(route('people.update', props.person.uuid), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Editar Persona" />

  <AppLayout>
    <div class="mb-6">
      <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
        Editar {{ props.person.full_name }}
      </h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Actualiza los datos de la persona. La baja logica se hace desde el listado.
      </p>
    </div>

    <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <!-- Nombre completo -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Nombre completo <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.full_name"
              type="text"
              required
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-500">{{ form.errors.full_name }}</p>
          </div>

          <!-- Nombres -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Nombres
            </label>
            <input
              v-model="form.first_name"
              type="text"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.first_name" class="mt-1 text-xs text-red-500">{{ form.errors.first_name }}</p>
          </div>

          <!-- Apellidos -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Apellidos
            </label>
            <input
              v-model="form.last_name"
              type="text"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.last_name" class="mt-1 text-xs text-red-500">{{ form.errors.last_name }}</p>
          </div>

          <!-- Tipo de documento -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tipo de documento
            </label>
            <select
              v-model="form.document_type"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="">Sin tipo</option>
              <option v-for="type in props.documentTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
            <p v-if="form.errors.document_type" class="mt-1 text-xs text-red-500">{{ form.errors.document_type }}</p>
          </div>

          <!-- Numero de documento -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Numero de documento
            </label>
            <input
              v-model="form.document_number"
              type="text"
              :disabled="!props.canViewDocument"
              :placeholder="props.canViewDocument ? 'Ej: 12345678' : 'Documento enmascarado'"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="!props.canViewDocument" class="mt-1 text-xs text-gray-500">
              No tienes el permiso para ver el documento en claro: se conserva el actual ({{ props.person.document_number || 'sin documento' }}).
            </p>
            <p v-if="form.errors.document_number" class="mt-1 text-xs text-red-500">{{ form.errors.document_number }}</p>
          </div>

          <!-- Telefono -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Telefono
            </label>
            <input
              v-model="form.phone"
              type="text"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
          </div>

          <!-- Email -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Email
            </label>
            <input
              v-model="form.email"
              type="email"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
          </div>

          <!-- Fecha de nacimiento -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Fecha de nacimiento
            </label>
            <input
              v-model="form.birth_date"
              type="date"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="form.errors.birth_date" class="mt-1 text-xs text-red-500">{{ form.errors.birth_date }}</p>
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
            :href="route('people.show', props.person.uuid)"
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
