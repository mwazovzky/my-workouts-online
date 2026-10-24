<script setup>
import { computed } from 'vue';
import catalog from 'virtual:public-catalog';
import PublicPageLayout from '@/layouts/PublicPageLayout.vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { useTranslation } from '@/composables/useTranslation';
import { catalogProgramPath, localizedPath, PROGRAM_CATALOG_PATH } from '@/lib/crawl';
import { weekdayLabel } from '@/lib/enumLabels';
import { plannedSetsLabel, programEquipment, programTemplates } from '@/lib/publicCatalog';
import { useAuthStore } from '@/stores/auth';
import { useLocaleStore } from '@/stores/locale';

// A shipped program's public page (`/catalog/programs/{slug}`), built from
// the catalog files rather than the API.
const props = defineProps({
  program: { type: Object, required: true },
});

const { t } = useTranslation();
const auth = useAuthStore();
const localeStore = useLocaleStore();

const text = value => value[localeStore.locale] ?? value.en;

const templates = computed(() => programTemplates(catalog, props.program));
const equipment = computed(() =>
  programEquipment(catalog, props.program)
    .map(item => text(item.name))
    .join(', ')
);

function setsLabel(activity) {
  const exercise = catalog.exercises[activity.exercise];

  return plannedSetsLabel(
    t,
    activity.sets,
    exercise.effortType,
    catalog.equipment[exercise.equipment].difficultyUnit
  );
}

usePageMeta({
  title: () => text(props.program.name),
  description: () => text(props.program.description),
  path: catalogProgramPath(props.program.slug),
  structuredData: () => ({
    '@type': 'CreativeWork',
    name: text(props.program.name),
    description: text(props.program.description),
  }),
});
</script>

<template>
  <PublicPageLayout>
    <article class="space-y-8 py-2">
      <header class="space-y-3">
        <RouterLink
          :to="localizedPath(PROGRAM_CATALOG_PATH, localeStore.locale)"
          class="text-sm text-primary underline-offset-4 hover:underline"
        >
          {{ t('Program catalog') }}
        </RouterLink>
        <h1 class="text-2xl font-semibold text-card-foreground">{{ text(program.name) }}</h1>
        <p class="leading-7 text-muted-foreground">{{ text(program.description) }}</p>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-muted-foreground">{{ t('Workouts a week') }}</dt>
            <dd class="font-medium text-card-foreground">{{ program.schedule.length }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">{{ t('Equipment') }}</dt>
            <dd class="font-medium text-card-foreground">{{ equipment }}</dd>
          </div>
        </dl>
      </header>

      <section class="space-y-3">
        <h2 class="text-lg font-semibold text-card-foreground">{{ t('Weekly schedule') }}</h2>
        <ul class="divide-y divide-border rounded-lg border border-border">
          <li
            v-for="(day, index) in program.schedule"
            :key="index"
            class="flex items-baseline justify-between gap-4 px-4 py-2 text-sm"
          >
            <span class="text-muted-foreground">{{ weekdayLabel(t, day.weekday) }}</span>
            <a
              :href="`#${day.template}`"
              class="text-right font-medium text-primary underline-offset-4 hover:underline"
            >
              {{ text(catalog.workoutTemplates[day.template].name) }}
            </a>
          </li>
        </ul>
      </section>

      <section class="space-y-6">
        <h2 class="text-lg font-semibold text-card-foreground">{{ t('Workouts') }}</h2>
        <section
          v-for="template in templates"
          :id="template.slug"
          :key="template.slug"
          class="scroll-mt-6 space-y-2"
        >
          <h3 class="font-semibold text-card-foreground">{{ text(template.name) }}</h3>
          <p class="text-sm leading-6 text-muted-foreground">{{ text(template.description) }}</p>
          <ol class="divide-y divide-border rounded-lg border border-border">
            <li
              v-for="(activity, index) in template.activities"
              :key="index"
              class="flex items-baseline justify-between gap-4 px-4 py-2 text-sm"
            >
              <span class="text-card-foreground">
                {{ text(catalog.exercises[activity.exercise].name) }}
              </span>
              <span class="shrink-0 text-right text-muted-foreground">
                {{ setsLabel(activity) }}
              </span>
            </li>
          </ol>
        </section>
      </section>

      <section class="space-y-3 rounded-lg bg-primary/10 p-4">
        <template v-if="auth.isAuthenticated">
          <p class="text-sm text-card-foreground">
            {{
              t('Find this program in Programs and enroll to see its workouts on your dashboard.')
            }}
          </p>
          <RouterLink
            :to="{ name: 'programs.index' }"
            class="inline-flex rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
          >
            {{ t('Open Programs') }}
          </RouterLink>
        </template>
        <template v-else>
          <p class="text-sm text-card-foreground">
            {{ t('Create an account to follow this program and log every set.') }}
          </p>
          <RouterLink
            :to="{ name: 'register' }"
            class="inline-flex rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
          >
            {{ t('Create account') }}
          </RouterLink>
        </template>
      </section>
    </article>
  </PublicPageLayout>
</template>
