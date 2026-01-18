<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">View Workout</h2>
    </template>

    <div class="p-4">
      <div class="mb-4 p-4 bg-white rounded shadow-sm">
        <div class="font-semibold text-lg">Workout Log #{{ workoutLogId }}</div>
        <div class="text-sm text-gray-600">Date: {{ workoutDate }} · Status: {{ workoutStatus }}</div>
      </div>

      <!-- prominent read-only banner -->
      <div class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded" role="status" aria-live="polite">
        This is a read-only view of the workout. To make changes open the editor (available only while the workout is in progress and you are the owner).
      </div>

      <div class="mb-4 flex items-center gap-3">
        <span class="px-4 py-2 inline-block text-sm text-gray-600">View only</span>

        <button
          v-if="canEdit"
          @click="goEdit"
          :disabled="editingNav"
          class="ml-2 px-4 py-2 bg-indigo-600 text-white rounded flex items-center gap-2"
        >
          <span v-if="!editingNav">Open editor</span>
          <span v-else class="text-sm">Opening…</span>
        </button>
      </div>

      <div>
        <ActivitiesList
          :activities="activities"
          :editable="false"
          @add-set="() => {}"
          @remove-set="() => {}"
          @update-activity="() => {}"
        />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';

const props = defineProps({
  workoutLog: {
    type: Object,
    required: true,
  },
});

const workoutLogId = computed(() => props.workoutLog.id);
const workoutStatus = computed(() => props.workoutLog.status ?? null);
const workoutDate = computed(() => props.workoutLog.date ?? props.workoutLog.created_at ?? null);
const workoutOwnerId = computed(() => props.workoutLog.user_id ?? null);
const activities = computed(() => props.workoutLog.activities ?? []);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const editingNav = ref(false);

// allow navigation to editor only for owner and when status is in_progress
const canEdit = computed(() => {
  return !!currentUserId.value && workoutStatus.value === 'in_progress' && workoutOwnerId.value === currentUserId.value;
});

// go to editor (with tiny loading state)
function goEdit() {
  if (!canEdit.value) return;
  editingNav.value = true;
  window.location.href = route('workout.logs.edit', { id: workoutLogId.value });
}
</script>
