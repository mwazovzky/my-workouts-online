<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader title="Workout Edit" />
    </template>

    <PageLayout>
      <WorkoutCard :workout="workoutLog" />

      <div>
        <ActivitiesList
          :activities="activities"
          :editable="isEditable"
          @save-activity="onSaveActivity"
          @add-set="payload => onAddSet(payload)"
          @remove-set="payload => onRemoveSet(payload)"
          @update-activity="payload => onUpdateActivity(payload)"
          @remove-activity="onRemoveActivity"
        />
      </div>
    </PageLayout>

    <WorkoutFooter :show="isEditable">
      <Button
        :disabled="isFinishing"
        variant="outline"
        size="lg"
        class="px-8"
        @click="finishWorkout"
      >
        <span v-if="!isFinishing">Complete</span>
        <span v-else>Completing…</span>
      </Button>
    </WorkoutFooter>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import WorkoutCard from '@/Components/WorkoutCard.vue';
import WorkoutFooter from '@/Components/WorkoutFooter.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Button } from '@/Components/ui/button';

const props = defineProps({
  workoutLog: {
    type: Object,
    required: true,
  },
});

const workoutLogId = ref(props.workoutLog.id ?? null);
const activities = ref(
  (props.workoutLog.activities ?? []).map(a => ({
    id: a.id,
    exercise_id: a.exercise_id ?? null,
    exercise_name: a.exercise_name ?? '',
    sets: (a.sets ?? []).map(s => ({
      id: s.id ?? null,
      order: s.order,
      repetitions: s.repetitions,
      weight: s.weight,
    })),
  }))
);

const workoutStatus = ref(props.workoutLog.status ?? null);
const workoutOwnerId = ref(props.workoutLog.user_id ?? null);

// UI flags
const isFinishing = ref(false);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

// editable only when owner and status is in_progress
const isEditable = computed(() => {
  return (
    !!workoutLogId.value &&
    workoutStatus.value === 'in_progress' &&
    workoutOwnerId.value === currentUserId.value
  );
});

const activityForm = useForm({
  sets: [],
});

// Save a single activity (upsert sets)
async function saveActivity(activityId) {
  if (!isEditable.value) {
    alert('This workout cannot be edited');
    return;
  }

  const activity = activities.value.find(a => a.id === activityId);
  if (!activity || !workoutLogId.value) {
    alert('No activity or workout not started');
    return;
  }

  activityForm.sets = activity.sets.map(s => ({
    id: s.id ?? null,
    order: s.order,
    repetitions: s.repetitions,
    weight: s.weight,
  }));

  activityForm.patch(route('activities.update', { activity: activity.id }), {
    preserveScroll: true,
    onError: () => {
      alert('Failed to save activity');
    },
  });
}

// Finish workout via Inertia; update status locally on success
function finishWorkout() {
  if (!workoutLogId.value || isFinishing.value) {
    return;
  }

  isFinishing.value = true;

  router.post(route('workout.logs.complete', { workoutLog: workoutLogId.value }), {
    preserveScroll: true,
    onSuccess: () => {
      workoutStatus.value = 'completed';
    },
    onError: () => {
      alert('Failed to finish workout');
    },
    onFinish: () => {
      isFinishing.value = false;
    },
  });
}

// handlers forwarded from components
function onSaveActivity(id) {
  saveActivity(id);
}

// remove/add set handlers (simple client-side updates)
function onAddSet({ activityId }) {
  const activity = activities.value.find(a => a.id === activityId);
  if (!activity) return;
  const maxOrder = activity.sets.length ? Math.max(...activity.sets.map(s => s.order)) : 0;
  activity.sets.push({ id: null, order: maxOrder + 1, repetitions: 0, weight: 0 });
}

function onRemoveSet({ activityId, id, order }) {
  const activity = activities.value.find(a => a.id === activityId);
  if (!activity) return;
  if (id) {
    activity.sets = activity.sets.filter(s => s.id !== id);
    return;
  }

  activity.sets = activity.sets.filter(s => s.order !== order);
}

function onUpdateActivity(updated) {
  const idx = activities.value.findIndex(a => a.id === updated.id);
  if (idx !== -1) activities.value[idx] = updated;
}

function onRemoveActivity(activityId) {
  if (!isEditable.value) {
    alert('This workout cannot be edited');
    return;
  }

  if (!confirm('Delete this activity? This action cannot be undone.')) {
    return;
  }

  router.delete(route('activities.destroy', { activity: activityId }), {
    preserveScroll: true,
    onSuccess: () => {
      // Remove from local state to keep unsaved changes in other activities
      activities.value = activities.value.filter(a => a.id !== activityId);
    },
    onError: () => {
      alert('Failed to delete activity');
    },
  });
}
</script>
