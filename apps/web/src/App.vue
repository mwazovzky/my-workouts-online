<script setup>
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <RouterView v-if="auth.ready" />
    <div v-else class="flex min-h-screen items-center justify-center text-muted-foreground">
      <span class="animate-pulse">…</span>
    </div>
  </div>
</template>
