<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Workout</h2>
        </template>

        <div class="p-4">
            <div class="mb-4 p-4 bg-white rounded shadow-sm">
                <div class="font-semibold text-lg">Editing Workout Log #{{ workoutLogId }}</div>
                <div class="text-sm text-gray-600">Date: {{ workoutDate }} · Status: {{ workoutStatus }}</div>
            </div>

            <!-- editing banner -->
            <div v-if="isEditable" class="mb-4 p-3 bg-green-50 border-l-4 border-green-400 text-green-800 rounded" role="status" aria-live="polite">
                You are editing this workout. Changes will be saved to the server. Use "Finish Workout" when done.
            </div>

            <!-- not owner or not editable message -->
            <div v-else class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded" role="status" aria-live="polite">
                This workout cannot be edited here. Only the owner can edit while the workout status is "in_progress".
            </div>

            <div class="mb-4">
                <span class="px-4 py-2 inline-block text-sm text-gray-600">Workout opened</span>

                <!-- Only show Finish when the log is editable (in_progress and owner) -->
                <button v-if="isEditable" @click="finishWorkout" :disabled="isFinishing" class="ml-2 px-4 py-2 bg-green-600 text-white rounded flex items-center">
                    <span v-if="!isFinishing">Finish Workout</span>
                    <span v-else>Finishing…</span>
                </button>
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
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';

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
        sets: (a.sets ?? []).map(s => ({ order: s.order, repetitions: s.repetitions, weight: s.weight })),
    })),
);

const workoutStatus = ref(props.workoutLog.status ?? null);
const workoutDate = ref(props.workoutLog.date ?? props.workoutLog.created_at ?? null);
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

    activityForm.sets = activity.sets.map(s => ({ order: s.order, repetitions: s.repetitions, weight: s.weight }));

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
    activity.sets.push({ order: maxOrder + 1, repetitions: 0, weight: 0 });
}

function onRemoveSet({ activityId, order }) {
    const activity = activities.value.find(a => a.id === activityId);
    if (!activity) return;
    activity.sets = activity.sets.filter(s => s.order !== order);
}

function onUpdateActivity(updated) {
    const idx = activities.value.findIndex(a => a.id === updated.id);
    if (idx !== -1) activities.value[idx] = updated;
}
</script>
