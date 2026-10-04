<script setup>
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
</script>

<template>
  <nav class="flex items-center justify-center gap-4 text-xs text-muted-foreground">
    <RouterLink :to="{ name: 'privacy' }" class="underline-offset-4 hover:underline">
      {{ t('Privacy Policy') }}
    </RouterLink>
    <span aria-hidden="true">·</span>
    <RouterLink :to="{ name: 'support' }" class="underline-offset-4 hover:underline">
      {{ t('Support') }}
    </RouterLink>
  </nav>
</template>
