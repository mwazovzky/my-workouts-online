<script setup>
import { computed } from 'vue';
import catalog from 'virtual:public-catalog';
import CatalogExercise from '@/components/CatalogExercise.vue';
import NotFound from '@/pages/NotFound.vue';

const props = defineProps({
  slug: { type: String, required: true },
});

const exercise = computed(() => catalog.exercises[props.slug]);
</script>

<template>
  <CatalogExercise v-if="exercise" :key="slug" :slug="slug" />
  <NotFound v-else />
</template>
