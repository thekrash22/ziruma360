<script setup lang="ts">
import { ref } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import Header from '@/Components/Header.vue';

const sidebarOpen = ref(false);

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value;
};
</script>

<template>
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <Sidebar :sidebar-open="sidebarOpen" @toggle="toggleSidebar" />

    <!-- Main Content Area -->
    <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
      <!-- Header -->
      <Header :sidebar-open="sidebarOpen" @toggle-sidebar="toggleSidebar" />

      <!-- Page Content -->
      <main class="mx-auto w-full max-w-screen-2xl p-4 pb-10 md:p-6 md:pb-6">
        <slot />
      </main>
    </div>
  </div>
</template>
