<script setup>
import { reactive, watch } from 'vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-vue-next';

const props = defineProps({
    set: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

const emits = defineEmits(['update', 'remove']);

const local = reactive({
    id: props.set.id ?? null,
    order: props.set.order,
    repetitions: props.set.repetitions ?? 0,
    weight: props.set.weight ?? 0,
});

watch(
    () => [local.repetitions, local.weight],
    () => {
        emits('update', { id: local.id, order: local.order, repetitions: Number(local.repetitions), weight: Number(local.weight) });
    }
);

function remove() {
    emits('remove', { id: local.id, order: local.order });
}
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="w-12 text-sm font-medium text-muted-foreground">Set {{ local.order }}</div>

        <div class="flex gap-4 flex-1">
            <div class="flex flex-col gap-1 flex-1">
                <template v-if="editable">
                    <Input v-model.number="local.repetitions" type="number" min="0" class="h-9 text-right transition-colors hover:border-gray-400 dark:hover:border-gray-500" />
                </template>
                <template v-else>
                    <Input :model-value="local.repetitions" type="number" disabled class="h-9 text-right disabled:opacity-100 disabled:cursor-default disabled:text-foreground" />
                </template>
            </div>

            <div class="flex flex-col gap-1 flex-1">
                <template v-if="editable">
                    <Input v-model.number="local.weight" type="number" min="0" step="0.5" class="h-9 text-right transition-colors hover:border-gray-400 dark:hover:border-gray-500" />
                </template>
                <template v-else>
                    <Input :model-value="local.weight" type="number" disabled class="h-9 text-right disabled:opacity-100 disabled:cursor-default disabled:text-foreground" />
                </template>
            </div>
        </div>

        <div>
            <Button v-if="editable" @click="remove" variant="destructive" size="sm" class="h-8 w-8 p-0">
                <Trash2 class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
