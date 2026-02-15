<template>
  <nav class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    <NavLink :href="route('dashboard')" :active="route().current('dashboard')"> Dashboard </NavLink>
    <NavLink :href="route('programs.index')" :active="route().current('programs.index')">
      Programs
    </NavLink>
    <NavLink :href="route('workouts.index')" :active="route().current('workouts.index')">
      Workouts
    </NavLink>
  </nav>
</template>

<script setup>
import NavLink from '@/Components/NavLink.vue';
</script>
