<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  href: {
    type: String,
    required: true,
  },
  active: {
    type: Boolean,
    default: false,
  },
  method: {
    type: String,
    default: 'get',
  },
  as: {
    type: String,
    default: 'a',
  },
});

const classes = computed(() =>
  props.active
    ? 'block w-full rounded-md bg-primary/10 px-4 py-2 text-left text-sm font-medium text-primary focus:bg-primary/15 focus:outline-none'
    : 'block w-full rounded-md px-4 py-2 text-left text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none'
);
</script>

<template>
  <Link :href="href" :class="classes" :method="method" :as="as">
    <slot />
  </Link>
</template>
