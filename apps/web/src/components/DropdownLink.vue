<script setup>
defineProps({
  to: {
    type: [String, Object],
    required: true,
  },
});
</script>

<template>
  <RouterLink
    :to="to"
    class="block w-full px-4 py-2 text-start text-sm leading-5 text-popover-foreground transition duration-150 ease-in-out hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
  >
    <slot />
  </RouterLink>
</template>
