<script setup>
import { useTranslation } from '@/composables/useTranslation';
import { localizedPath, PROGRAM_CATALOG_PATH, publicPagePath } from '@/lib/crawl';
import { useLocaleStore } from '@/stores/locale';

const { t } = useTranslation();
const localeStore = useLocaleStore();
</script>

<template>
  <nav class="flex flex-wrap items-center justify-center gap-4 text-xs text-muted-foreground">
    <RouterLink
      :to="localizedPath(PROGRAM_CATALOG_PATH, localeStore.locale)"
      class="underline-offset-4 hover:underline"
    >
      {{ t('Program catalog') }}
    </RouterLink>
    <span aria-hidden="true">·</span>
    <RouterLink
      :to="publicPagePath('privacy', localeStore.locale)"
      class="underline-offset-4 hover:underline"
    >
      {{ t('Privacy Policy') }}
    </RouterLink>
    <span aria-hidden="true">·</span>
    <RouterLink
      :to="publicPagePath('support', localeStore.locale)"
      class="underline-offset-4 hover:underline"
    >
      {{ t('Support') }}
    </RouterLink>
  </nav>
</template>
