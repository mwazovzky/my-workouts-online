<script setup>
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import { useApiForm } from '@/composables/useApiForm';
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
  template: { type: Object, default: null },
  exercises: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'cancel']);

const { t } = useTranslation();

const isEdit = computed(() => props.template !== null);

function mapActivities(template) {
  if (!template?.activities?.length) {
    return [];
  }
  return template.activities.map(activity => ({
    exercise_id: activity.exercise_id,
    sets: activity.sets.map(set => ({
      effort_value: set.effort_value,
      difficulty_value: set.difficulty_value,
    })),
  }));
}

const form = useApiForm({
  translations: {
    en: {
      name: props.template?.translations?.name?.en ?? '',
      description: props.template?.translations?.description?.en ?? '',
    },
    ru: {
      name: props.template?.translations?.name?.ru ?? '',
      description: props.template?.translations?.description?.ru ?? '',
    },
  },
  activities: mapActivities(props.template),
});

function exerciseFor(activity) {
  return props.exercises.find(exercise => exercise.id === activity.exercise_id) ?? null;
}

function difficultyUnitFor(activity) {
  return exerciseFor(activity)?.difficulty_unit ?? null;
}

function isZone(activity) {
  return difficultyUnitFor(activity) === 'heart_rate_zone';
}

function hasDifficulty(activity) {
  const unit = difficultyUnitFor(activity);
  return unit && unit !== 'none';
}

function defaultSet(activity) {
  const unit = difficultyUnitFor(activity);
  const difficulty = unit && unit !== 'none' && unit !== 'heart_rate_zone' ? 0 : null;
  return { effort_value: 0, difficulty_value: difficulty };
}

function addActivity() {
  form.activities.push({
    exercise_id: props.exercises[0]?.id ?? '',
    sets: [],
  });
  const activity = form.activities[form.activities.length - 1];
  activity.sets.push(defaultSet(activity));
}

function removeActivity(index) {
  form.activities.splice(index, 1);
}

function addSet(activity) {
  activity.sets.push(defaultSet(activity));
}

function removeSet(activity, index) {
  activity.sets.splice(index, 1);
}

function onExerciseChange(activity) {
  // Reset difficulty values so they match the newly selected exercise's unit.
  const fresh = defaultSet(activity);
  activity.sets.forEach(set => {
    set.difficulty_value = fresh.difficulty_value;
  });
}

function normalizeOrders() {
  form.activities.forEach((activity, activityIndex) => {
    activity.order = activityIndex + 1;
    activity.sets.forEach((set, setIndex) => {
      set.order = setIndex + 1;
    });
  });
}

function submit() {
  normalizeOrders();

  const options = {
    onSuccess: response => emit('saved', response.data.data),
    onFail: () => toast.error(t('Failed to save workout template')),
  };

  if (isEdit.value) {
    form.put(`/api/v1/admin/workout-templates/${props.template.id}`, options);
  } else {
    form.post('/api/v1/admin/workout-templates', options);
  }
}
</script>

<template>
  <form class="space-y-5" @submit.prevent="submit">
    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <InputLabel for="name_en" :value="`${t('Name')} (EN)`" />
        <Input id="name_en" v-model="form.translations.en.name" class="mt-1" />
        <InputError class="mt-1" :message="form.errors['translations.en.name']" />
      </div>
      <div>
        <InputLabel for="name_ru" :value="`${t('Name')} (RU)`" />
        <Input id="name_ru" v-model="form.translations.ru.name" class="mt-1" />
      </div>
      <div>
        <InputLabel for="description_en" :value="`${t('Description')} (EN)`" />
        <Textarea id="description_en" v-model="form.translations.en.description" class="mt-1" />
      </div>
      <div>
        <InputLabel for="description_ru" :value="`${t('Description')} (RU)`" />
        <Textarea id="description_ru" v-model="form.translations.ru.description" class="mt-1" />
      </div>
    </div>

    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <InputLabel :value="t('Activities')" />
        <Button type="button" variant="outline" size="sm" @click="addActivity">
          {{ t('Add activity') }}
        </Button>
      </div>
      <InputError :message="form.errors.activities" />

      <div v-if="form.activities.length === 0" class="text-sm text-muted-foreground">
        {{ t('Add at least one activity') }}
      </div>

      <div
        v-for="(activity, activityIndex) in form.activities"
        :key="activityIndex"
        class="space-y-3 rounded-lg border border-border p-3"
      >
        <div class="flex items-end gap-2">
          <div class="flex-1">
            <InputLabel :for="`exercise_${activityIndex}`" :value="t('Exercise')" />
            <Select
              :id="`exercise_${activityIndex}`"
              v-model="activity.exercise_id"
              class="mt-1"
              @update:model-value="onExerciseChange(activity)"
            >
              <option v-for="exercise in exercises" :key="exercise.id" :value="exercise.id">
                {{ exercise.name }}
              </option>
            </Select>
          </div>
          <Button
            type="button"
            variant="destructive"
            size="sm"
            @click="removeActivity(activityIndex)"
          >
            {{ t('Remove') }}
          </Button>
        </div>

        <div class="space-y-2">
          <div
            class="grid items-center gap-2 text-xs font-medium text-muted-foreground"
            :class="
              hasDifficulty(activity)
                ? 'grid-cols-[2rem_1fr_1fr_2.25rem]'
                : 'grid-cols-[2rem_1fr_2.25rem]'
            "
          >
            <span>#</span>
            <span>{{ exerciseFor(activity)?.effort_label || t('Effort') }}</span>
            <span v-if="hasDifficulty(activity)">
              {{ exerciseFor(activity)?.difficulty_label }}
            </span>
            <span />
          </div>

          <div
            v-for="(set, setIndex) in activity.sets"
            :key="setIndex"
            class="grid items-center gap-2"
            :class="
              hasDifficulty(activity)
                ? 'grid-cols-[2rem_1fr_1fr_2.25rem]'
                : 'grid-cols-[2rem_1fr_2.25rem]'
            "
          >
            <span class="text-center text-xs tabular-nums text-muted-foreground">{{
              setIndex + 1
            }}</span>
            <Input
              v-model.number="set.effort_value"
              type="number"
              min="0"
              class="h-9 text-right tabular-nums"
            />
            <Select
              v-if="hasDifficulty(activity) && isZone(activity)"
              v-model="set.difficulty_value"
              class="h-9 tabular-nums"
            >
              <option :value="null">—</option>
              <option v-for="zone in [1, 2, 3, 4, 5]" :key="zone" :value="zone">{{ zone }}</option>
            </Select>
            <Input
              v-else-if="hasDifficulty(activity)"
              v-model.number="set.difficulty_value"
              type="number"
              min="0"
              step="0.5"
              class="h-9 text-right tabular-nums"
            />
            <Button
              type="button"
              variant="outline"
              size="icon-sm"
              @click="removeSet(activity, setIndex)"
            >
              &minus;
            </Button>
          </div>

          <Button type="button" variant="outline" size="sm" @click="addSet(activity)">
            {{ t('Add set') }}
          </Button>
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" @click="emit('cancel')">{{ t('Cancel') }}</Button>
      <Button type="submit" :disabled="form.processing">{{ t('Save') }}</Button>
    </div>
  </form>
</template>
