<script setup>
import Activity from '@/Components/Activity.vue';

defineProps({
  activities: { type: Array, default: () => [] },
  editable: { type: Boolean, default: false },
  canRemoveActivity: { type: Boolean, default: true },
});

const emits = defineEmits([
  'update-activity',
  'add-set',
  'remove-set',
  'remove-activity',
  'set-completion-toggled',
]);

function forward(evName, payload) {
  emits(evName, payload);
}
</script>

<template>
  <div class="space-y-6">
    <Activity
      v-for="activity in activities"
      :key="activity.id ?? activity.client_temp_id ?? activity.activity_template_id"
      :activity="activity"
      :editable="editable"
      :can-remove="canRemoveActivity"
      @update-activity="payload => forward('update-activity', payload)"
      @add-set="payload => forward('add-set', payload)"
      @remove-set="payload => forward('remove-set', payload)"
      @remove-activity="payload => forward('remove-activity', payload)"
      @set-completion-toggled="payload => forward('set-completion-toggled', payload)"
    />
  </div>
</template>
