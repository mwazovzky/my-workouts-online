<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">View Workout</h2>
    </template>

    <PageLayout>
      <WorkoutCard :workout="workoutLog" />

      <!-- prominent read-only banner -->
      <div class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded" role="status" aria-live="polite">
        This is a read-only view of the workout. To make changes open the editor (available only while the workout is in progress and you are the owner).
      </div>

      <div>
        <ActivitiesList
          v-if="activities.length"
          :activities="activities"
          :editable="false"
          @add-set="() => {}"
          @remove-set="() => {}"
          @update-activity="() => {}"
        />
        <div v-else class="space-y-4">
          <Card v-for="i in 2" :key="i" class="p-4">
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
    </PageLayout>

    <WorkoutFooter :show="canEdit">
      <Button @click="goEdit" :disabled="editingNav" variant="outline" size="lg" class="px-8">
        <span v-if="!editingNav">Continue</span>
        <span v-else>Opening…</span>
      </Button>
    </WorkoutFooter>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import WorkoutCard from '@/Components/WorkoutCard.vue';
import WorkoutFooter from '@/Components/WorkoutFooter.vue';
import PageLayout from '@/Components/PageLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';

const props = defineProps({
  workoutLog: {
    type: Object,
    required: true,
  },
  activities: {
    type: Array,
    default: null,
  },
});

const workoutOwnerId = computed(() => props.workoutLog.user_id ?? null);
const workoutStatus = computed(() => props.workoutLog.status ?? null);
const activities = computed(() => props.activities ?? []);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const editingNav = ref(false);

// allow navigation to editor only for owner and when status is in_progress
const canEdit = computed(() => {
  return !!currentUserId.value && workoutStatus.value === 'in_progress' && workoutOwnerId.value === currentUserId.value;
});

// go to editor (with tiny loading state)
function goEdit() {
  if (!canEdit.value) return;
  editingNav.value = true;
  router.visit(route('workout.logs.edit', { id: props.workoutLog.id }), {
    onFinish: () => {
      editingNav.value = false;
    },
  });
}
</script>
