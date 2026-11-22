<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface PageProps {
  defaults: {
    country_code: string;
    timezone: string;
    currency_code: string;
  };
  errors?: Record<string, string>;
}

const props = defineProps<PageProps>();

const form = ref({
  name: '',
  code: '',
  address: '',
  city: '',
  state: '',
  legal_identifier: '',
  country_code: props.defaults.country_code,
  timezone: props.defaults.timezone,
  currency_code: props.defaults.currency_code,
});
</script>

<template>
  <Head title="Nuevo Conjunto" />

  <AppLayout>
    <div class="mb-6">
      <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
        Nuevo Conjunto
      </h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        El conjunto nace en estado borrador: lo activas cuando tenga su estructura y sus políticas.
      </p>
    </div>

    <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <form :action="route('complexes.store')" method="post">
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
              placeholder="Ej: Torres del Parque"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="props.errors?.name" class="mt-1 text-xs text-red-500">{{ props.errors.name }}</p>
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
              placeholder="Ej: torres-del-parque"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="props.errors?.code" class="mt-1 text-xs text-red-500">{{ props.errors.code }}</p>
          </div>

          <!-- Dirección -->
          <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Dirección <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.address"
              type="text"
              name="address"
              required
              placeholder="Ej: Calle 100 # 20-30"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="props.errors?.address" class="mt-1 text-xs text-red-500">{{ props.errors.address }}</p>
          </div>

          <!-- Ciudad -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Ciudad <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.city"
              type="text"
              name="city"
              required
              placeholder="Ej: Bogotá"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="props.errors?.city" class="mt-1 text-xs text-red-500">{{ props.errors.city }}</p>
          </div>

          <!-- Departamento -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Departamento
            </label>
            <input
              v-model="form.state"
              type="text"
              name="state"
              placeholder="Ej: Cundinamarca"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <!-- Identificador legal -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Identificador legal
            </label>
            <input
              v-model="form.legal_identifier"
              type="text"
              name="legal_identifier"
              placeholder="Ej: 900123456-7"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <p v-if="props.errors?.legal_identifier" class="mt-1 text-xs text-red-500">{{ props.errors.legal_identifier }}</p>
          </div>

          <!-- País -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              País
            </label>
            <input
              v-model="form.country_code"
              type="text"
              name="country_code"
              maxlength="2"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <!-- Zona horaria -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Zona horaria
            </label>
            <input
              v-model="form.timezone"
              type="text"
              name="timezone"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>

          <!-- Moneda -->
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Moneda
            </label>
            <input
              v-model="form.currency_code"
              type="text"
              name="currency_code"
              maxlength="3"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>
        </div>

        <!-- Buttons -->
        <div class="mt-8 flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
          <a
            :href="route('complexes.index')"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancelar
          </a>
          <button
            type="submit"
            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
          >
            Crear Conjunto
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
