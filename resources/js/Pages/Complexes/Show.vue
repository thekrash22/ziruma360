<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';

interface Module {
  key: string;
  label: string;
  description: string;
  enabled: boolean;
}

interface Role {
  key: string;
  name: string;
  is_system: boolean;
  is_active: boolean;
  scope: string;
}

interface Member {
  id: number;
  name: string | null;
  email: string | null;
  status: string;
  status_label: string;
  roles: string[];
  joined_at: string | null;
}

interface PageProps {
  complex: {
    uuid: string;
    name: string;
    code: string;
    address: string | null;
    city: string | null;
    state: string | null;
    country_code: string | null;
    timezone: string | null;
    currency_code: string | null;
    legal_identifier?: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
    updated_at: string | null;
  };
  modules: Module[];
  roles: Role[];
  members: Member[];
  flash?: {
    success?: string;
    error?: string;
  };
}

const props = defineProps<PageProps>();

const page = usePage();

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    suspended: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    archived: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
  };
  return colors[status] || 'bg-gray-100 text-gray-700';
};

const getMembershipColor = (status: string) => {
  const colors: Record<string, string> = {
    invited: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    suspended: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    revoked: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
  };
  return colors[status] || 'bg-gray-100 text-gray-700';
};

const formatDate = (dateString: string | null) => {
  if (!dateString) return '-';

  return new Date(dateString).toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const selectComplex = () => {
  router.post(
    route('complexes.select', props.complex.uuid),
    { redirect: page.url },
    { preserveScroll: true }
  );
};
</script>

<template>
  <Head title="Conjunto" />

  <AppLayout>
    <!-- Flash Messages -->
    <div v-if="props.flash?.success" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
      {{ props.flash.success }}
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <a
          :href="route('complexes.index')"
          class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </a>
        <div>
          <h2 class="text-title-lg font-semibold text-gray-800 dark:text-white">
            {{ props.complex.name }}
          </h2>
          <p class="text-sm text-gray-500">Código: {{ props.complex.code }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(props.complex.status)]">
          {{ props.complex.status_label }}
        </span>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          @click="selectComplex"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Usar este conjunto
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Identidad -->
      <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="mb-4 text-sm font-medium uppercase text-gray-500 dark:text-gray-400">
          Información del Conjunto
        </h3>
        <dl class="space-y-3">
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Nombre</dt>
            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.complex.name }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Código</dt>
            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ props.complex.code }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Dirección</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ props.complex.address || '-' }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Ciudad</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ props.complex.city || '-' }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Departamento</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ props.complex.state || '-' }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">País</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ props.complex.country_code || '-' }}</dd>
          </div>
          <div v-if="props.complex.legal_identifier" class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Identificador legal</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ props.complex.legal_identifier }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Zona horaria</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ props.complex.timezone || '-' }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Moneda</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ props.complex.currency_code || '-' }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Creado</dt>
            <dd class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(props.complex.created_at) }}</dd>
          </div>
        </dl>
      </div>

      <div class="lg:col-span-2 space-y-6">
        <!-- Modulos habilitados -->
        <div class="rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
            <h3 class="text-sm font-medium text-gray-800 dark:text-white">
              Módulos habilitados
            </h3>
          </div>
          <div class="p-4">
            <div v-if="props.modules.length > 0" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div
                v-for="module in props.modules"
                :key="module.key"
                class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="text-sm font-medium text-gray-800 dark:text-white">{{ module.label }}</span>
                  <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-300">
                    Habilitado
                  </span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ module.description }}</p>
              </div>
            </div>
            <p v-else class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
              No hay módulos habilitados en este conjunto.
            </p>
          </div>
        </div>

        <!-- Roles del catalogo -->
        <div class="overflow-hidden rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
            <h3 class="text-sm font-medium text-gray-800 dark:text-white">
              Roles del catálogo
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Rol
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Clave
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Ámbito
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Estado
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="role in props.roles" :key="role.key" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                  <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">
                    {{ role.name }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ role.key }}</td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ role.scope === 'system' ? 'Plataforma' : 'Conjunto' }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    <span
                      :class="[
                        'rounded-full px-2.5 py-0.5 text-xs font-medium',
                        role.is_active
                          ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                          : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                      ]"
                    >
                      {{ role.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Miembros -->
        <div class="overflow-hidden rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
            <h3 class="text-sm font-medium text-gray-800 dark:text-white">
              Miembros del conjunto
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Miembro
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Roles
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Estado
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="member in props.members" :key="member.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                  <td class="whitespace-nowrap px-4 py-3">
                    <span class="font-medium text-gray-800 dark:text-white">{{ member.name || '-' }}</span>
                    <span class="ml-2 text-sm text-gray-500">{{ member.email }}</span>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ member.roles.length > 0 ? member.roles.join(', ') : '-' }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', getMembershipColor(member.status)]">
                      {{ member.status_label }}
                    </span>
                  </td>
                </tr>
                <tr v-if="props.members.length === 0">
                  <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Este conjunto todavía no tiene miembros.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
