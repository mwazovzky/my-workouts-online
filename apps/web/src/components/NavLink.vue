<script setup>
import { computed } from 'vue';

const props = defineProps({
  to: {
    type: [String, Object],
    required: true,
  },
  active: {
    type: Boolean,
  },
});

const classes = computed(() =>
  props.active
    ? 'inline-flex items-center border-b-2 border-primary px-1 pt-1 text-sm font-medium leading-5 text-foreground transition duration-150 ease-in-out focus:border-primary focus:outline-none'
    : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-muted-foreground transition duration-150 ease-in-out hover:border-border hover:text-foreground focus:border-border focus:text-foreground focus:outline-none'
);
</script>

<template>
  <RouterLink :to="to" :class="classes">
    <slot />
  </RouterLink>
</template>
