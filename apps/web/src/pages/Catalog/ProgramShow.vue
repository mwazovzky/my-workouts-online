<script setup>
import { computed } from 'vue';
import catalog from 'virtual:public-catalog';
import CatalogProgram from '@/components/CatalogProgram.vue';
import NotFound from '@/pages/NotFound.vue';

const props = defineProps({
  slug: { type: String, required: true },
});

const program = computed(() => catalog.programs.find(candidate => candidate.slug === props.slug));
</script>

<template>
  <CatalogProgram v-if="program" :key="program.slug" :program="program" />
  <NotFound v-else />
</template>
