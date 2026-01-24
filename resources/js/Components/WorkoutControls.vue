<script setup>
const emits = defineEmits(['start-workout', 'finish-workout', 'save-activity']);

function start() {
  emits('start-workout');
}
function finish() {
  emits('finish-workout');
}
</script>

<template>
  <div class="flex items-center gap-3 mt-4">
    <button class="px-4 py-2 bg-indigo-600 text-white rounded text-sm" @click="start">
      Start Workout
    </button>
    <button class="px-4 py-2 bg-green-600 text-white rounded text-sm" @click="finish">
      Finish
    </button>
    <!-- Save button is contextual; the page can call save-activity with id when needed -->
  </div>
</template>
