<script setup>
import { computed } from 'vue';
import Set from '@/Components/Set.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Clock, GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
  activity: { type: Object, required: true },
  editable: { type: Boolean, default: false },
  reorderable: { type: Boolean, default: false },
  canRemove: { type: Boolean, default: true },
});

const emits = defineEmits([
  'update-activity',
  'add-set',
  'remove-set',
  'remove-activity',
  'set-completion-toggled',
]);

const hasDifficulty = computed(
  () =>
    props.activity.exercise_difficulty_unit && props.activity.exercise_difficulty_unit !== 'none'
);

const effortLabel = computed(() => props.activity.exercise_effort_label || t('Reps'));

const difficultyLabel = computed(() => props.activity.exercise_difficulty_label || '');

const formatRestTime = seconds => {
  if (seconds == null) return null;
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;

  if (minutes > 0 && remainingSeconds > 0) {
    return `${minutes}m ${remainingSeconds}s`;
  }
  if (minutes > 0) {
    return `${minutes}m`;
  }
  return `${seconds}s`;
};

function onSetUpdate(set) {
  const updated = {
    ...props.activity,
    sets: props.activity.sets.map(s => {
      if (set.id) {
        return s.id === set.id ? set : s;
      }

      return s.order === set.order ? set : s;
    }),
  };
  emits('update-activity', updated);
}

function onSetRemove({ id, order }) {
  emits('remove-set', { activityId: props.activity.id, id, order });
}

function onSetCompletionToggled(payload) {
  // Ensure parent state reflects the toggled completion before we ask it to persist.
  onSetUpdate(payload);
  emits('set-completion-toggled', { activityId: props.activity.id, ...payload });
}

function addSet() {
  emits('add-set', { activityId: props.activity.id });
}

function removeActivity() {
  emits('remove-activity', props.activity.id);
}
</script>

<template>
  <Card class="max-w-md mx-auto">
    <CardHeader class="px-4 py-2 border-b">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-2 min-w-0 flex-1">
          <button
            v-if="reorderable"
            type="button"
            class="activity-drag-handle mt-0.5 inline-flex items-center justify-center rounded text-muted-foreground/70 hover:text-foreground cursor-grab active:cursor-grabbing"
            :aria-label="t('Reorder activity')"
          >
            <GripVertical class="h-4 w-4" />
          </button>
          <div class="flex-1 min-w-0">
            <CardTitle class="text-sm font-semibold">{{ activity.exercise_name }}</CardTitle>

            <div class="flex flex-wrap items-center gap-1.5 mt-1 text-xs text-muted-foreground">
              <span>{{ (activity.exercise_category_names ?? []).join(', ') || '—' }}</span>
              <span>·</span>
              <span>{{ activity.exercise_equipment_name ?? '—' }}</span>
              <template v-if="activity.rest_time_seconds != null">
                <span>·</span>
                <span class="inline-flex items-center gap-0.5">
                  <Clock class="h-3 w-3" />
                  {{ formatRestTime(activity.rest_time_seconds) }}
                </span>
              </template>
            </div>
          </div>
        </div>
        <div v-if="editable" class="flex items-center gap-1">
          <Button variant="outline" size="icon" :aria-label="t('Add set')" @click="addSet">
            <Plus />
          </Button>
          <Button
            v-if="canRemove"
            variant="outline"
            size="icon"
            class="text-muted-foreground hover:text-destructive hover:bg-destructive/10"
            :aria-label="t('Delete activity')"
            @click="removeActivity"
          >
            <Trash2 />
          </Button>
        </div>
      </div>
    </CardHeader>

    <CardContent class="px-4 py-2">
      <div class="space-y-1">
        <!-- Column Headers -->
        <div
          :class="[
            'grid items-end gap-2 text-xs font-medium text-muted-foreground',
            'grid-cols-[2rem_1fr_1fr_2.25rem_2.25rem]',
          ]"
        >
          <div class="text-center">#</div>
          <div v-if="hasDifficulty" class="text-center whitespace-nowrap">
            {{ difficultyLabel }}
          </div>
          <div v-else />
          <div class="text-center whitespace-nowrap">{{ effortLabel }}</div>
          <div class="text-center">{{ t('Done') }}</div>
          <div class="text-center">
            <span class="sr-only">{{ t('Remove') }}</span>
          </div>
        </div>

        <!-- Set Rows -->
        <Set
          v-for="set in activity.sets"
          :key="set.id ?? set.order"
          :set="set"
          :editable="editable"
          :effort-type="activity.exercise_effort_type"
          :difficulty-unit="activity.exercise_difficulty_unit"
          @update="onSetUpdate"
          @remove="onSetRemove"
          @completion-toggled="onSetCompletionToggled"
        />
      </div>
    </CardContent>
  </Card>
</template>
