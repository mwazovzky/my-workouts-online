<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
  type: String,
  required: true,
});

const input = ref(null);

onMounted(() => {
  if (input.value.hasAttribute('autofocus')) {
    input.value.focus();
  }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
  <input
    ref="input"
    v-model="model"
    class="rounded-md border-input bg-background text-foreground shadow-sm focus:border-primary focus:ring-primary"
  />
</template>
