<script setup>
import { computed, reactive, watch } from 'vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Check, Minus } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
  set: { type: Object, required: true },
  editable: { type: Boolean, default: false },
  effortType: { type: String, default: 'repetitions' },
  difficultyUnit: { type: String, default: null },
});

const emits = defineEmits(['update', 'remove', 'completion-toggled']);

const hasDifficulty = computed(() => props.difficultyUnit && props.difficultyUnit !== 'none');

const local = reactive({
  id: props.set.id ?? null,
  order: props.set.order,
  effort_value: props.set.effort_value ?? 0,
  difficulty_value: props.set.difficulty_value ?? 0,
  is_completed: props.set.is_completed ?? false,
});

const inputsDisabled = computed(() => !props.editable || local.is_completed);
const canComplete = computed(() => props.editable && Number(local.effort_value) > 0);

watch(
  () => [local.effort_value, local.difficulty_value],
  () => {
    // Auto-uncheck if effort changed to 0 while completed
    if (local.is_completed && Number(local.effort_value) <= 0) {
      local.is_completed = false;
    }

    emits('update', {
      id: local.id,
      order: local.order,
      effort_value: Number(local.effort_value),
      difficulty_value: hasDifficulty.value ? Number(local.difficulty_value) : null,
      is_completed: local.is_completed,
    });
  }
);

watch(
  () => props.set.is_completed,
  val => {
    local.is_completed = !!val;
  }
);

function onCheckedUpdate(val) {
  if (!props.editable) {
    return;
  }

  const previous = local.is_completed;
  local.is_completed = !!val;

  emits('update', {
    id: local.id,
    order: local.order,
    effort_value: Number(local.effort_value),
    difficulty_value: hasDifficulty.value ? Number(local.difficulty_value) : null,
    is_completed: local.is_completed,
  });

  emits('completion-toggled', {
    id: local.id,
    order: local.order,
    effort_value: Number(local.effort_value),
    difficulty_value: hasDifficulty.value ? Number(local.difficulty_value) : null,
    is_completed: local.is_completed,
    previous,
  });
}

function remove() {
  emits('remove', { id: local.id, order: local.order });
}
</script>

<template>
  <div
    class="group grid items-center gap-2 grid-cols-[2rem_1fr_1fr_2.25rem_2.25rem]"
    :class="{ 'opacity-70': local.is_completed }"
  >
    <div class="text-xs font-medium tabular-nums text-muted-foreground text-center">
      {{ local.order }}
    </div>

    <Input
      v-if="hasDifficulty"
      v-model.number="local.difficulty_value"
      type="number"
      min="0"
      step="0.5"
      placeholder="0"
      :disabled="inputsDisabled"
      class="h-9 text-right tabular-nums"
    />
    <div v-else />

    <Input
      v-model.number="local.effort_value"
      type="number"
      min="0"
      placeholder="0"
      :disabled="inputsDisabled"
      class="h-9 text-right tabular-nums"
    />

    <Button
      v-if="editable"
      :variant="local.is_completed ? 'secondary' : 'outline'"
      size="icon"
      :disabled="!canComplete"
      :class="local.is_completed ? 'text-foreground' : 'text-muted-foreground'"
      :aria-label="
        local.is_completed
          ? t('Mark set :order as incomplete', { order: local.order })
          : t('Mark set :order as complete', { order: local.order })
      "
      @click="onCheckedUpdate(!local.is_completed)"
    >
      <Check v-if="local.is_completed" class="h-4 w-4" />
    </Button>
    <div
      v-else
      class="h-9 w-9 rounded-md border border-input bg-background flex items-center justify-center opacity-60"
      aria-hidden="true"
    >
      <Check v-if="local.is_completed" class="h-4 w-4" />
    </div>

    <Button
      v-if="editable"
      variant="outline"
      size="icon"
      class="text-muted-foreground hover:text-destructive hover:bg-destructive/10"
      :aria-label="t('Remove set :order', { order: local.order })"
      @click="remove"
    >
      <Minus class="h-4 w-4" />
    </Button>
    <div v-else class="h-9 w-9" />
  </div>
</template>
