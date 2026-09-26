<script setup>
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
</script>

<template>
  <main class="mx-auto flex max-w-md flex-col gap-4 p-6">
    <h1 class="text-2xl font-semibold">{{ t('ForgotPassword') }}</h1>
    <RouterLink class="text-primary underline" :to="{ name: 'login' }">{{
      t('Log in')
    }}</RouterLink>
  </main>
</template>
