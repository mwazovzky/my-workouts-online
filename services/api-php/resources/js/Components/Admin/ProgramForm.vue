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
  program: { type: Object, default: null },
  templates: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'cancel']);

const { t } = useTranslation();

const isEdit = computed(() => props.program !== null);

const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function mapAssignments(program) {
  if (!program?.assignments?.length) {
    return [];
  }
  return program.assignments.map(assignment => ({
    workout_template_id: assignment.workout_template_id,
    weekday: assignment.weekday,
  }));
}

const form = useApiForm({
  translations: {
    en: {
      name: props.program?.translations?.name?.en ?? '',
      description: props.program?.translations?.description?.en ?? '',
    },
    ru: {
      name: props.program?.translations?.name?.ru ?? '',
      description: props.program?.translations?.description?.ru ?? '',
    },
  },
  assignments: mapAssignments(props.program),
});

function addAssignment() {
  form.assignments.push({
    workout_template_id: props.templates[0]?.id ?? '',
    weekday: weekdays[0],
  });
}

function removeAssignment(index) {
  form.assignments.splice(index, 1);
}

function submit() {
  const options = {
    onSuccess: response => emit('saved', response.data.data),
    onFail: () => toast.error(t('Failed to save program')),
  };

  if (isEdit.value) {
    form.put(`/api/v1/admin/programs/${props.program.id}`, options);
  } else {
    form.post('/api/v1/admin/programs', options);
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
        <InputLabel :value="t('Weekly schedule')" />
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="templates.length === 0"
          @click="addAssignment"
        >
          {{ t('Add assignment') }}
        </Button>
      </div>

      <div v-if="templates.length === 0" class="text-sm text-muted-foreground">
        {{ t('Create a workout template first') }}
      </div>
      <div v-else-if="form.assignments.length === 0" class="text-sm text-muted-foreground">
        {{ t('No workouts scheduled yet') }}
      </div>

      <div
        v-for="(assignment, index) in form.assignments"
        :key="index"
        class="flex items-end gap-2 rounded-lg border border-border p-3"
      >
        <div class="flex-1">
          <InputLabel :for="`weekday_${index}`" :value="t('Weekday')" />
          <Select :id="`weekday_${index}`" v-model="assignment.weekday" class="mt-1">
            <option v-for="day in weekdays" :key="day" :value="day">{{ t(day) }}</option>
          </Select>
        </div>
        <div class="flex-1">
          <InputLabel :for="`template_${index}`" :value="t('Workout template')" />
          <Select :id="`template_${index}`" v-model="assignment.workout_template_id" class="mt-1">
            <option v-for="template in templates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </Select>
        </div>
        <Button type="button" variant="destructive" size="sm" @click="removeAssignment(index)">
          {{ t('Remove') }}
        </Button>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" @click="emit('cancel')">{{ t('Cancel') }}</Button>
      <Button type="submit" :disabled="form.processing">{{ t('Save') }}</Button>
    </div>
  </form>
</template>
