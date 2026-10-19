<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

// Sample data for the dashboard
const stats = [
  { label: 'Total Units', value: '156', change: '+12%', positive: true },
  { label: 'Active Residents', value: '324', change: '+8%', positive: true },
  { label: 'Pending Visits', value: '18', change: '-5%', positive: false },
  { label: 'Monthly Revenue', value: '$45.2K', change: '+15%', positive: true },
];

const recentActivity = [
  { user: 'John Doe', action: 'Check-in completed', time: '2 min ago', type: 'checkin' },
  { user: 'Maria Garcia', action: 'New reservation', time: '15 min ago', type: 'reservation' },
  { user: 'Carlos Rodriguez', action: 'Vehicle registered', time: '1 hour ago', type: 'vehicle' },
  { user: 'Ana Martinez', action: 'Package delivered', time: '2 hours ago', type: 'package' },
];
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout>
    <div class="grid grid-cols-12 gap-4 md:gap-6">
      <!-- Stats Cards -->
      <div
        v-for="(stat, index) in stats"
        :key="index"
        class="col-span-12 sm:col-span-6 xl:col-span-3"
      >
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="text-title-md font-semibold text-gray-800 dark:text-white">
                {{ stat.value }}
              </h4>
              <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ stat.label }}
              </span>
            </div>
            <div
              class="flex h-12 w-12 items-center justify-center rounded-full"
              :class="stat.positive ? 'bg-success-500/10 text-success-500' : 'bg-error-500/10 text-error-500'"
            >
              <svg v-if="stat.positive" class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                <path d="M10 2L5 8H8V14H12V8H15L10 2Z" />
              </svg>
              <svg v-else class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                <path d="M10 18L15 12H12V6H8V12H5L10 18Z" />
              </svg>
            </div>
          </div>
          <div class="mt-4 flex items-center gap-2">
            <span
              class="flex items-center gap-1 text-sm font-medium"
              :class="stat.positive ? 'text-success-500' : 'text-error-500'"
            >
              {{ stat.change }}
            </span>
            <span class="text-sm text-gray-500 dark:text-gray-400">vs last month</span>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="col-span-12 xl:col-span-8">
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="mb-6 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
              Recent Activity
            </h3>
            <button class="text-sm font-medium text-brand-500 hover:text-brand-600">
              View All
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800">
                  <th class="pb-4 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                    User
                  </th>
                  <th class="pb-4 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                    Action
                  </th>
                  <th class="pb-4 text-right text-sm font-medium text-gray-500 dark:text-gray-400">
                    Time
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(activity, index) in recentActivity"
                  :key="index"
                  class="border-b border-gray-100 dark:border-gray-800"
                >
                  <td class="py-4">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 overflow-hidden rounded-full bg-brand-500">
                        <div class="flex h-full w-full items-center justify-center text-sm font-medium text-white">
                          {{ activity.user.charAt(0) }}
                        </div>
                      </div>
                      <span class="text-sm font-medium text-gray-800 dark:text-white">
                        {{ activity.user }}
                      </span>
                    </div>
                  </td>
                  <td class="py-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                      {{ activity.action }}
                    </span>
                  </td>
                  <td class="py-4 text-right">
                    <span class="text-sm text-gray-400 dark:text-gray-500">
                      {{ activity.time }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="col-span-12 xl:col-span-4">
        <div class="rounded-sm border border-stroke bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white">
            Quick Actions
          </h3>

          <div class="flex flex-col gap-4">
            <button class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-left transition hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-500/10 text-brand-500">
                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                  <path d="M10 2L5 8H8V14H12V8H15L10 2Z" />
                </svg>
              </div>
              <div>
                <span class="block text-sm font-medium text-gray-800 dark:text-white">
                  Register Visitor
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  Quick check-in
                </span>
              </div>
            </button>

            <button class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-left transition hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-success-500/10 text-success-500">
                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                  <path d="M10 2L5 8H8V14H12V8H15L10 2Z" />
                </svg>
              </div>
              <div>
                <span class="block text-sm font-medium text-gray-800 dark:text-white">
                  New Reservation
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  Create booking
                </span>
              </div>
            </button>

            <button class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-left transition hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500">
                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                  <path d="M10 2L5 8H8V14H12V8H15L10 2Z" />
                </svg>
              </div>
              <div>
                <span class="block text-sm font-medium text-gray-800 dark:text-white">
                  Report Issue
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  Submit maintenance
                </span>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
