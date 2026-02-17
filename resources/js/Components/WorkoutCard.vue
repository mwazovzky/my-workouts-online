<script setup>
import ModelCard from '@/Components/ModelCard.vue';
import { Badge } from '@/Components/ui/badge';
import { formatDate } from '@/utils/date';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

defineProps({
  workout: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <ModelCard
    :title="workout.name ?? workout.workout_template?.name ?? t('Workout')"
    :description="workout.workout_template?.description ?? null"
  >
    <template #metadata>
      <div class="flex items-center gap-2 flex-wrap">
        <span
          >{{ formatDate(workout.created_at) }} ·
          {{ t(':count activities', { count: workout.activities_count ?? 0 }) }}</span
        >
        <Badge :variant="workout.status === 'completed' ? 'success' : 'warning'">
          {{ workout.status_label ?? workout.status }}
        </Badge>
      </div>
    </template>
  </ModelCard>
</template>
