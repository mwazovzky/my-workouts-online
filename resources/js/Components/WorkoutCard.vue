<script setup>
import ModelCard from '@/Components/ModelCard.vue';
import { Badge } from '@/Components/ui/badge';
import { formatDate } from '@/utils/date';

const props = defineProps({
  workout: {
    type: Object,
    required: true,
  },
  title: {
    type: String,
    default: 'Workout Log',
  },
});
</script>

<template>
  <ModelCard :title="`${title} #${workout.id}`">
    <template #metadata>
      <div class="flex items-center gap-2 flex-wrap">
        <span>{{ formatDate(workout.created_at) }}</span>
        <Badge :variant="workout.status === 'completed' ? 'success' : 'warning'">
          {{ workout.status }}
        </Badge>
      </div>
    </template>
  </ModelCard>
</template>
