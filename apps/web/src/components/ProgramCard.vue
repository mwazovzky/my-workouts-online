<script setup>
import ModelCard from '@/components/ModelCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
  canUnenroll: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['enroll', 'unenroll']);
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
      <Button v-else-if="canUnenroll" variant="outline" @click="$emit('unenroll')">
        {{ t('Unenroll') }}
      </Button>
    </template>
  </ModelCard>
</template>
