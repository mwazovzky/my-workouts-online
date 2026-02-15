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
          :can-remove-activity="activities.length > 1"
          @set-completion-toggled="onSetCompletionToggled"
          @add-set="payload => onAddSet(payload)"
          @remove-set="payload => onRemoveSet(payload)"
          @update-activity="payload => onUpdateActivity(payload)"
          @remove-activity="onRemoveActivity"
        />
      </div>
    </PageLayout>

    <WorkoutFooter :show="isEditable">
      <div class="flex items-center gap-3">
        <Button
          v-if="isDirty"
          :disabled="isSaving"
          variant="outline"
          size="lg"
          class="px-8"
          @click="saveWorkout"
        >
          <span v-if="!isSaving">Save</span>
          <span v-else>Saving…</span>
        </Button>
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
      </div>
    </WorkoutFooter>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
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
    rest_time_seconds: a.rest_time_seconds ?? null,
    exercise_equipment_name: a.exercise_equipment_name ?? null,
    exercise_category_names: a.exercise_category_names ?? [],
    sets: (a.sets ?? []).map(s => ({
      id: s.id ?? null,
      order: s.order,
      repetitions: s.repetitions,
      weight: s.weight,
      is_completed: s.is_completed ?? false,
    })),
  }))
);

const workoutStatus = ref(props.workoutLog.status ?? null);
const workoutOwnerId = ref(props.workoutLog.user_id ?? null);

// UI flags
const isSaving = ref(false);
const isFinishing = ref(false);
const isDirty = ref(false);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

// Editable only when owner and status is in_progress
const isEditable = computed(() => {
  return (
    !!workoutLogId.value &&
    workoutStatus.value === 'in_progress' &&
    workoutOwnerId.value === currentUserId.value
  );
});

function markDirty() {
  isDirty.value = true;
}

/**
 * Normalize activity and set orders to be sequential starting from 1,
 * and build the payload for the save endpoint.
 */
function buildSavePayload() {
  return {
    activities: activities.value.map((a, aIdx) => ({
      id: a.id ?? undefined,
      exercise_id: a.exercise_id,
      order: aIdx + 1,
      sets: a.sets.map((s, sIdx) => ({
        id: s.id ?? undefined,
        order: sIdx + 1,
        repetitions: Number(s.repetitions),
        weight: Number(s.weight),
        is_completed: s.is_completed ?? false,
      })),
    })),
  };
}

function saveWorkout({ onSuccess, onError } = {}) {
  if (!isEditable.value || isSaving.value) {
    return;
  }

  isSaving.value = true;

  router.patch(route('workout.logs.save', { workoutLog: workoutLogId.value }), buildSavePayload(), {
    preserveScroll: true,
    onSuccess: () => {
      isDirty.value = false;
      onSuccess?.();
    },
    onError: () => {
      onError?.();
      alert('Failed to save workout');
    },
    onFinish: () => {
      isSaving.value = false;
    },
  });
}

function finishWorkout() {
  if (!workoutLogId.value || isFinishing.value) {
    return;
  }

  isFinishing.value = true;

  // If there are unsaved changes, save first then complete
  if (isDirty.value) {
    saveWorkout({
      onSuccess: () => {
        completeWorkout();
      },
      onError: () => {
        isFinishing.value = false;
      },
    });

    return;
  }

  completeWorkout();
}

function completeWorkout() {
  router.post(
    route('workout.logs.complete', { workoutLog: workoutLogId.value }),
    {},
    {
      onFinish: () => {
        isFinishing.value = false;
      },
    }
  );
}

// Set completion toggle — now client-side only (no auto-save)
function onSetCompletionToggled() {
  markDirty();
}

function onAddSet({ activityId }) {
  const activity = activities.value.find(a => a.id === activityId);
  if (!activity) return;

  const maxOrder = activity.sets.length ? Math.max(...activity.sets.map(s => s.order)) : 0;
  activity.sets.push({
    id: null,
    order: maxOrder + 1,
    repetitions: 0,
    weight: 0,
    is_completed: false,
  });
  markDirty();
}

function onRemoveSet({ activityId, id, order }) {
  const activity = activities.value.find(a => a.id === activityId);
  if (!activity) return;

  // If this is the last set, confirm and remove the entire activity
  if (activity.sets.length === 1) {
    if (!confirm('Removing the last set will delete this activity. Continue?')) {
      return;
    }
    activities.value = activities.value.filter(a => a.id !== activityId);
    markDirty();

    return;
  }

  if (id) {
    activity.sets = activity.sets.filter(s => s.id !== id);
  } else {
    activity.sets = activity.sets.filter(s => s.order !== order);
  }
  markDirty();
}

function onUpdateActivity(updated) {
  const idx = activities.value.findIndex(a => a.id === updated.id);
  if (idx !== -1) {
    activities.value[idx] = updated;
    markDirty();
  }
}

function onRemoveActivity(activityId) {
  if (!isEditable.value) {
    alert('This workout cannot be edited');

    return;
  }

  if (activities.value.length <= 1) {
    return;
  }

  if (!confirm('Delete this activity? This action cannot be undone.')) {
    return;
  }

  activities.value = activities.value.filter(a => a.id !== activityId);
  markDirty();
}
</script>
