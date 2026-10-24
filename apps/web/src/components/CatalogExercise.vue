<script setup>
import { computed } from 'vue';
import catalog from 'virtual:public-catalog';
import PublicPageLayout from '@/layouts/PublicPageLayout.vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { useTranslation } from '@/composables/useTranslation';
import {
  catalogExercisePath,
  catalogProgramPath,
  EXERCISE_CATALOG_PATH,
  localizedPath,
} from '@/lib/crawl';
import { exerciseUsage, relatedExercises } from '@/lib/publicCatalog';
import { useAuthStore } from '@/stores/auth';
import { useLocaleStore } from '@/stores/locale';

// A shipped exercise's public page (`/catalog/exercises/{slug}`), built from
// the catalog files rather than the API.
const props = defineProps({
  slug: { type: String, required: true },
});

const { t } = useTranslation();
const auth = useAuthStore();
const localeStore = useLocaleStore();

const text = value => value[localeStore.locale] ?? value.en;
const path = english => localizedPath(english, localeStore.locale);

const exercise = computed(() => catalog.exercises[props.slug]);
const usage = computed(() => exerciseUsage(catalog, props.slug));
const related = computed(() => relatedExercises(catalog, props.slug));
const categories = computed(() =>
  exercise.value.categories.map(category => text(catalog.categories[category].name)).join(', ')
);

usePageMeta({
  title: () => t(':name: how to do it', { name: text(exercise.value.name) }),
  description: () => text(exercise.value.description),
  path: catalogExercisePath(props.slug),
  structuredData: () => ({
    '@type': 'WebPage',
    name: t(':name: how to do it', { name: text(exercise.value.name) }),
    description: text(exercise.value.description),
    about: { '@type': 'Thing', name: text(exercise.value.name) },
  }),
});
</script>

<template>
  <PublicPageLayout>
    <article class="space-y-8 py-2">
      <header class="space-y-3">
        <RouterLink
          :to="path(EXERCISE_CATALOG_PATH)"
          class="text-sm text-primary underline-offset-4 hover:underline"
        >
          {{ t('Exercise catalog') }}
        </RouterLink>
        <h1 class="text-2xl font-semibold text-card-foreground">{{ text(exercise.name) }}</h1>
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
          <div>
            <dt class="text-muted-foreground">{{ t('Equipment') }}</dt>
            <dd class="font-medium text-card-foreground">
              {{ text(catalog.equipment[exercise.equipment].name) }}
            </dd>
          </div>
          <div>
            <dt class="text-muted-foreground">{{ t('Categories') }}</dt>
            <dd class="font-medium text-card-foreground">{{ categories }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">{{ t('Measured by') }}</dt>
            <dd class="font-medium text-card-foreground">
              {{ exercise.effortType === 'duration' ? t('Time') : t('Repetitions') }}
            </dd>
          </div>
        </dl>
      </header>

      <section class="space-y-2">
        <h2 class="text-lg font-semibold text-card-foreground">{{ t('How to do it') }}</h2>
        <p class="leading-7 text-muted-foreground">{{ text(exercise.description) }}</p>
      </section>

      <section v-if="usage.length" class="space-y-3">
        <h2 class="text-lg font-semibold text-card-foreground">{{ t('Used in programs') }}</h2>
        <ul class="divide-y divide-border rounded-lg border border-border">
          <li v-for="item in usage" :key="item.program.slug" class="space-y-1 px-4 py-2 text-sm">
            <RouterLink
              :to="path(catalogProgramPath(item.program.slug))"
              class="font-medium text-primary underline-offset-4 hover:underline"
            >
              {{ text(item.program.name) }}
            </RouterLink>
            <p class="text-muted-foreground">
              {{
                item.templates
                  .map(template => text(catalog.workoutTemplates[template].name))
                  .join(', ')
              }}
            </p>
          </li>
        </ul>
      </section>

      <section v-if="related.length" class="space-y-3">
        <h2 class="text-lg font-semibold text-card-foreground">{{ t('Related exercises') }}</h2>
        <ul class="grid gap-2 text-sm sm:grid-cols-2">
          <li v-for="other in related" :key="other">
            <RouterLink
              :to="path(catalogExercisePath(other))"
              class="text-primary underline-offset-4 hover:underline"
            >
              {{ text(catalog.exercises[other].name) }}
            </RouterLink>
          </li>
        </ul>
      </section>

      <section v-if="!auth.isAuthenticated" class="space-y-3 rounded-lg bg-primary/10 p-4">
        <p class="text-sm text-card-foreground">
          {{ t('Create an account to log this exercise in your workouts and see your history.') }}
        </p>
        <RouterLink
          :to="{ name: 'register' }"
          class="inline-flex rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
        >
          {{ t('Create account') }}
        </RouterLink>
      </section>
    </article>
  </PublicPageLayout>
</template>
