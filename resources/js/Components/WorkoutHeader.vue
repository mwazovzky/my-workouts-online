<script setup>
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/utils/date';

defineProps({
    workoutLogId: {
        type: [Number, String],
        required: true,
    },
    workoutDate: {
        type: String,
        required: true,
    },
    workoutStatus: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        default: 'Workout Log',
    },
});
</script>

<template>
    <div class="mb-4 p-4 bg-white rounded shadow-sm">
        <div class="font-semibold text-lg">{{ title }} #{{ workoutLogId }}</div>
        <div class="text-sm text-gray-600 flex items-center gap-2 flex-wrap">
            <span>{{ formatDate(workoutDate) }}</span>
            <Badge :variant="workoutStatus === 'completed' ? 'success' : 'warning'">
                {{ workoutStatus }}
            </Badge>
        </div>
    </div>
</template>
