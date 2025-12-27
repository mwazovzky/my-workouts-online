<script setup>
import { reactive, toRefs, watch } from 'vue';

const props = defineProps({
    set: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

const emits = defineEmits(['update', 'remove']);

const local = reactive({
    order: props.set.order,
    repetitions: props.set.repetitions ?? 0,
    weight: props.set.weight ?? 0,
});

watch(
    () => [local.repetitions, local.weight],
    () => {
        emits('update', { order: local.order, repetitions: Number(local.repetitions), weight: Number(local.weight) });
    }
);

function remove() {
    emits('remove', local.order);
}
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="w-10 text-sm font-medium dark:text-gray-200">Set {{ local.order }}</div>

        <div class="flex gap-2 items-center">
            <div class="flex flex-col">
                <label class="text-xs text-gray-500 dark:text-gray-400">Reps</label>
                <template v-if="editable">
                    <input v-model.number="local.repetitions" type="number" min="0" class="w-20 rounded border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-2 py-1 text-sm" />
                </template>
                <template v-else>
                    <div class="text-sm dark:text-gray-200">{{ local.repetitions }}</div>
                </template>
            </div>

            <div class="flex flex-col">
                <label class="text-xs text-gray-500 dark:text-gray-400">Weight</label>
                <template v-if="editable">
                    <input v-model.number="local.weight" type="number" min="0" step="0.5" class="w-24 rounded border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 px-2 py-1 text-sm" />
                </template>
                <template v-else>
                    <div class="text-sm dark:text-gray-200">{{ local.weight }}</div>
                </template>
            </div>
        </div>

        <div class="ml-auto">
            <button v-if="editable" @click="remove" class="text-red-600 dark:text-red-400 text-sm hover:underline">Delete</button>
        </div>
    </div>
</template>
