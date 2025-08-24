<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">View Workout</h2>
    </template>

    <div class="p-4">
      <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
      <div v-else-if="error" class="text-sm text-red-600">Error: {{ error }}</div>
      <div v-else>
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
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';

const routeParams = route().params;
const workoutLogId = ref(routeParams.id ?? null);

const loading = ref(true);
const error = ref(null);
const activities = ref([]);
const workoutStatus = ref(null);
const workoutDate = ref(null);
const workoutOwnerId = ref(null); // set from API

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const editingNav = ref(false);

// load workout log data
async function load() {
  loading.value = true;
  error.value = null;
  try {
    if (!workoutLogId.value) throw new Error('No workout log id provided');
    const res = await fetch(route('api.workout.logs.show', { id: workoutLogId.value }), { credentials: 'same-origin' });
    if (!res.ok) throw new Error(await res.text() || res.statusText);
    const payload = await res.json();
    const data = payload.data ?? payload;

    workoutStatus.value = data.status ?? null;
    workoutDate.value = data.date ?? data.created_at ?? null;
    workoutOwnerId.value = data.user_id ?? null;

    activities.value = (data.activities ?? []).map(a => ({
      id: a.id,
      exercise_id: a.exercise_id ?? null,
      exercise_name: a.exercise_name ?? '',
      sets: (a.sets ?? []).map(s => ({ order: s.order, repetitions: s.repetitions, weight: s.weight })),
    }));
  } catch (e) {
    error.value = e.message || 'Failed to load workout';
  } finally {
    loading.value = false;
  }
}

onMounted(load);

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
