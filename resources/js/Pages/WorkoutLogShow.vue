<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">View Workout</h2>
    </template>

    <WorkoutPageLayout>
      <WorkoutHeader 
        :workout-log-id="workoutLogId" 
        :workout-date="workoutDate" 
        :workout-status="workoutStatus"
      />

      <!-- prominent read-only banner -->
      <div class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded" role="status" aria-live="polite">
        This is a read-only view of the workout. To make changes open the editor (available only while the workout is in progress and you are the owner).
      </div>

      <div>
        <ActivitiesList
          v-if="activities.length"
          :activities="activities"
          :editable="false"
          @add-set="() => {}"
          @remove-set="() => {}"
          @update-activity="() => {}"
        />
        <p
          v-else
          class="text-sm text-gray-500"
        >
          Loading activities...
        </p>
      </div>
    </WorkoutPageLayout>

    <WorkoutFooter :show="canEdit">
      <Button @click="goEdit" :disabled="editingNav" variant="outline" size="lg" class="px-8">
        <span v-if="!editingNav">Continue</span>
        <span v-else>Opening…</span>
      </Button>
    </WorkoutFooter>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import WorkoutHeader from '@/Components/WorkoutHeader.vue';
import WorkoutFooter from '@/Components/WorkoutFooter.vue';
import WorkoutPageLayout from '@/Components/WorkoutPageLayout.vue';
import { Button } from '@/components/ui/button';

const props = defineProps({
  workoutLog: {
    type: Object,
    required: true,
  },
  activities: {
    type: Array,
    default: null,
  },
});

const workoutLogId = computed(() => props.workoutLog.id);
const workoutStatus = computed(() => props.workoutLog.status ?? null);
const workoutDate = computed(() => props.workoutLog.created_at ?? null);
const workoutOwnerId = computed(() => props.workoutLog.user_id ?? null);
const activities = computed(() => props.activities ?? []);

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
  router.visit(route('workout.logs.edit', { id: workoutLogId.value }), {
    onFinish: () => {
      editingNav.value = false;
    },
  });
}
</script>
