<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <h2 class="font-semibold text-gray-600 leading-tight -my-1">
    {{ title }}
  </h2>
</template>
