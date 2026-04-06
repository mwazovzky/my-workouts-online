<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PageLayout from '@/Components/PageLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import WorkoutFooter from '@/Components/WorkoutFooter.vue';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { Form } from '@inertiajs/vue3';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get } = useApi();

const props = defineProps({
  id: { type: Number, required: true },
});

const workoutTemplate = ref(null);

onMounted(async () => {
  const { data } = await get(`/api/v1/workout-templates/${props.id}`);
  workoutTemplate.value = data.data;
});
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Workout Template Show')" />
    </template>

    <!-- Loading -->
    <div v-if="workoutTemplate === null">
      <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
        <Skeleton class="h-6 w-48 mb-2" />
        <Skeleton class="h-4 w-72" />
      </div>

      <div class="mt-6">
        <Skeleton class="h-5 w-24 mb-2" />
        <div class="mt-2 space-y-3">
          <Card v-for="i in 3" :key="i" class="p-4">
            <div class="mb-4 pb-4 border-b">
              <Skeleton class="h-6 w-64 mb-3" />
              <div class="space-y-2">
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-full" />
              </div>
            </div>
            <Skeleton class="h-10 w-full" />
          </Card>
        </div>
      </div>
    </div>

    <template v-else>
      <PageLayout>
        <div>
          <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h3 class="text-lg font-semibold text-card-foreground">{{ workoutTemplate.name }}</h3>
            <p class="text-sm text-muted-foreground">{{ workoutTemplate.description }}</p>
          </div>

          <div class="mt-6">
            <h4 class="text-md font-semibold text-foreground">{{ t('Activities') }}</h4>
            <div class="mt-2">
              <ActivitiesList :activities="workoutTemplate.activities ?? []" :editable="false" />
            </div>
          </div>
        </div>
      </PageLayout>

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
            <p v-if="errors.workout_template_id" class="text-sm text-destructive">
              {{ errors.workout_template_id }}
            </p>
          </div>
          <input type="hidden" name="workout_template_id" :value="workoutTemplate.id" />
        </Form>
      </WorkoutFooter>
    </template>
  </AuthenticatedLayout>
</template>
