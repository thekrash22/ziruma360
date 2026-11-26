<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AuditRow {
  id: number;
  action: string;
  entity_type: string;
  entity_id: string;
  occurred_at: string;
  correlation_id: string | null;
  actor: { id: number; name: string } | null;
  before_data: Record<string, unknown> | null;
  after_data: Record<string, unknown> | null;
}

interface PageProps {
  logs: {
    data: AuditRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  filters: {
    action: string | null;
    entity_type: string | null;
    entity_id: string | null;
    from: string | null;
    to: string | null;
  };
  actions: Array<{ value: string; label: string }>;
  entityTypes: Array<{ value: string; label: string }>;
}

const props = defineProps<PageProps>();

const selected = ref<AuditRow | null>(null);

const action = ref(props.filters.action ?? '');
const entityType = ref(props.filters.entity_type ?? '');
const entityId = ref(props.filters.entity_id ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const actionLabel = (value: string) =>
  props.actions.find((item) => item.value === value)?.label ?? value;

const entityLabel = (value: string) =>
  props.entityTypes.find((item) => item.value === value)?.label ?? value.split('\\').pop() ?? value;

const actionClass = (value: string) => {
  const classes: Record<string, string> = {
    created: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    updated: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    deleted: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
  };

  return classes[value] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
};

const changedKeys = (row: AuditRow): string[] => {
  const before = row.before_data ?? {};
  const after = row.after_data ?? {};

  return [...new Set([...Object.keys(before), ...Object.keys(after)])].filter(
    (key) => JSON.stringify(before[key]) !== JSON.stringify(after[key]),
  );
};

const summary = (row: AuditRow): string => {
  const keys = changedKeys(row);

  if (keys.length === 0) {
    return 'Sin cambios de valor';
  }

  const shown = keys.slice(0, 3).join(', ');

  return keys.length > 3 ? `${shown} y ${keys.length - 3} más` : shown;
};

const formatValue = (value: unknown): string => {
  if (value === null || value === undefined) {
    return '—';
  }

  if (typeof value === 'object') {
    return JSON.stringify(value);
  }

  return String(value);
};

const formatMoment = (value: string): string =>
  new Date(value).toLocaleString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });

const applyFilters = () => {
  const params: Record<string, string> = {};

  if (action.value) params.action = action.value;
  if (entityType.value) params.entity_type = entityType.value;
  if (entityId.value) params.entity_id = entityId.value;
  if (from.value) params.from = from.value;
  if (to.value) params.to = to.value;

  router.get(route('auditoria.index'), params, { preserveState: true });
};

const clearFilters = () => {
  action.value = '';
  entityType.value = '';
  entityId.value = '';
  from.value = '';
  to.value = '';

  router.get(route('auditoria.index'), {}, { preserveState: true });
};

const detailRows = computed(() => {
  if (selected.value === null) {
    return [];
  }

  const before = selected.value.before_data ?? {};
  const after = selected.value.after_data ?? {};

  return [...new Set([...Object.keys(before), ...Object.keys(after)])].map((key) => ({
    key,
    before: formatValue(before[key]),
    after: formatValue(after[key]),
  }));
});
</script>

<template>
  <Head title="Auditoría" />

  <AppLayout>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-title-md font-semibold text-gray-800 dark:text-white">
          Auditoría
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Historial de cambios del conjunto: quién hizo qué, cuándo y sobre qué registro.
        </p>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-sm border border-stroke bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
        <div class="flex-1">
          <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400" for="filter-action">
            Acción
          </label>
          <select
            id="filter-action"
            v-model="action"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          >
            <option value="">Todas las acciones</option>
            <option v-for="item in props.actions" :key="item.value" :value="item.value">
              {{ item.label }}
            </option>
          </select>
        </div>

        <div class="flex-1">
          <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400" for="filter-entity">
            Entidad
          </label>
          <select
            id="filter-entity"
            v-model="entityType"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          >
            <option value="">Todas las entidades</option>
            <option v-for="item in props.entityTypes" :key="item.value" :value="item.value">
              {{ item.label }}
            </option>
          </select>
        </div>

        <div class="flex-1">
          <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400" for="filter-entity-id">
            Id de la entidad
          </label>
          <input
            id="filter-entity-id"
            v-model="entityId"
            type="text"
            placeholder="Ej.: 42"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </div>

        <div class="flex-1">
          <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400" for="filter-from">
            Desde
          </label>
          <input
            id="filter-from"
            v-model="from"
            type="date"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </div>

        <div class="flex-1">
          <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400" for="filter-to">
            Hasta
          </label>
          <input
            id="filter-to"
            v-model="to"
            type="date"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </div>

        <div class="flex gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
            @click="applyFilters"
          >
            Filtrar
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            @click="clearFilters"
          >
            Limpiar
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Fecha
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Actor
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Acción
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Entidad
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Cambios
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Detalle
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr
              v-for="row in props.logs.data"
              :key="row.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
            >
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ formatMoment(row.occurred_at) }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ row.actor?.name ?? 'Sistema' }}
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', actionClass(row.action)]">
                  {{ actionLabel(row.action) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium text-gray-800 dark:text-white">{{ entityLabel(row.entity_type) }}</span>
                <span class="ml-2 text-xs text-gray-500">#{{ row.entity_id }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ summary(row) }}
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-right">
                <button
                  type="button"
                  class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                  title="Ver detalle"
                  @click="selected = row"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
              </td>
            </tr>
            <tr v-if="props.logs.data.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No hay registros de auditoría para estos filtros.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="props.logs.last_page > 1"
        class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
      >
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Mostrando {{ props.logs.from }} a {{ props.logs.to }} de {{ props.logs.total }} resultados
        </div>
        <div class="flex gap-1">
          <a
            v-for="(link, index) in props.logs.links"
            :key="index"
            :href="link.url || '#'"
            :class="[
              'rounded-lg px-3 py-1.5 text-sm',
              link.active
                ? 'bg-brand-500 text-white'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
              !link.url ? 'pointer-events-none opacity-50' : ''
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- Detail -->
    <Modal :show="selected !== null" max-width="2xl" @close="selected = null">
      <div v-if="selected" class="p-6">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
              {{ entityLabel(selected.entity_type) }} #{{ selected.entity_id }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ actionLabel(selected.action) }} por {{ selected.actor?.name ?? 'Sistema' }} —
              {{ formatMoment(selected.occurred_at) }}
            </p>
            <p v-if="selected.correlation_id" class="mt-1 text-xs text-gray-400">
              Correlación: {{ selected.correlation_id }}
            </p>
          </div>
          <button
            type="button"
            class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-300"
            title="Cerrar"
            @click="selected = null"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="overflow-hidden rounded-sm border border-gray-200 dark:border-gray-800">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Campo
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Antes
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Después
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <tr v-for="field in detailRows" :key="field.key">
                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">
                  {{ field.key }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                  {{ field.before }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                  {{ field.after }}
                </td>
              </tr>
              <tr v-if="detailRows.length === 0">
                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                  Este registro no guarda valores.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>
