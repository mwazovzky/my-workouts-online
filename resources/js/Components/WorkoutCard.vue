<script setup>
import ModelCard from '@/Components/ModelCard.vue';
import { Badge } from '@/Components/ui/badge';
import { formatDate } from '@/utils/date';
import { formatStatus } from '@/utils/format';

defineProps({
  workout: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <ModelCard
    :title="workout.name ?? workout.workout_template?.name ?? 'Workout'"
    :description="workout.workout_template?.description ?? null"
  >
    <template #metadata>
      <div class="flex items-center gap-2 flex-wrap">
        <span
          >{{ formatDate(workout.created_at) }} ·
          {{ workout.activities_count ?? 0 }} activities</span
        >
        <Badge :variant="workout.status === 'completed' ? 'success' : 'warning'">
          {{ formatStatus(workout.status) }}
        </Badge>
      </div>
    </template>
  </ModelCard>
</template>
