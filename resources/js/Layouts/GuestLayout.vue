<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import GuestLocaleSwitcher from '@/Components/GuestLocaleSwitcher.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div
    class="relative flex min-h-screen flex-col items-center bg-background pt-6 text-foreground sm:justify-center sm:pt-0"
  >
    <div class="absolute right-6 top-6">
      <GuestLocaleSwitcher />
    </div>

    <div>
      <Link href="/">
        <ApplicationLogo class="h-28 w-28 fill-current text-primary" />
      </Link>
    </div>

    <div
      class="mt-6 w-full overflow-hidden rounded-lg border border-border bg-card px-6 py-4 shadow-md sm:max-w-md"
    >
      <slot />
    </div>
  </div>
</template>
