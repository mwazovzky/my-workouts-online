<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Programs
            </h2>
        </template>

        <PageLayout>
            <!-- Filter Toggle -->
            <div class="flex items-center justify-between mb-6 p-4 border rounded-lg bg-muted/50">
                <span class="text-sm font-medium">Enrolled programs only</span>
                <Switch v-model="filterEnrolled" />
            </div>

            <!-- Program List -->
            <Empty v-if="filteredPrograms.length === 0">
                <EmptyTitle>No programs found</EmptyTitle>
                <EmptyDescription>There are no programs matching your filter</EmptyDescription>
            </Empty>
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
                                    <Badge v-if="isProgramEnrolled(program)" variant="success">Enrolled</Badge>
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
        </PageLayout>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { usePage } from '@inertiajs/vue3';
import { useEnrollment } from '@/composables/useEnrollment';

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

const { enroll: enrollInProgram } = useEnrollment({ only: ['programs'] });
</script>
