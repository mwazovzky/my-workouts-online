<script setup>
import { reactive, computed, watch } from 'vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
  set: { type: Object, required: true },
  editable: { type: Boolean, default: false },
  effortType: { type: String, default: 'repetitions' },
  difficultyUnit: { type: String, default: null },
});

const emits = defineEmits(['update', 'remove']);

const hasDifficulty = computed(() => props.difficultyUnit && props.difficultyUnit !== 'none');

const local = reactive({
  id: props.set.id ?? null,
  order: props.set.order,
  effort_value: props.set.effort_value ?? 0,
  difficulty_value: props.set.difficulty_value ?? 0,
});

watch(
  () => [local.effort_value, local.difficulty_value],
  () => {
    emits('update', {
      id: local.id,
      order: local.order,
      effort_value: Number(local.effort_value),
      difficulty_value: hasDifficulty.value ? Number(local.difficulty_value) : null,
    });
  }
);

function remove() {
  emits('remove', { id: local.id, order: local.order });
}
</script>

<template>
  <div class="flex items-center gap-2">
    <div class="w-12 text-sm font-medium text-muted-foreground">
      {{ t('Set :order', { order: local.order }) }}
    </div>

    <div class="flex gap-4 flex-1">
      <div class="flex flex-col gap-1 flex-1">
        <template v-if="editable">
          <Input
            v-model.number="local.effort_value"
            type="number"
            min="0"
            class="h-9 text-right transition-colors hover:border-primary/50"
          />
        </template>
        <template v-else>
          <Input
            :value="props.set.effort_value"
            type="number"
            disabled
            class="h-9 text-right disabled:opacity-100 disabled:cursor-default disabled:text-foreground"
          />
        </template>
      </div>

      <div v-if="hasDifficulty" class="flex flex-col gap-1 flex-1">
        <template v-if="editable">
          <Input
            v-model.number="local.difficulty_value"
            type="number"
            min="0"
            step="0.5"
            class="h-9 text-right transition-colors hover:border-primary/50"
          />
        </template>
        <template v-else>
          <Input
            :value="props.set.difficulty_value"
            type="number"
            disabled
            class="h-9 text-right disabled:opacity-100 disabled:cursor-default disabled:text-foreground"
          />
        </template>
      </div>
    </div>

    <div>
      <Button v-if="editable" variant="destructive" size="sm" class="h-8 w-8 p-0" @click="remove">
        <Trash2 class="h-4 w-4" />
      </Button>
    </div>
  </div>
</template>
