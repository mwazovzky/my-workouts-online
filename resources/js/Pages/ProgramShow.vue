<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ProgramCard from '@/Components/ProgramCard.vue';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Empty, EmptyDescription, EmptyTitle } from '@/Components/ui/empty';
import { Skeleton } from '@/Components/ui/skeleton';
import { useEnrollment } from '@/composables/useEnrollment';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get } = useApi();
const { enroll } = useEnrollment();

const props = defineProps({
  id: { type: Number, required: true },
});

const program = ref(null);
const workoutTemplates = ref(null);

const isEnrolled = computed(() => program.value?.is_enrolled ?? false);

onMounted(async () => {
  try {
    const { data } = await get(`/api/v1/programs/${props.id}`);
    program.value = data.data;
    workoutTemplates.value = data.data.workout_templates;
  } catch {
    toast.error(t('Failed to load program'));
    router.visit(route('programs.index'));
  }
});

async function enrollInProgram() {
  if ((await enroll(props.id)) && program.value) {
    program.value.is_enrolled = true;
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Program Show')" />
    </template>

    <PageLayout>
      <!-- Program Header skeleton -->
      <div v-if="program === null" class="space-y-4">
        <Skeleton class="h-24 w-full rounded-xl" />
      </div>
      <ProgramCard v-else :program="program" :is-enrolled="isEnrolled" @enroll="enrollInProgram" />

      <!-- Workouts Section -->
      <div class="mb-4">
        <h4 class="font-semibold text-lg mb-4">{{ t('Program Workouts') }}</h4>

        <div v-if="workoutTemplates === null" class="space-y-3">
          <Card v-for="i in 3" :key="i" class="p-4">
            <div
              class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4"
            >
              <div class="flex-1 space-y-2">
                <Skeleton class="h-5 w-48" />
                <Skeleton class="h-4 w-32" />
              </div>
              <Skeleton class="h-9 w-16" />
            </div>
          </Card>
        </div>
        <Empty v-else-if="workoutTemplates.length === 0">
          <EmptyTitle>{{ t('No workouts yet') }}</EmptyTitle>
          <EmptyDescription>{{
            t("This program doesn't have any workouts configured")
          }}</EmptyDescription>
        </Empty>
        <ul v-else class="space-y-3">
          <li v-for="workout in workoutTemplates" :key="workout.id">
            <Card class="p-4">
              <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4"
              >
                <div class="flex-1 min-w-0">
                  <div class="font-medium truncate">
                    {{ workout.name }}
                  </div>
                  <div class="text-sm text-muted-foreground mt-1">
                    {{ t('Scheduled:') }} {{ workout.pivot?.weekday_label ?? t('Not scheduled') }}
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <Button
                    as="a"
                    :href="route('workout.templates.show', { id: workout.id })"
                    variant="outline"
                    size="sm"
                  >
                    {{ t('Show') }}
                  </Button>
                </div>
              </div>
            </Card>
          </li>
        </ul>
      </div>
    </PageLayout>
  </AuthenticatedLayout>
</template>
