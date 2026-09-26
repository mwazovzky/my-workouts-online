<script setup>
import { useRouter } from 'vue-router';
import { useTranslation } from '@/composables/useTranslation';
import { useAuthStore } from '@/stores/auth';

const { t } = useTranslation();
const auth = useAuthStore();
const router = useRouter();

async function logout() {
  await auth.logout();
  router.push({ name: 'welcome' });
}
</script>

<template>
  <main class="mx-auto flex max-w-md flex-col gap-4 p-6">
    <h1 class="text-2xl font-semibold">{{ t('Dashboard') }}</h1>
    <p>{{ auth.user?.name }}</p>
    <button type="button" class="text-primary underline" @click="logout">{{ t('Log Out') }}</button>
  </main>
</template>
