<script setup>
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { useApiForm } from '@/composables/useApiForm';
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
  category: { type: Object, default: null },
});

const emit = defineEmits(['saved', 'cancel']);

const { t } = useTranslation();

const isEdit = computed(() => props.category !== null);

const form = useApiForm({
  translations: {
    en: { name: props.category?.translations?.name?.en ?? '' },
    ru: { name: props.category?.translations?.name?.ru ?? '' },
  },
});

function submit() {
  const options = {
    onSuccess: response => emit('saved', response.data.data),
    onFail: () => toast.error(t('Failed to save category')),
  };

  if (isEdit.value) {
    form.put(`/api/v1/admin/categories/${props.category.id}`, options);
  } else {
    form.post('/api/v1/admin/categories', options);
  }
}
</script>

<template>
  <form class="space-y-4" @submit.prevent="submit">
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

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" @click="emit('cancel')">{{ t('Cancel') }}</Button>
      <Button type="submit" :disabled="form.processing">{{ t('Save') }}</Button>
    </div>
  </form>
</template>
