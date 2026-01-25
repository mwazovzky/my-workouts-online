<script setup>
import { Card } from '@/Components/ui/card';

defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: null,
  },
});
</script>

<template>
  <Card class="p-6 mb-8 border-2 bg-muted/50 shadow-md">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 sm:gap-6">
      <div class="flex-1 min-w-0">
        <h3 class="font-bold text-2xl mb-2">{{ title }}</h3>
        <p v-if="description" class="text-base mb-2">{{ description }}</p>

        <!-- Slot for metadata (date, badges, etc.) -->
        <div class="text-sm text-muted-foreground">
          <slot name="metadata" />
        </div>
      </div>

      <!-- Slot for action buttons -->
      <div v-if="$slots.actions" class="flex-shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </Card>
</template>
