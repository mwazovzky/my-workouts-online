<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Workouts</h2>
        </template>

        <div class="p-4">
            <div v-if="logs.length === 0" class="text-sm text-gray-600">No workouts yet.</div>
            <ul v-else class="space-y-2">
                <li v-for="log in logs" :key="log.id" class="p-3 bg-white rounded shadow-sm flex items-center justify-between">
                        <div>
                            <div class="font-medium flex items-center gap-2">
                                <!-- lock icon for completed logs -->
                                <svg v-if="log.status === 'completed'" class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 8V6a5 5 0 1110 0v2h1a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V9a1 1 0 011-1h1zm2-2a3 3 0 116 0v2H7V6z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ log.workout_template?.name ?? 'Workout' }}</span>
                            </div>
                            <div class="text-sm text-gray-500">{{ log.date ?? log.created_at }} · {{ log.activities_count ?? 0 }} activities</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Open goes to canonical show (read-only) -->
                            <Link
                                :href="route('workout.logs.show', { id: log.id })"
                                class="px-3 py-1 bg-indigo-600 text-white rounded text-sm flex items-center gap-2"
                            >
                                Open
                            </Link>

                            <!-- Continue (edit) only for owner and when in_progress -->
                            <Link
                                v-if="log.user_id === currentUserId() && log.status === 'in_progress'"
                                :href="route('workout.logs.edit', { id: log.id })"
                                class="px-3 py-1 bg-indigo-500 text-white rounded text-sm flex items-center gap-2"
                            >
                                Continue
                            </Link>

                            <button @click="deleteWorkout(log.id)" class="px-3 py-1 bg-red-600 text-white rounded text-sm">Delete</button>
                            <span class="text-sm text-gray-600">{{ log.status }}</span>
                        </div>
                    </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({
    logs: {
        type: Array,
        default: () => [],
    },
});

const logs = ref([...props.logs]);

const page = usePage();
const currentUserId = () => page.props.auth?.user?.id ?? null;

async function deleteWorkout(id) {
    if (!confirm('Delete this workout? This action cannot be undone.')) return;
    router.delete(route('workout.logs.destroy', { workoutLog: id }), {
        preserveScroll: true,
        onSuccess: () => {
            // Inertia will reload the index; local logs will be refreshed from props.
        },
        onError: () => {
            alert('Failed to delete workout');
        },
    });
}
</script>
