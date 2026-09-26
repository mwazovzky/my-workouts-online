<script setup>
import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import { Search } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps({
  show: { type: Boolean, default: false },
  exercises: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emits = defineEmits(['select', 'close']);

const query = ref('');

watch(
  () => props.show,
  isOpen => {
    if (isOpen) {
      query.value = '';
    }
  }
);

const filteredExercises = computed(() => {
  const needle = query.value.trim().toLowerCase();

  if (!needle) {
    return props.exercises;
  }

  return props.exercises.filter(exercise => (exercise.name ?? '').toLowerCase().includes(needle));
});

function describe(exercise) {
  const categories = (exercise.categories ?? []).map(category => category.name).join(', ');

  return [exercise.equipment_name, categories].filter(Boolean).join(' · ');
}
</script>

<template>
  <Modal :show="show" max-width="md" @close="emits('close')">
    <div class="p-4">
      <h2 class="text-base font-semibold text-foreground">{{ t('Add activity') }}</h2>

      <div class="relative mt-3">
        <Search
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          v-model="query"
          type="search"
          class="pl-9"
          :placeholder="t('Search exercises…')"
          :aria-label="t('Search exercises…')"
        />
      </div>

      <div class="mt-3 max-h-80 overflow-y-auto">
        <div v-if="loading" class="space-y-2 py-1">
          <Skeleton v-for="i in 4" :key="i" class="h-12 w-full rounded-md" />
        </div>

        <p
          v-else-if="!filteredExercises.length"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          {{ t('No exercises found') }}
        </p>

        <ul v-else class="divide-y divide-border">
          <li v-for="exercise in filteredExercises" :key="exercise.id">
            <button
              type="button"
              class="w-full rounded-md px-2 py-2.5 text-left transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              @click="emits('select', exercise)"
            >
              <span class="block text-sm font-medium text-foreground">{{ exercise.name }}</span>
              <span class="block text-xs text-muted-foreground">{{
                describe(exercise) || '—'
              }}</span>
            </button>
          </li>
        </ul>
      </div>

      <div class="mt-4 flex justify-end">
        <Button variant="outline" size="sm" @click="emits('close')">{{ t('Cancel') }}</Button>
      </div>
    </div>
  </Modal>
</template>
