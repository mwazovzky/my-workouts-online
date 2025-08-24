<script setup>
import { reactive } from 'vue';
import SetRow from '@/Components/SetRow.vue';

const props = defineProps({
    activity: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

const emits = defineEmits(['update-activity', 'add-set', 'remove-set', 'remove-activity', 'save']);

function onSetUpdate(set) {
    const updated = { ...props.activity, sets: props.activity.sets.map(s => (s.order === set.order ? set : s)) };
    emits('update-activity', updated);
}

function onSetRemove(order) {
    emits('remove-set', { activityId: props.activity.id, order });
}

function addSet() {
    emits('add-set', { activityId: props.activity.id });
}

function removeActivity() {
    emits('remove-activity', props.activity.id);
}

function save() {
    emits('save', props.activity.id);
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold">{{ activity.exercise_name }}</div>
            </div>

            <div class="flex items-center gap-2">
                <button v-if="editable" @click="addSet" class="text-sm text-indigo-600 hover:underline">Add Set</button>
                <button v-if="editable" @click="save" class="px-2 py-1 bg-indigo-600 text-white text-sm rounded">Save</button>
                <button v-if="editable" @click="removeActivity" class="text-sm text-red-600 hover:underline">Remove</button>
            </div>
        </div>

        <div class="space-y-1">
            <SetRow
                v-for="set in activity.sets"
                :key="set.order"
                :set="set"
                :editable="editable"
                @update="onSetUpdate"
                @remove="onSetRemove"
            />
        </div>
    </div>
</template>
