<script setup>
import catalog from 'virtual:public-catalog';
import PublicPageLayout from '@/layouts/PublicPageLayout.vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { useTranslation } from '@/composables/useTranslation';
import { catalogProgramPath, localizedPath, PROGRAM_CATALOG_PATH } from '@/lib/crawl';
import { useLocaleStore } from '@/stores/locale';

const { t } = useTranslation();
const localeStore = useLocaleStore();

const text = value => value[localeStore.locale] ?? value.en;

usePageMeta({
  title: () => t('Program catalog'),
  description: () =>
    t(
      'Ready-made training programs for the gym, at home and on the bike, with the weekly schedule and the sets and repetitions of every workout.'
    ),
  path: PROGRAM_CATALOG_PATH,
});
</script>

<template>
  <PublicPageLayout>
    <div class="space-y-6 py-2">
      <header class="space-y-2">
        <h1 class="text-2xl font-semibold text-card-foreground">{{ t('Program catalog') }}</h1>
        <p class="text-muted-foreground">
          {{
            t(
              'Ready-made training programs for the gym, at home and on the bike. Open one to see its weekly schedule and every workout.'
            )
          }}
        </p>
      </header>

      <ul class="space-y-3">
        <li v-for="program in catalog.programs" :key="program.slug">
          <RouterLink
            :to="localizedPath(catalogProgramPath(program.slug), localeStore.locale)"
            class="block rounded-lg border border-border p-4 transition hover:bg-accent"
          >
            <h2 class="font-semibold text-card-foreground">{{ text(program.name) }}</h2>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">
              {{ text(program.description) }}
            </p>
            <p class="mt-2 text-xs font-medium text-muted-foreground">
              {{ t('Workouts a week: :count', { count: program.schedule.length }) }}
            </p>
          </RouterLink>
        </li>
      </ul>
    </div>
  </PublicPageLayout>
</template>
