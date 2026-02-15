<script setup>
import { VueDraggableNext as Draggable } from 'vue-draggable-next';
import Activity from '@/Components/Activity.vue';

defineProps({
  activities: { type: Array, required: true },
  editable: { type: Boolean, default: false },
  reorderable: { type: Boolean, default: false },
  canRemoveActivity: { type: Boolean, default: true },
});

const emits = defineEmits([
  'reorder',
  'update-activity',
  'add-set',
  'remove-set',
  'remove-activity',
  'set-completion-toggled',
]);

function onDragChange() {
  emits('reorder');
}

function forward(evName, payload) {
  emits(evName, payload);
}
</script>

<template>
  <Draggable
    :list="activities"
    tag="div"
    class="space-y-3"
    :animation="150"
    :disabled="!reorderable"
    handle=".activity-drag-handle"
    @change="onDragChange"
  >
    <Activity
      v-for="element in activities"
      :key="element.id"
      :activity="element"
      :editable="editable"
      :reorderable="reorderable"
      :can-remove="canRemoveActivity"
      @update-activity="payload => forward('update-activity', payload)"
      @add-set="payload => forward('add-set', payload)"
      @remove-set="payload => forward('remove-set', payload)"
      @remove-activity="payload => forward('remove-activity', payload)"
      @set-completion-toggled="payload => forward('set-completion-toggled', payload)"
    />
  </Draggable>
</template>
