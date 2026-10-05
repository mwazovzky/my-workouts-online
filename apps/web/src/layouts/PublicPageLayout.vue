<script setup>
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import PageLayout from '@/components/PageLayout.vue';
import { useAuthStore } from '@/stores/auth';

// Public pages (privacy policy, support) keep the app navigation for signed-in users.
const auth = useAuthStore();
</script>

<template>
  <AuthenticatedLayout v-if="auth.isAuthenticated">
    <PageLayout>
      <div class="rounded-lg border border-border bg-card shadow-sm">
        <slot />
      </div>
    </PageLayout>
  </AuthenticatedLayout>
  <GuestLayout v-else wide>
    <slot />
  </GuestLayout>
</template>
