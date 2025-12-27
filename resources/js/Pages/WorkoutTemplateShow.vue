<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import { Link } from '@inertiajs/vue3';

const workout = ref(null);
const loading = ref(true);
const error = ref(null);
const starting = ref(false);
const workoutLogId = ref(null);

async function loadWorkout() {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`/api/workout-templates/${route().params.id}`, {
            credentials: 'same-origin',
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || res.statusText);
        }
        const payload = await res.json();
        // payload expected to be the workout resource (object)
        workout.value = payload.data ?? payload; // support both shapes
    } catch (e) {
        error.value = e.message || 'Failed to load workout details';
    } finally {
        loading.value = false;
    }
}

onMounted(loadWorkout);

// helper to read CSRF token injected by Blade
function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : null;
}

async function startWorkout() {
    if (starting.value || workoutLogId.value) return;
    starting.value = true;
    error.value = null;

    try {
        const token = csrfToken();

        const res = await fetch(route('api.workout.logs.store'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({ workout_template_id: route().params.id }),
        });
        if (!res.ok) {
            const txt = await res.text();
            throw new Error(txt || res.statusText);
        }
        const payload = await res.json();
        const data = payload.data ?? payload;
        workoutLogId.value = data.id;

        // redirect to workout logging page for the created WorkoutLog
        const loggingHref = route('workout.logs.edit', { id: workoutLogId.value });
        window.location.href = loggingHref;
    } catch (e) {
        error.value = e.message || 'Failed to start workout';
    } finally {
        starting.value = false;
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Workout Template Details
            </h2>
        </template>

        <div>
            <div v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading workout details…</div>
            <div v-else-if="error" class="text-sm text-red-600 dark:text-red-400">Error: {{ error }}</div>
            <div v-else>
                <div class="p-4 bg-white dark:bg-gray-800 rounded shadow-sm">
                    <h3 class="font-semibold text-lg dark:text-gray-100">{{ workout.name }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ workout.description }}</p>
                </div>

                <div class="mt-6">
                    <h4 class="font-semibold text-md dark:text-gray-200">Activities</h4>
                    <div class="mt-2">
                        <ActivitiesList :activities="workout.activities ?? []" :editable="false" />
                    </div>
                </div>

                <div class="mt-6">
                    <Link @click.prevent="startWorkout" class="inline-block px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded hover:bg-indigo-700 dark:hover:bg-indigo-600">
                        Start Workout
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
