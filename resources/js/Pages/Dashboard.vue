<script setup>
import { ref, onMounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PageLayout from '@/Components/PageLayout.vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Empty, EmptyDescription, EmptyTitle } from '@/Components/ui/empty';
import { Skeleton } from '@/Components/ui/skeleton';
import { formatDate, formatDateOnly } from '@/utils/date';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get, post } = useApi();

const loading = ref(true);
const upcomingSchedule = ref([]);
const inProgressWorkout = ref(null);
const recentWorkouts = ref([]);
const summary = ref({
  enrolled_programs_count: 0,
  completed_workouts_count: 0,
  completed_last_7_days_count: 0,
  upcoming_workouts_count: 0,
});

onMounted(async () => {
  try {
    const { data } = await get('/api/v1/dashboard');
    upcomingSchedule.value = data.data.upcoming_schedule;
    inProgressWorkout.value = data.data.in_progress_workout;
    recentWorkouts.value = data.data.recent_workouts;
    summary.value = data.data.summary;
  } catch {
    toast.error(t('Failed to load dashboard'));
  } finally {
    loading.value = false;
  }
});

function resolveWorkoutName(workout) {
  return workout.name ?? workout.workout_template?.name ?? t('Workout');
}

const startingWorkoutId = ref(null);

async function startWorkout(workoutTemplateId) {
  if (startingWorkoutId.value) return;
  startingWorkoutId.value = workoutTemplateId;
  try {
    const { data } = await post('/api/v1/workouts', { workout_template_id: workoutTemplateId });
    router.visit(route('workouts.edit', { id: data.data.id }));
  } catch {
    toast.error(t('Failed to start workout'));
    startingWorkoutId.value = null;
  }
}
</script>

<template>
  <Head :title="t('Dashboard')" />

  <AuthenticatedLayout>
    <template #header>
      <div class="space-y-1">
        <PageHeader :title="t('Your training home')" />
        <p class="text-sm text-muted-foreground">
          {{
            t(
              'See what is scheduled next, keep an eye on unfinished work, and review recent sessions.'
            )
          }}
        </p>
      </div>
    </template>

    <PageLayout>
      <div class="space-y-6">
        <!-- In-progress workout -->
        <template v-if="loading">
          <Skeleton class="h-32 w-full rounded-xl" />
        </template>
        <Card
          v-else-if="inProgressWorkout"
          class="border-primary/15 bg-gradient-to-r from-primary/5 via-accent/40 to-background p-5 shadow-sm"
        >
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <Badge variant="info">{{ t('Resume unfinished workout') }}</Badge>
              <div class="mt-2 text-xl font-semibold">
                {{ resolveWorkoutName(inProgressWorkout) }}
              </div>
              <p class="mt-2 text-sm text-muted-foreground">
                {{ t('Continue where you left off before starting something new.') }}
              </p>
              <p class="mt-2 text-sm text-muted-foreground">
                {{ t('Last updated') }}: {{ formatDate(inProgressWorkout.updated_at) }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <Button
                as="a"
                :href="route('workouts.edit', { id: inProgressWorkout.id })"
                variant="default"
              >
                {{ t('Continue') }}
              </Button>
              <Button
                as="a"
                :href="route('workouts.show', { id: inProgressWorkout.id })"
                variant="outline"
              >
                {{ t('Show') }}
              </Button>
            </div>
          </div>
        </Card>

        <!-- Coming up -->
        <section class="space-y-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h3 class="text-xl font-semibold text-foreground">
                {{ t('Coming up') }}
              </h3>
              <p class="text-sm text-muted-foreground">
                {{ t('Next 7 days') }}
              </p>
            </div>

            <Button as="a" :href="route('programs.index')" variant="outline" size="sm">
              {{ t('Browse programs') }}
            </Button>
          </div>

          <div v-if="loading" class="space-y-3">
            <Skeleton v-for="i in 2" :key="i" class="h-24 w-full rounded-xl" />
          </div>
          <Empty v-else-if="upcomingSchedule.length === 0" class="bg-card">
            <EmptyTitle>{{ t('No workouts scheduled for the next 7 days') }}</EmptyTitle>
            <EmptyDescription>{{
              t('Enroll in a program to build your upcoming schedule.')
            }}</EmptyDescription>
            <Button as="a" :href="route('programs.index')" variant="default">
              {{ t('Browse programs') }}
            </Button>
          </Empty>
          <ul v-else class="space-y-3">
            <li v-for="item in upcomingSchedule" :key="item.id">
              <Card class="p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <Badge variant="info">{{ item.weekday_label }}</Badge>
                      <Badge variant="outline">{{ formatDateOnly(item.scheduled_at) }}</Badge>
                    </div>
                    <div class="mt-3 text-lg font-semibold text-foreground">
                      {{ item.workout_name }}
                    </div>
                    <div
                      class="mt-2 flex flex-col gap-1 text-sm text-muted-foreground sm:flex-row sm:flex-wrap sm:gap-4"
                    >
                      <span>{{ t('Program') }}: {{ item.program_name }}</span>
                      <span>{{ t('Scheduled for') }}: {{ formatDateOnly(item.scheduled_at) }}</span>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    <Button
                      as="a"
                      :href="route('workout.templates.show', { id: item.workout_template_id })"
                      variant="outline"
                      size="sm"
                    >
                      {{ t('Open template') }}
                    </Button>

                    <Button
                      size="sm"
                      :disabled="startingWorkoutId === item.workout_template_id"
                      @click="startWorkout(item.workout_template_id)"
                    >
                      <span v-if="startingWorkoutId !== item.workout_template_id">{{
                        t('Start Workout')
                      }}</span>
                      <span v-else>{{ t('Starting…') }}</span>
                    </Button>
                  </div>
                </div>
              </Card>
            </li>
          </ul>
        </section>

        <!-- Summary stats -->
        <section class="grid gap-4 md:grid-cols-3">
          <Card class="p-5">
            <div class="text-sm font-medium text-muted-foreground">
              {{ t('Programs enrolled') }}
            </div>
            <div class="mt-3 text-3xl font-semibold text-foreground">
              <Skeleton v-if="loading" class="h-9 w-16" />
              <template v-else>{{ summary.enrolled_programs_count }}</template>
            </div>
          </Card>

          <Card class="p-5">
            <div class="text-sm font-medium text-muted-foreground">
              {{ t('Completed workouts') }}
            </div>
            <div class="mt-3 text-3xl font-semibold text-foreground">
              <Skeleton v-if="loading" class="h-9 w-16" />
              <template v-else>{{ summary.completed_workouts_count }}</template>
            </div>
          </Card>

          <Card class="p-5">
            <div class="text-sm font-medium text-muted-foreground">
              {{ t('Completed in last 7 days') }}
            </div>
            <div class="mt-3 text-3xl font-semibold text-foreground">
              <Skeleton v-if="loading" class="h-9 w-16" />
              <template v-else>{{ summary.completed_last_7_days_count }}</template>
            </div>
          </Card>
        </section>

        <!-- Recent workouts -->
        <section class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-semibold text-foreground">
                {{ t('Recent workouts') }}
              </h3>
            </div>

            <Button as="a" :href="route('workouts.index')" variant="ghost" size="sm">
              {{ t('View all workouts') }}
            </Button>
          </div>

          <div v-if="loading" class="space-y-3">
            <Skeleton v-for="i in 3" :key="i" class="h-20 w-full rounded-xl" />
          </div>
          <Empty v-else-if="recentWorkouts.length === 0" class="bg-card">
            <EmptyTitle>{{ t('No workouts yet') }}</EmptyTitle>
            <EmptyDescription>{{ t('Start a workout to see it listed here') }}</EmptyDescription>
          </Empty>
          <ul v-else class="space-y-3">
            <li v-for="workout in recentWorkouts" :key="workout.id">
              <Card class="p-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div class="min-w-0 flex-1">
                    <div class="text-base font-semibold text-foreground">
                      {{ resolveWorkoutName(workout) }}
                    </div>
                    <div
                      class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                    >
                      <span>{{ formatDate(workout.created_at) }}</span>
                      <span>·</span>
                      <span>{{
                        t(':count activities', { count: workout.activities_count ?? 0 })
                      }}</span>
                      <Badge :variant="workout.status === 'completed' ? 'success' : 'warning'">
                        {{ workout.status_label }}
                      </Badge>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center gap-2">
                    <Button
                      as="a"
                      :href="route('workouts.show', { id: workout.id })"
                      variant="outline"
                      size="sm"
                    >
                      {{ t('Show') }}
                    </Button>
                    <Button
                      v-if="workout.status === 'in_progress'"
                      as="a"
                      :href="route('workouts.edit', { id: workout.id })"
                      size="sm"
                    >
                      {{ t('Continue') }}
                    </Button>
                  </div>
                </div>
              </Card>
            </li>
          </ul>
        </section>
      </div>
    </PageLayout>
  </AuthenticatedLayout>
</template>
