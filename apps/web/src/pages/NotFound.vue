<script setup>
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
</script>

<template>
  <main class="mx-auto flex max-w-md flex-col gap-4 p-6 text-center">
    <h1 class="text-2xl font-semibold">404</h1>
    <RouterLink class="text-primary underline" :to="{ name: 'welcome' }">{{
      t('Home')
    }}</RouterLink>
  </main>
</template>
