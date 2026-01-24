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
    ? 'block w-full px-4 py-2 text-left text-sm font-medium text-indigo-700 bg-indigo-100 rounded-md focus:outline-none focus:bg-indigo-200'
    : 'block w-full px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md focus:outline-none focus:bg-gray-100 focus:text-gray-900'
);
</script>

<template>
  <Link :href="href" :class="classes" :method="method" :as="as">
    <slot />
  </Link>
</template>
