<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ProgramCard from '@/Components/ProgramCard.vue';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Empty, EmptyDescription, EmptyTitle } from '@/Components/ui/empty';
import { Skeleton } from '@/Components/ui/skeleton';
import { useEnrollment } from '@/composables/useEnrollment';

const props = defineProps({
  program: {
    type: Object,
    required: true,
  },
  workouts: {
    type: Array,
    default: null,
  },
});

const isEnrolled = computed(() => {
  return props.program.is_enrolled ?? false;
});

const { enroll } = useEnrollment({ only: ['program'] });
const enrollInProgram = () => enroll(props.program.id);
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader title="Program Show" />
    </template>

    <PageLayout>
      <!-- Program Header -->
      <ProgramCard :program="program" :is-enrolled="isEnrolled" @enroll="enrollInProgram" />

      <!-- Workouts Section -->
      <div class="mb-4">
        <h4 class="font-semibold text-lg mb-4">Program Workouts</h4>

        <div v-if="workouts === null" class="space-y-3">
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
        <Empty v-else-if="workouts.length === 0">
          <EmptyTitle>No workouts yet</EmptyTitle>
          <EmptyDescription>This program doesn't have any workouts configured</EmptyDescription>
        </Empty>
        <ul v-else class="space-y-3">
          <li v-for="workout in workouts" :key="workout.id">
            <Card class="p-4">
              <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4"
              >
                <div class="flex-1 min-w-0">
                  <div class="font-medium truncate">
                    {{ workout.name }}
                  </div>
                  <div class="text-sm text-muted-foreground mt-1">
                    Scheduled: {{ workout.pivot?.weekday ?? 'Not scheduled' }}
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <Button
                    as="a"
                    :href="route('workout.templates.show', { id: workout.id })"
                    variant="outline"
                    size="sm"
                  >
                    Show
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
