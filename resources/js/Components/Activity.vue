<script setup>
import Set from '@/Components/Set.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Plus, Save, Trash2 } from 'lucide-vue-next';

const props = defineProps({
  activity: { type: Object, required: true },
  editable: { type: Boolean, default: false },
});

const emits = defineEmits(['update-activity', 'add-set', 'remove-set', 'remove-activity', 'save']);

function onSetUpdate(set) {
  const updated = {
    ...props.activity,
    sets: props.activity.sets.map(s => {
      if (set.id) {
        return s.id === set.id ? set : s;
      }

      return s.order === set.order ? set : s;
    }),
  };
  emits('update-activity', updated);
}

function onSetRemove({ id, order }) {
  emits('remove-set', { activityId: props.activity.id, id, order });
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
  <Card class="max-w-md mx-auto">
    <CardHeader class="pb-3 border-b">
      <div class="flex items-center justify-between gap-4">
        <CardTitle class="text-base">{{ activity.exercise_name }}</CardTitle>
        <div v-if="editable" class="flex items-center gap-1 bg-muted/50 p-1 rounded-md">
          <Button variant="outline" size="sm" class="h-8" @click="addSet">
            <Plus class="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            class="h-8 active:scale-95 active:shadow-inner transition-transform"
            @click="save"
          >
            <Save class="h-4 w-4" />
          </Button>
          <Button variant="destructive" size="sm" class="h-8" @click="removeActivity">
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </CardHeader>

    <CardContent class="py-3">
      <div class="space-y-2">
        <!-- Column Headers -->
        <div class="flex items-end gap-2 pb-1">
          <div class="w-12"></div>
          <div class="flex gap-3 flex-1">
            <div class="flex-1">
              <label class="text-xs text-gray-500 dark:text-gray-400 text-right block pr-3"
                >Reps</label
              >
            </div>
            <div class="flex-1">
              <label class="text-xs text-gray-500 dark:text-gray-400 text-right block pr-3"
                >Weight</label
              >
            </div>
          </div>
          <div class="w-8"></div>
        </div>

        <!-- Set Rows -->
        <Set
          v-for="set in activity.sets"
          :key="set.id ?? set.order"
          :set="set"
          :editable="editable"
          @update="onSetUpdate"
          @remove="onSetRemove"
        />
      </div>
    </CardContent>
  </Card>
</template>
