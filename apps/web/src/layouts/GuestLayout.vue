<script setup>
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import GuestLocaleSwitcher from '@/components/GuestLocaleSwitcher.vue';
import LegalLinks from '@/components/LegalLinks.vue';
import { publicPagePath } from '@/lib/crawl';
import { useLocaleStore } from '@/stores/locale';

defineProps({
  // Forms stay narrow; long-form pages such as the privacy policy use the wide variant.
  wide: { type: Boolean, default: false },
});

const localeStore = useLocaleStore();
</script>

<template>
  <div class="flex min-h-screen flex-col items-center bg-background text-foreground">
    <div
      class="flex w-full items-center justify-between px-4 pt-6 sm:pt-8"
      :class="wide ? 'max-w-2xl' : 'max-w-md'"
    >
      <RouterLink
        :to="publicPagePath('welcome', localeStore.locale)"
        class="flex items-center gap-3"
      >
        <div class="h-10 w-10 rounded-2xl bg-primary/10 p-1.5 text-primary">
          <ApplicationLogo class="h-full w-full fill-current" />
        </div>
        <div class="text-sm font-semibold uppercase tracking-[0.18em] text-primary">
          My Workouts Online
        </div>
      </RouterLink>
      <GuestLocaleSwitcher />
    </div>

    <div
      class="mt-6 w-full overflow-hidden rounded-lg border border-border bg-card px-6 py-4 shadow-md"
      :class="wide ? 'sm:max-w-2xl' : 'sm:max-w-md'"
    >
      <slot />
    </div>

    <LegalLinks class="my-6" />
  </div>
</template>
