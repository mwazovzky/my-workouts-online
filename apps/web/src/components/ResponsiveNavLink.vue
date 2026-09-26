<script setup>
import { computed } from 'vue';

const props = defineProps({
  to: {
    type: [String, Object],
    required: true,
  },
  active: {
    type: Boolean,
    default: false,
  },
});

const classes = computed(() =>
  props.active
    ? 'block w-full rounded-md bg-primary/10 px-4 py-2 text-left text-sm font-medium text-primary focus:bg-primary/15 focus:outline-none'
    : 'block w-full rounded-md px-4 py-2 text-left text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none'
);
</script>

<template>
  <RouterLink :to="to" :class="classes">
    <slot />
  </RouterLink>
</template>
