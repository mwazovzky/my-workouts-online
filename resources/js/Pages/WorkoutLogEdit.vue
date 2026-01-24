<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Workout</h2>
        </template>

        <WorkoutPageLayout>
            <WorkoutHeader 
                :workout-log-id="workoutLogId" 
                :workout-date="workoutDate" 
                :workout-status="workoutStatus"
                title="Editing Workout Log"
            />

            <!-- editing banner -->
            <div v-if="isEditable" class="mb-4 p-3 bg-green-50 border-l-4 border-green-400 text-green-800 rounded" role="status" aria-live="polite">
                You are editing this workout. Changes will be saved to the server. Use "Complete" when done.
            </div>

            <!-- not owner or not editable message -->
            <div v-else class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded" role="status" aria-live="polite">
                This workout cannot be edited here. Only the owner can edit while the workout status is "in_progress".
            </div>

            <div class="mb-6">
                <span class="text-sm text-gray-600 dark:text-gray-400">Workout opened</span>
            </div>

            <div>
                <ActivitiesList
                    :activities="activities"
                    :editable="isEditable"
                    @save-activity="onSaveActivity"
                    @add-set="payload => onAddSet(payload)"
                    @remove-set="payload => onRemoveSet(payload)"
                    @update-activity="payload => onUpdateActivity(payload)"
                />
            </div>
        </WorkoutPageLayout>

        <WorkoutFooter :show="isEditable">
            <Button @click="finishWorkout" :disabled="isFinishing" variant="outline" size="lg" class="px-8">
                <span v-if="!isFinishing">Complete</span>
                <span v-else>Completing…</span>
            </Button>
        </WorkoutFooter>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
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
});

const workoutLogId = ref(props.workoutLog.id ?? null);
const activities = ref(
    (props.workoutLog.activities ?? []).map(a => ({
        id: a.id,
        exercise_id: a.exercise_id ?? null,
        exercise_name: a.exercise_name ?? '',
        sets: (a.sets ?? []).map(s => ({ id: s.id ?? null, order: s.order, repetitions: s.repetitions, weight: s.weight })),
    })),
);

const workoutStatus = ref(props.workoutLog.status ?? null);
const workoutDate = ref(props.workoutLog.created_at ?? null);
const workoutOwnerId = ref(props.workoutLog.user_id ?? null);

// UI flags
const isFinishing = ref(false);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

// editable only when owner and status is in_progress
const isEditable = computed(() => {
    return !!workoutLogId.value && workoutStatus.value === 'in_progress' && workoutOwnerId.value === currentUserId.value;
});

const activityForm = useForm({
    sets: [],
});

// Save a single activity (upsert sets)
async function saveActivity(activityId) {
    if (!isEditable.value) {
        alert('This workout cannot be edited');
        return;
    }

    const activity = activities.value.find(a => a.id === activityId);
    if (!activity || !workoutLogId.value) {
        alert('No activity or workout not started');
        return;
    }

    activityForm.sets = activity.sets.map(s => ({ id: s.id ?? null, order: s.order, repetitions: s.repetitions, weight: s.weight }));

    activityForm.patch(route('activities.update', { activity: activity.id }), {
        preserveScroll: true,
        onError: () => {
            alert('Failed to save activity');
        },
    });
}

// Finish workout via Inertia; update status locally on success
function finishWorkout() {
    if (!workoutLogId.value || isFinishing.value) {
        return;
    }

    isFinishing.value = true;

    router.post(route('workout.logs.complete', { workoutLog: workoutLogId.value }), {
        preserveScroll: true,
        onSuccess: () => {
            workoutStatus.value = 'completed';
        },
        onError: () => {
            alert('Failed to finish workout');
        },
        onFinish: () => {
            isFinishing.value = false;
        },
    });
}

// handlers forwarded from components
function onSaveActivity(id) {
    saveActivity(id);
}

// remove/add set handlers (simple client-side updates)
function onAddSet({ activityId }) {
    const activity = activities.value.find(a => a.id === activityId);
    if (!activity) return;
    const maxOrder = activity.sets.length ? Math.max(...activity.sets.map(s => s.order)) : 0;
    activity.sets.push({ id: null, order: maxOrder + 1, repetitions: 0, weight: 0 });
}

function onRemoveSet({ activityId, id, order }) {
    const activity = activities.value.find(a => a.id === activityId);
    if (!activity) return;
    if (id) {
        activity.sets = activity.sets.filter(s => s.id !== id);
        return;
    }

    activity.sets = activity.sets.filter(s => s.order !== order);
}

function onUpdateActivity(updated) {
    const idx = activities.value.findIndex(a => a.id === updated.id);
    if (idx !== -1) activities.value[idx] = updated;
}
</script>
