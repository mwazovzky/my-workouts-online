<template>
  <nav class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    <NavLink :to="{ name: 'dashboard' }" :active="isActive('dashboard')">
      {{ t('Dashboard') }}
    </NavLink>
    <NavLink :to="{ name: 'programs.index' }" :active="isActive('programs.index')">
      {{ t('Programs') }}
    </NavLink>
    <NavLink :to="{ name: 'workouts.index' }" :active="isActive('workouts.index')">
      {{ t('Workouts') }}
    </NavLink>
    <NavLink :to="{ name: 'about' }" :active="isActive('about')">
      {{ t('About') }}
    </NavLink>
    <NavLink v-if="auth.isAdmin" :to="{ name: 'admin.index' }" :active="isActive('admin.*')">
      {{ t('Admin') }}
    </NavLink>
  </nav>
</template>

<script setup>
import NavLink from '@/components/NavLink.vue';
import { useActiveRoute } from '@/composables/useActiveRoute';
import { useTranslation } from '@/composables/useTranslation';
import { useAuthStore } from '@/stores/auth';

const { t } = useTranslation();
const { isActive } = useActiveRoute();
const auth = useAuthStore();
</script>
