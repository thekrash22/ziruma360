<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
  sidebarOpen: boolean;
}>();

const page = usePage();
const user = (page.props.auth as any)?.user ?? null;
</script>

<template>
  <header
    class="sticky top-0 z-99999 flex w-full items-center border-b border-gray-200 bg-white px-4 xl:px-6 dark:border-gray-800 dark:bg-gray-900"
  >
    <div class="flex w-full items-center justify-between">
      <!-- Left side -->
      <div class="flex items-center gap-4">
        <button
          class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 xl:hidden"
          @click="$emit('toggle-sidebar')"
        >
          <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24">
            <path d="M3.25 6C3.25 5.58579 3.58579 5.25 4 5.25L20 5.25C20.4142 5.25 20.75 5.58579 20.75 6C20.75 6.41421 20.4142 6.75 20 6.75L4 6.75C3.58579 6.75 3.25 6.41422 3.25 6ZM3.25 18C3.25 17.5858 3.58579 17.25 4 17.25L20 17.25C20.4142 17.25 20.75 17.5858 20.75 18C20.75 18.4142 20.4142 18.75 20 18.75L4 18.75C3.58579 18.75 3.25 18.4142 3.25 18ZM4 11.25C3.58579 11.25 3.25 11.5858 3.25 12C3.25 12.4142 3.58579 12.75 4 12.75L12 12.75C12.4142 12.75 12.75 12.4142 12.75 12C12.75 11.5858 12.4142 11.25 12 11.25L4 11.25Z" />
          </svg>
        </button>

        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
          {{ (page.props as any).title || 'Dashboard' }}
        </h2>
      </div>

      <!-- Right side -->
      <div class="flex items-center gap-3">
        <!-- Dark mode toggle -->
        <button
          class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800"
        >
          <svg class="hidden dark:block" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 2.25C5.38578 2.25 1.625 6.00978 1.625 10.625C1.625 15.2402 5.38578 19 10 19C14.6142 19 18.375 15.2402 18.375 10.625C18.375 6.00978 14.6142 2.25 10 2.25Z" />
          </svg>
          <svg class="dark:hidden" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97Z" />
          </svg>
        </button>

        <!-- Notifications -->
        <button
          class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800"
        >
          <span class="absolute top-0.5 right-0.5 z-10 h-2 w-2 rounded-full bg-orange-400"></span>
          <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20">
            <path d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875Z" />
          </svg>
        </button>

        <!-- User -->
        <div class="flex items-center gap-3">
          <Link
            :href="route('profile.edit')"
            class="flex items-center gap-3"
          >
            <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full bg-brand-500 text-sm font-medium text-white">
              {{ user?.name?.charAt(0).toUpperCase() || 'U' }}
            </div>
            <span class="hidden text-sm font-medium text-gray-700 dark:text-gray-300 xl:block">
              {{ user?.name || 'Usuario' }}
            </span>
          </Link>

          <form :action="route('logout')" method="POST" class="hidden xl:block">
            <input type="hidden" name="_token" :value="(page.props as any).csrf_token" />
            <button
              type="submit"
              class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
            >
              <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                <path d="M15.1007 19.247C14.6865 19.247 14.3507 18.9112 14.3507 18.497L14.3507 14.245H12.8507V18.497C12.8507 19.7396 13.8581 20.747 15.1007 20.747H18.5007C19.7434 20.747 20.7507 19.7396 20.7507 18.497L20.7507 5.49609C20.7507 4.25345 19.7433 3.24609 18.5007 3.24609H15.1007C13.8581 3.24609 12.8507 4.25345 12.8507 5.49609V9.74501L14.3507 9.74501V5.49609C14.3507 5.08188 14.6865 4.74609 15.1007 4.74609L18.5007 4.74609C18.9149 4.74609 19.2507 5.08188 19.2507 5.49609L19.2507 18.497C19.2507 18.9112 18.9149 19.247 18.5007 19.247H15.1007Z" />
                <path d="M3.25073 11.9984C3.25073 12.2144 3.34204 12.4091 3.48817 12.546L8.09483 17.1556C8.38763 17.4485 8.86251 17.4487 9.15549 17.1559C9.44848 16.8631 9.44863 16.3882 9.15583 16.0952L5.81116 12.7484L16.0007 12.7484C16.4149 12.7484 16.7507 12.4127 16.7507 11.9984C16.7507 11.5842 16.4149 11.2484 16.0007 11.2484L5.81528 11.2484L9.15585 7.90554C9.44864 7.61255 9.44847 7.13767 9.15547 6.84488C8.86248 6.55209 8.3876 6.55226 8.09481 6.84525L3.52309 11.4202C3.35673 11.5577 3.25073 11.7657 3.25073 11.9984Z" />
              </svg>
            </button>
          </form>
        </div>
      </div>
    </div>
  </header>
</template>
