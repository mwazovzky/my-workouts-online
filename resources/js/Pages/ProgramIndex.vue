<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Programs
            </h2>
        </template>

        <WorkoutPageLayout>
            <!-- Filter Toggle -->
            <div class="flex items-center justify-between mb-6 p-4 border rounded-lg bg-muted/50">
                <span class="text-sm font-medium">Enrolled programs only</span>
                <Switch v-model="filterEnrolled" />
            </div>

            <!-- Program List -->
            <div v-if="filteredPrograms.length === 0" class="text-sm text-muted-foreground">
                No programs found.
            </div>
            <ul v-else class="space-y-3">
                <li v-for="program in filteredPrograms" :key="program.id">
                    <Card class="p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate">
                                    {{ program.name }}
                                </div>
                                <div class="text-sm text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                                    <span class="truncate">{{ program.description ?? 'No description' }}</span>
                                    <span
                                        v-if="isProgramEnrolled(program)"
                                        class="text-xs px-2 py-1 rounded-full flex-shrink-0 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                                    >
                                        Enrolled
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <Button
                                    v-if="!isProgramEnrolled(program)"
                                    @click="enrollInProgram(program.id)"
                                    variant="default"
                                    size="sm"
                                >
                                    Enroll
                                </Button>
                                <Button
                                    as="a"
                                    :href="route('programs.show', { id: program.id })"
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
        </WorkoutPageLayout>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WorkoutPageLayout from '@/Components/WorkoutPageLayout.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    programs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const filterEnrolled = ref(false);

const isProgramEnrolled = program => {
    return program.is_enrolled ?? false;
};

const filteredPrograms = computed(() => {
    if (!filterEnrolled.value) {
        return props.programs;
    }

    return props.programs.filter(program => isProgramEnrolled(program));
});

function enrollInProgram(programId) {
    router.post(route('programs.enroll', { program: programId }), {}, {
        preserveScroll: true,
        only: ['programs'],
        onSuccess: () => {
            // Program list will be refreshed with updated enrollment status
        },
        onError: () => {
            alert('Failed to enroll in program');
        },
    });
}
</script>
