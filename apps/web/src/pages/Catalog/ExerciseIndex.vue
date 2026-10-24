<script setup>
import { computed, onMounted, ref } from 'vue';
import catalog from 'virtual:public-catalog';
import PublicPageLayout from '@/layouts/PublicPageLayout.vue';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { usePageMeta } from '@/composables/usePageMeta';
import { useTranslation } from '@/composables/useTranslation';
import { catalogExercisePath, EXERCISE_CATALOG_PATH, localizedPath } from '@/lib/crawl';
import { exercisesByCategory } from '@/lib/publicCatalog';
import { useLocaleStore } from '@/stores/locale';

const { t } = useTranslation();
const localeStore = useLocaleStore();

const text = value => value[localeStore.locale] ?? value.en;

usePageMeta({
  title: () => t('Exercise catalog'),
  description: () =>
    t(
      'How to do the exercises in the program catalog, for the gym, at home and on the bike, with the equipment and muscles each one works.'
    ),
  path: EXERCISE_CATALOG_PATH,
});

const groups = exercisesByCategory(catalog);

// The filter needs JavaScript, so it appears once the app has loaded; the
// prerendered page is the full grouped list.
const interactive = ref(false);
const query = ref('');
const equipment = ref('');

onMounted(() => {
  interactive.value = true;
});

const equipmentOptions = computed(() =>
  Object.entries(catalog.equipment)
    .map(([slug, item]) => ({ slug, name: text(item.name) }))
    .sort((a, b) => a.name.localeCompare(b.name, localeStore.locale))
);

function matches(slug) {
  const exercise = catalog.exercises[slug];
  const needle = query.value.trim().toLocaleLowerCase(localeStore.locale);

  return (
    (!equipment.value || exercise.equipment === equipment.value) &&
    (!needle || text(exercise.name).toLocaleLowerCase(localeStore.locale).includes(needle))
  );
}

const visibleCount = computed(
  () => groups.flatMap(group => group.exercises).filter(matches).length
);
</script>

<template>
  <PublicPageLayout>
    <div class="space-y-6 py-2">
      <header class="space-y-2">
        <h1 class="text-2xl font-semibold text-card-foreground">{{ t('Exercise catalog') }}</h1>
        <p class="text-muted-foreground">
          {{
            t(
              'Every exercise in the program catalog, grouped by the muscles it works. Open one to see how to do it.'
            )
          }}
        </p>
      </header>

      <form v-if="interactive" class="grid gap-3 sm:grid-cols-2" role="search" @submit.prevent>
        <Input
          v-model="query"
          type="search"
          :aria-label="t('Exercise name')"
          :placeholder="t('Exercise name')"
        />
        <Select v-model="equipment" :aria-label="t('Equipment')">
          <option value="">{{ t('All equipment') }}</option>
          <option v-for="option in equipmentOptions" :key="option.slug" :value="option.slug">
            {{ option.name }}
          </option>
        </Select>
      </form>

      <p v-if="interactive && visibleCount === 0" class="text-sm text-muted-foreground">
        {{ t('No exercises match the filter.') }}
      </p>

      <section
        v-for="group in groups"
        v-show="group.exercises.some(matches)"
        :key="group.category"
        class="space-y-2"
      >
        <h2 :id="group.category" class="text-lg font-semibold text-card-foreground">
          {{ text(catalog.categories[group.category].name) }}
        </h2>
        <ul class="divide-y divide-border rounded-lg border border-border">
          <li
            v-for="slug in group.exercises"
            v-show="matches(slug)"
            :key="slug"
            class="flex items-baseline justify-between gap-4 px-4 py-2 text-sm"
          >
            <RouterLink
              :to="localizedPath(catalogExercisePath(slug), localeStore.locale)"
              class="font-medium text-primary underline-offset-4 hover:underline"
            >
              {{ text(catalog.exercises[slug].name) }}
            </RouterLink>
            <span class="shrink-0 text-right text-muted-foreground">
              {{ text(catalog.equipment[catalog.exercises[slug].equipment].name) }}
            </span>
          </li>
        </ul>
      </section>
    </div>
  </PublicPageLayout>
</template>
