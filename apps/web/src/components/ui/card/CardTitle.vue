<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
  // The heading level; a page whose card holds its title passes `h1`.
  as: { type: String, default: 'h3' },
});
</script>

<template>
  <component :is="props.as" :class="cn('font-semibold leading-none tracking-tight', props.class)">
    <slot />
  </component>
</template>
