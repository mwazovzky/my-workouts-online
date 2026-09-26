<script setup>
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { useApiForm } from '@/composables/useApiForm';
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
  exercise: { type: Object, default: null },
  equipmentOptions: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'cancel']);

const { t } = useTranslation();

const isEdit = computed(() => props.exercise !== null);

const effortTypes = ['repetitions', 'duration'];

const form = useApiForm({
  equipment_id: props.exercise?.equipment_id ?? props.equipmentOptions[0]?.id ?? '',
  effort_type: props.exercise?.effort_type ?? 'repetitions',
  rest_time_seconds: props.exercise?.rest_time_seconds ?? null,
  category_ids: props.exercise?.category_ids ? [...props.exercise.category_ids] : [],
  translations: {
    en: {
      name: props.exercise?.translations?.name?.en ?? '',
      description: props.exercise?.translations?.description?.en ?? '',
    },
    ru: {
      name: props.exercise?.translations?.name?.ru ?? '',
      description: props.exercise?.translations?.description?.ru ?? '',
    },
  },
});

function toggleCategory(id) {
  const index = form.category_ids.indexOf(id);
  if (index === -1) {
    form.category_ids.push(id);
  } else {
    form.category_ids.splice(index, 1);
  }
}

function submit() {
  const options = {
    onSuccess: response => emit('saved', response.data.data),
    onFail: () => toast.error(t('Failed to save exercise')),
  };

  if (isEdit.value) {
    form.put(`/api/v1/admin/exercises/${props.exercise.id}`, options);
  } else {
    form.post('/api/v1/admin/exercises', options);
  }
}
</script>

<template>
  <form class="space-y-4" @submit.prevent="submit">
    <div>
      <InputLabel for="equipment_id" :value="t('Equipment')" />
      <Select id="equipment_id" v-model="form.equipment_id" class="mt-1">
        <option v-for="option in equipmentOptions" :key="option.id" :value="option.id">
          {{ option.name }}
        </option>
      </Select>
      <InputError class="mt-1" :message="form.errors.equipment_id" />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <InputLabel for="effort_type" :value="t('Effort type')" />
        <Select id="effort_type" v-model="form.effort_type" class="mt-1">
          <option v-for="type in effortTypes" :key="type" :value="type">{{ t(type) }}</option>
        </Select>
        <InputError class="mt-1" :message="form.errors.effort_type" />
      </div>

      <div>
        <InputLabel
          for="rest_time_seconds"
          :value="`${t('Rest time (seconds)')} (${t('optional')})`"
        />
        <Input
          id="rest_time_seconds"
          v-model="form.rest_time_seconds"
          type="number"
          min="0"
          :placeholder="t('None')"
          class="mt-1"
        />
        <InputError class="mt-1" :message="form.errors.rest_time_seconds" />
      </div>
    </div>

    <div>
      <InputLabel for="name_en" :value="`${t('Name')} (EN)`" />
      <Input id="name_en" v-model="form.translations.en.name" class="mt-1" />
      <InputError class="mt-1" :message="form.errors['translations.en.name']" />
    </div>

    <div>
      <InputLabel for="name_ru" :value="`${t('Name')} (RU)`" />
      <Input id="name_ru" v-model="form.translations.ru.name" class="mt-1" />
      <InputError class="mt-1" :message="form.errors['translations.ru.name']" />
    </div>

    <div>
      <InputLabel for="description_en" :value="`${t('Description')} (EN)`" />
      <Textarea id="description_en" v-model="form.translations.en.description" class="mt-1" />
      <InputError class="mt-1" :message="form.errors['translations.en.description']" />
    </div>

    <div>
      <InputLabel for="description_ru" :value="`${t('Description')} (RU)`" />
      <Textarea id="description_ru" v-model="form.translations.ru.description" class="mt-1" />
      <InputError class="mt-1" :message="form.errors['translations.ru.description']" />
    </div>

    <div>
      <InputLabel :value="t('Categories')" />
      <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
        <label
          v-for="category in categoryOptions"
          :key="category.id"
          class="flex items-center gap-2 text-sm"
        >
          <Checkbox
            :checked="form.category_ids.includes(category.id)"
            @update:checked="toggleCategory(category.id)"
          />
          <span class="truncate">{{ category.name }}</span>
        </label>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" @click="emit('cancel')">{{ t('Cancel') }}</Button>
      <Button type="submit" :disabled="form.processing">{{ t('Save') }}</Button>
    </div>
  </form>
</template>
