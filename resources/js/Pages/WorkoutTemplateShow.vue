<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import WorkoutFooter from '@/Components/WorkoutFooter.vue';
import { Button } from '@/Components/ui/button';
import { Form } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
  workout: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Workout Template Show')" />
    </template>

    <div>
      <div class="p-4 bg-white dark:bg-gray-800 rounded shadow-sm">
        <h3 class="font-semibold text-lg dark:text-gray-100">{{ workout.name }}</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ workout.description }}</p>
      </div>

      <div class="mt-6">
        <h4 class="font-semibold text-md dark:text-gray-200">{{ t('Activities') }}</h4>
        <div class="mt-2">
          <ActivitiesList :activities="workout.activities ?? []" :editable="false" />
        </div>
      </div>
    </div>

    <WorkoutFooter :show="true">
      <Form
        v-slot="{ errors, processing }"
        :action="route('workouts.store')"
        method="post"
        preserve-scroll
      >
        <div class="flex flex-col gap-2 w-full">
          <Button type="submit" size="lg" class="px-8" :disabled="processing">
            <span v-if="!processing">{{ t('Start Workout') }}</span>
            <span v-else>{{ t('Starting…') }}</span>
          </Button>
          <p v-if="errors.workout_template_id" class="text-sm text-red-600 dark:text-red-400">
            {{ errors.workout_template_id }}
          </p>
        </div>
        <input type="hidden" name="workout_template_id" :value="props.workout.id" />
      </Form>
    </WorkoutFooter>
  </AuthenticatedLayout>
</template>
