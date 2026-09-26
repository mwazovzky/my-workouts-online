<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import GuestLocaleSwitcher from '@/Components/GuestLocaleSwitcher.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="flex min-h-screen flex-col items-center bg-background text-foreground">
    <div class="flex w-full max-w-md items-center justify-between px-4 pt-6 sm:pt-8">
      <Link href="/" class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-2xl bg-primary/10 p-1.5 text-primary">
          <ApplicationLogo class="h-full w-full fill-current" />
        </div>
        <div class="text-sm font-semibold uppercase tracking-[0.18em] text-primary">
          My Workouts Online
        </div>
      </Link>
      <GuestLocaleSwitcher />
    </div>

    <div
      class="mt-6 w-full overflow-hidden rounded-lg border border-border bg-card px-6 py-4 shadow-md sm:max-w-md"
    >
      <slot />
    </div>
  </div>
</template>
