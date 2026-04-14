<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Workout Edit')" />
    </template>

    <PageLayout>
      <!-- Loading -->
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-24 w-full rounded-xl" />
        <Skeleton class="h-2 w-full rounded-full" />
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

      <template v-else>
        <WorkoutCard :workout="workout" />

        <div v-if="totalSets > 0" class="mb-3 flex items-center gap-3">
          <div class="flex-1 h-2 rounded-full bg-muted overflow-hidden">
            <div
              class="h-full rounded-full bg-primary transition-all duration-300"
              :style="{ width: progressPercent + '%' }"
            />
          </div>
          <span class="text-xs text-muted-foreground tabular-nums whitespace-nowrap">
            {{
              t(':completed/:total sets', {
                completed: completedSets,
                total: totalSets,
              })
            }}
          </span>
        </div>

        <div>
          <ActivitiesList
            :activities="activities"
            :editable="isEditable"
            :reorderable="isEditable && activities.length > 1"
            :can-remove-activity="activities.length > 1"
            @reorder="markDirty"
            @set-completion-toggled="onSetCompletionToggled"
            @add-set="payload => onAddSet(payload)"
            @remove-set="payload => onRemoveSet(payload)"
            @update-activity="payload => onUpdateActivity(payload)"
            @remove-activity="onRemoveActivity"
          />
        </div>
      </template>
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
          <span v-if="!isSaving">{{ t('Save') }}</span>
          <span v-else>{{ t('Saving…') }}</span>
        </Button>
        <Button
          :disabled="isFinishing"
          variant="default"
          size="lg"
          class="px-8"
          @click="finishWorkout"
        >
          <span v-if="!isFinishing">{{ t('Complete') }}</span>
          <span v-else>{{ t('Completing…') }}</span>
        </Button>
      </div>
    </WorkoutFooter>

    <ConfirmDialog
      :open="confirmDialog.open"
      :title="confirmDialog.title"
      :description="confirmDialog.description"
      :confirm-label="confirmDialog.confirmLabel"
      @confirm="onConfirmDialogConfirm"
      @cancel="onConfirmDialogCancel"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { useApi } from '@/composables/useApi';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import WorkoutCard from '@/Components/WorkoutCard.vue';
import WorkoutFooter from '@/Components/WorkoutFooter.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get, patch, post } = useApi();

const props = defineProps({
  id: { type: Number, required: true },
});

const isLoading = ref(true);
const workout = ref(null);
const workoutId = ref(null);
const activities = ref([]);
const workoutStatus = ref(null);
const workoutOwnerId = ref(null);

// UI flags
const isSaving = ref(false);
const isFinishing = ref(false);
const isDirty = ref(false);
const skipNavigationGuard = ref(false);
const isRedirectingAfterLoadError = ref(false);

// Confirm dialog state
const confirmDialog = ref({
  open: false,
  title: '',
  description: '',
  confirmLabel: '',
  onConfirm: null,
});

function openConfirm({ title, description, confirmLabel = t('Continue'), onConfirm }) {
  confirmDialog.value = { open: true, title, description, confirmLabel, onConfirm };
}

function onConfirmDialogConfirm() {
  const callback = confirmDialog.value.onConfirm;
  confirmDialog.value.open = false;
  callback?.();
}

function onConfirmDialogCancel() {
  confirmDialog.value.open = false;
}

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

// Progress tracking
const totalSets = computed(() => activities.value.reduce((sum, a) => sum + a.sets.length, 0));
const completedSets = computed(() =>
  activities.value.reduce((sum, a) => sum + a.sets.filter(s => s.is_completed).length, 0)
);
const progressPercent = computed(() =>
  totalSets.value > 0 ? Math.round((completedSets.value / totalSets.value) * 100) : 0
);

// Editable only when owner and status is in_progress
const isEditable = computed(() => {
  return (
    !!workoutId.value &&
    workoutStatus.value === 'in_progress' &&
    workoutOwnerId.value === currentUserId.value
  );
});

function markDirty() {
  isDirty.value = true;
}

// Navigation guard — warn about unsaved changes
function onBeforeUnload(e) {
  if (isDirty.value) {
    e.preventDefault();
  }
}

let removeInertiaListener = null;

onMounted(async () => {
  window.addEventListener('beforeunload', onBeforeUnload);
  removeInertiaListener = router.on('before', event => {
    if (skipNavigationGuard.value) {
      return;
    }
    if (isDirty.value && !confirm(t('You have unsaved changes. Leave anyway?'))) {
      event.preventDefault();
    }
  });

  try {
    const { data } = await get(`/api/v1/workouts/${props.id}`);
    const w = data.data;
    workout.value = w;
    workoutId.value = w.id ?? null;
    workoutStatus.value = w.status ?? null;
    workoutOwnerId.value = w.user_id ?? null;
    activities.value = (w.activities ?? []).map(a => ({
      id: a.id,
      exercise_id: a.exercise_id ?? null,
      exercise_name: a.exercise_name ?? '',
      rest_time_seconds: a.rest_time_seconds ?? null,
      exercise_equipment_name: a.exercise_equipment_name ?? null,
      exercise_category_names: a.exercise_category_names ?? [],
      exercise_effort_type: a.exercise_effort_type ?? 'repetitions',
      exercise_effort_label: a.exercise_effort_label ?? '',
      exercise_difficulty_unit: a.exercise_difficulty_unit ?? null,
      exercise_difficulty_label: a.exercise_difficulty_label ?? '',
      sets: (a.sets ?? []).map(s => ({
        id: s.id ?? null,
        order: s.order,
        effort_value: s.effort_value,
        difficulty_value: s.difficulty_value,
        is_completed: s.is_completed ?? false,
      })),
    }));
  } catch {
    toast.error(t('Failed to load workout'));
    skipNavigationGuard.value = true;
    isRedirectingAfterLoadError.value = true;
    router.visit(route('workouts.index'));
  } finally {
    if (!isRedirectingAfterLoadError.value) {
      isLoading.value = false;
    }
  }
});

onUnmounted(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  removeInertiaListener?.();
});

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
        effort_value: Number(s.effort_value),
        difficulty_value: s.difficulty_value == null ? null : Number(s.difficulty_value),
        is_completed: s.is_completed ?? false,
      })),
    })),
  };
}

async function saveWorkout({ onSuccess, onError } = {}) {
  if (!isEditable.value || isSaving.value) {
    return;
  }

  isSaving.value = true;
  skipNavigationGuard.value = true;

  try {
    await patch(`/api/v1/workouts/${workoutId.value}/save`, buildSavePayload());
    isDirty.value = false;
    toast.success(t('Workout saved'));
    onSuccess?.();
  } catch {
    onError?.();
    toast.error(t('Failed to save workout'));
  } finally {
    isSaving.value = false;
    skipNavigationGuard.value = false;
  }
}

function finishWorkout() {
  if (!workoutId.value || isFinishing.value) {
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

async function completeWorkout() {
  skipNavigationGuard.value = true;

  try {
    await post(`/api/v1/workouts/${workoutId.value}/complete`);
    router.visit(route('workouts.show', { id: workoutId.value }));
  } catch {
    toast.error(t('Failed to complete workout'));
  } finally {
    isFinishing.value = false;
    skipNavigationGuard.value = false;
  }
}

// Set completion toggle — now client-side only (no auto-save)
function onSetCompletionToggled() {
  markDirty();
}

function onAddSet({ activityId }) {
  const activity = activities.value.find(a => a.id === activityId);
  if (!activity) return;

  const lastSet = activity.sets.length ? activity.sets[activity.sets.length - 1] : null;
  const maxOrder = activity.sets.length ? Math.max(...activity.sets.map(s => s.order)) : 0;
  const hasDifficulty =
    activity.exercise_difficulty_unit && activity.exercise_difficulty_unit !== 'none';
  activity.sets.push({
    id: null,
    order: maxOrder + 1,
    effort_value: lastSet ? lastSet.effort_value : 0,
    difficulty_value: lastSet ? lastSet.difficulty_value : hasDifficulty ? 0 : null,
    is_completed: false,
  });
  markDirty();
}

function onRemoveSet({ activityId, id, order }) {
  const activity = activities.value.find(a => a.id === activityId);
  if (!activity) return;

  // If this is the last set, confirm and remove the entire activity
  if (activity.sets.length === 1) {
    openConfirm({
      title: t('Remove activity?'),
      description: t('Removing the last set will delete this activity.'),
      confirmLabel: t('Remove'),
      onConfirm: () => {
        activities.value = activities.value.filter(a => a.id !== activityId);
        markDirty();
      },
    });

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
    toast.error(t('This workout cannot be edited'));

    return;
  }

  if (activities.value.length <= 1) {
    return;
  }

  openConfirm({
    title: t('Delete activity?'),
    description: t('This action cannot be undone.'),
    confirmLabel: t('Delete'),
    onConfirm: () => {
      activities.value = activities.value.filter(a => a.id !== activityId);
      markDirty();
    },
  });
}
</script>
