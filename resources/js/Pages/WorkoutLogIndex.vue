<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Workouts</h2>
        </template>

        <PageLayout>
            <div v-if="workouts.data.length === 0" class="text-sm text-muted-foreground">No workouts yet.</div>
            <ul v-else class="space-y-3">
                <li v-for="workout in workouts.data" :key="workout.id">
                    <Card class="p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium flex items-center gap-2">
                                    <Lock v-if="workout.status === 'completed'" class="w-4 h-4 text-muted-foreground flex-shrink-0" />
                                    <span class="truncate">{{ workout.workout_template?.name ?? 'Workout' }}</span>
                                </div>
                                <div class="text-sm text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                                    <span>{{ formatDate(workout.created_at) }} · {{ workout.activities_count ?? 0 }} activities</span>
                                    <span 
                                        :class="[
                                            'text-xs px-2 py-1 rounded-full flex-shrink-0',
                                            workout.status === 'completed' 
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' 
                                                : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'
                                        ]"
                                    >
                                        {{ workout.status }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <Button
                                    as="a"
                                    :href="route('workout.logs.show', { id: workout.id })"
                                    variant="outline"
                                    size="sm"
                                >
                                    Open
                                </Button>

                                <Button
                                    v-if="workout.user_id === currentUserId() && workout.status === 'in_progress'"
                                    as="a"
                                    :href="route('workout.logs.edit', { id: workout.id })"
                                    variant="default"
                                    size="sm"
                                >
                                    Continue
                                </Button>

                                <Button
                                    @click="deleteWorkout(workout.id)"
                                    variant="destructive"
                                    size="sm"
                                >
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </Card>
                </li>
            </ul>

            <!-- Pagination Links -->
            <nav v-if="workouts.links.length > 3" class="flex items-center justify-center gap-1 mt-6">
                <template v-for="link in workouts.links" :key="link.label">
                    <Button
                        v-if="link.url"
                        as="a"
                        :href="link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1 text-muted-foreground text-sm" v-html="link.label" />
                </template>
            </nav>
        </PageLayout>
    </AuthenticatedLayout>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Lock } from 'lucide-vue-next';

const props = defineProps({
    workouts: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentUserId = () => page.props.auth?.user?.id ?? null;

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}

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
