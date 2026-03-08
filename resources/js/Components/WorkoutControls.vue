<script setup>
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const emits = defineEmits(['start-workout', 'finish-workout', 'save-activity']);

function start() {
  emits('start-workout');
}
function finish() {
  emits('finish-workout');
}
</script>

<template>
  <div class="mt-4 flex items-center gap-3">
    <Button size="sm" @click="start">
      {{ t('Start Workout') }}
    </Button>
    <Button variant="secondary" size="sm" @click="finish">
      {{ t('Finish') }}
    </Button>
    <!-- Save button is contextual; the page can call save-activity with id when needed -->
  </div>
</template>
