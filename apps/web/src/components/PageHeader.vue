<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <h2 class="-my-1 font-semibold leading-tight text-foreground">
    {{ title }}
  </h2>
</template>
