<script setup>
import ModelCard from '@/Components/ModelCard.vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

defineProps({
  program: {
    type: Object,
    required: true,
  },
  isEnrolled: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['enroll']);
</script>

<template>
  <ModelCard :title="program.name" :description="program.description">
    <template #metadata>
      <div class="flex items-center gap-2 flex-wrap">
        <span v-if="program.start_date || program.end_date">
          {{ t('Duration:') }} {{ program.start_date ?? '?' }} - {{ program.end_date ?? '?' }}
        </span>
        <Badge v-if="isEnrolled" variant="success">{{ t('Enrolled') }}</Badge>
      </div>
    </template>
    <template #actions>
      <Button v-if="!isEnrolled" variant="default" @click="$emit('enroll')">
        {{ t('Enroll in Program') }}
      </Button>
    </template>
  </ModelCard>
</template>
