<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Workouts</h2>
        </template>

        <div class="p-4">
            <div v-if="workouts.data.length === 0" class="text-sm text-gray-600">No workouts yet.</div>
            <ul v-else class="space-y-2">
                <li v-for="workout in workouts.data" :key="workout.id" class="p-3 bg-white rounded shadow-sm flex items-center justify-between">
                        <div>
                            <div class="font-medium flex items-center gap-2">
                                <!-- lock icon for completed logs -->
                                <svg v-if="workout.status === 'completed'" class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 8V6a5 5 0 1110 0v2h1a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V9a1 1 0 011-1h1zm2-2a3 3 0 116 0v2H7V6z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ workout.workout_template?.name ?? 'Workout' }}</span>
                            </div>
                            <div class="text-sm text-gray-500">{{ workout.date ?? workout.created_at }} · {{ workout.activities_count ?? 0 }} activities</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Open goes to canonical show (read-only) -->
                            <Link
                                :href="route('workout.logs.show', { id: workout.id })"
                                class="px-3 py-1 bg-indigo-600 text-white rounded text-sm flex items-center gap-2"
                            >
                                Open
                            </Link>

                            <!-- Continue (edit) only for owner and when in_progress -->
                            <Link
                                v-if="workout.user_id === currentUserId() && workout.status === 'in_progress'"
                                :href="route('workout.logs.edit', { id: workout.id })"
                                class="px-3 py-1 bg-indigo-500 text-white rounded text-sm flex items-center gap-2"
                            >
                                Continue
                            </Link>

                            <button @click="deleteWorkout(workout.id)" class="px-3 py-1 bg-red-600 text-white rounded text-sm">Delete</button>
                            <span class="text-sm text-gray-600">{{ workout.status }}</span>
                        </div>
                    </li>
            </ul>

            <!-- Pagination Links -->
            <nav v-if="workouts.links.length > 3" class="flex items-center justify-center gap-1 mt-4">
                <template v-for="link in workouts.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :class="[
                            'px-3 py-1 rounded text-sm',
                            link.active 
                                ? 'bg-indigo-600 text-white' 
                                : 'bg-white text-gray-700 hover:bg-gray-100'
                        ]"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1 text-gray-400 text-sm" v-html="link.label" />
                </template>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    workouts: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentUserId = () => page.props.auth?.user?.id ?? null;

async function deleteWorkout(id) {
    if (!confirm('Delete this workout? This action cannot be undone.')) return;
    router.delete(route('workout.logs.destroy', { workoutLog: id }), {
        preserveScroll: true,
        onSuccess: () => {
            // Inertia will reload the index; workouts will be refreshed from props.
        },
        onError: () => {
            alert('Failed to delete workout');
        },
    });
}
</script>
