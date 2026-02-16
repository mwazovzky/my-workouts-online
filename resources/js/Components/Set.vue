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
});

const emits = defineEmits(['update', 'remove', 'completion-toggled']);

const local = reactive({
  id: props.set.id ?? null,
  order: props.set.order,
  repetitions: props.set.repetitions ?? 0,
  weight: props.set.weight ?? 0,
  is_completed: props.set.is_completed ?? false,
});

const inputsDisabled = computed(() => !props.editable || local.is_completed);
const canComplete = computed(() => props.editable && Number(local.repetitions) > 0);

watch(
  () => [local.repetitions, local.weight],
  () => {
    // Auto-uncheck if reps changed to 0 while completed
    if (local.is_completed && Number(local.repetitions) <= 0) {
      local.is_completed = false;
    }

    emits('update', {
      id: local.id,
      order: local.order,
      repetitions: Number(local.repetitions),
      weight: Number(local.weight),
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
    repetitions: Number(local.repetitions),
    weight: Number(local.weight),
    is_completed: local.is_completed,
  });

  emits('completion-toggled', {
    id: local.id,
    order: local.order,
    repetitions: Number(local.repetitions),
    weight: Number(local.weight),
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
    class="group grid grid-cols-[2rem_1fr_1fr_2.25rem_2.25rem] items-center gap-2"
    :class="{ 'opacity-70': local.is_completed }"
  >
    <div class="text-xs font-medium tabular-nums text-muted-foreground text-center">
      {{ local.order }}
    </div>

    <Input
      v-model.number="local.repetitions"
      type="number"
      min="0"
      :disabled="inputsDisabled"
      class="h-9 text-right tabular-nums"
    />

    <Input
      v-model.number="local.weight"
      type="number"
      min="0"
      step="0.5"
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
