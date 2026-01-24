<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    program: {
        type: Object,
        required: true,
    },
    workouts: {
        type: Array,
        default: null,
    },
});

const page = usePage();

const isEnrolled = computed(() => {
    return props.program.is_enrolled ?? false;
});

function enrollInProgram() {
    router.post(route('programs.enroll', { program: props.program.id }), {}, {
        preserveScroll: true,
        only: ['program'],
        onError: () => {
            alert('Failed to enroll in program');
        },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Program Details
            </h2>
        </template>

        <PageLayout>
            <!-- Program Header - Prominent Card -->
            <Card class="p-6 mb-8 border-2 bg-muted/50 shadow-md">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 sm:gap-6">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-2xl mb-3">{{ program.name }}</h3>
                        <p class="text-base mb-3">{{ program.description }}</p>
                        <div class="text-sm text-muted-foreground flex items-center gap-2 flex-wrap">
                            <span v-if="program.start_date || program.end_date">
                                Duration: {{ program.start_date ?? '?' }} - {{ program.end_date ?? '?' }}
                            </span>
                            <span
                                v-if="isEnrolled"
                                class="text-xs px-2 py-1 rounded-full flex-shrink-0 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                            >
                                Enrolled
                            </span>
                        </div>
                    </div>
                    <Button
                        v-if="!isEnrolled"
                        @click="enrollInProgram"
                        variant="default"
                        class="flex-shrink-0"
                    >
                        Enroll in Program
                    </Button>
                </div>
            </Card>

            <!-- Workouts Section -->
            <div class="mb-4">
                <h4 class="font-semibold text-lg mb-4">Program Workouts</h4>
                
                <div v-if="workouts === null" class="text-sm text-muted-foreground">
                    Loading workouts...
                </div>
                <div v-else-if="workouts.length === 0" class="text-sm text-muted-foreground">
                    No workouts in this program yet.
                </div>
                <ul v-else class="space-y-3">
                    <li v-for="workout in workouts" :key="workout.id">
                        <Card class="p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium truncate">
                                        {{ workout.name }}
                                    </div>
                                    <div class="text-sm text-muted-foreground mt-1">
                                        Scheduled: {{ workout.pivot?.weekday ?? 'Not scheduled' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <Button
                                        as="a"
                                        :href="route('workout.templates.show', { id: workout.id })"
                                        variant="outline"
                                        size="sm"
                                    >
                                        View
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </li>
                </ul>
            </div>
        </PageLayout>
    </AuthenticatedLayout>
</template>
