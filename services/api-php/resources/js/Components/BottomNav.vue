<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const page = usePage();

const isAdmin = computed(() => page.props.auth.user?.is_admin ?? false);

const tabs = computed(() => {
  const items = [
    { label: 'Dashboard', route: 'dashboard', current: 'dashboard' },
    { label: 'Programs', route: 'programs.index', current: 'programs.*' },
    { label: 'Workouts', route: 'workouts.index', current: 'workouts.*' },
    { label: 'Profile', route: 'profile.edit', current: 'profile.*' },
  ];

  if (isAdmin.value) {
    items.push({ label: 'Admin', route: 'admin.index', current: 'admin.*' });
  }

  return items;
});
</script>

<template>
  <nav
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 pt-2 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:hidden"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom))"
  >
    <div class="flex items-stretch justify-around">
      <Link
        v-for="tab in tabs"
        :key="tab.route"
        :href="route(tab.route)"
        class="flex flex-1 flex-col items-center gap-0.5 px-1 py-1 text-xs transition-colors"
        :class="
          route().current(tab.current)
            ? 'text-primary'
            : 'text-muted-foreground hover:text-foreground'
        "
      >
        <!-- Dashboard icon -->
        <svg
          v-if="tab.route === 'dashboard'"
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
          />
        </svg>

        <!-- Programs icon -->
        <svg
          v-else-if="tab.route === 'programs.index'"
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M4 6h16M4 10h16M4 14h16M4 18h16"
          />
        </svg>

        <!-- Workouts icon -->
        <svg
          v-else-if="tab.route === 'workouts.index'"
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>

        <!-- Profile icon -->
        <svg
          v-else-if="tab.route === 'profile.edit'"
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
          />
        </svg>

        <!-- Admin icon -->
        <svg
          v-else-if="tab.route === 'admin.index'"
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.75"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
          />
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
          />
        </svg>

        <span>{{ t(tab.label) }}</span>
      </Link>
    </div>
  </nav>
</template>
