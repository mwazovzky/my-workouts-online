<template>
  <nav class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
      {{ t('Dashboard') }}
    </NavLink>
    <NavLink :href="route('programs.index')" :active="route().current('programs.index')">
      {{ t('Programs') }}
    </NavLink>
    <NavLink :href="route('workouts.index')" :active="route().current('workouts.index')">
      {{ t('Workouts') }}
    </NavLink>
    <NavLink :href="route('about')" :active="route().current('about')">
      {{ t('About') }}
    </NavLink>
    <NavLink v-if="isAdmin" :href="route('admin.index')" :active="route().current('admin.*')">
      {{ t('Admin') }}
    </NavLink>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import NavLink from '@/Components/NavLink.vue';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const page = usePage();

const isAdmin = computed(() => page.props.auth.user?.is_admin ?? false);
</script>
