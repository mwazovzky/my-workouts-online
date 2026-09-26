<script setup>
import { useRouter, useRoute } from 'vue-router';
import { useApiForm } from '@/composables/useApiForm';
import { useTranslation } from '@/composables/useTranslation';
import { useAuthStore } from '@/stores/auth';

const { t } = useTranslation();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = useApiForm({ email: '', password: '', client: 'web' });

async function submit() {
  await form.post('/auth/login', {
    onSuccess: ({ data }) => {
      auth.applySession(data);
      router.push(route.query.redirect || { name: 'dashboard' });
    },
  });
}
</script>

<template>
  <main class="mx-auto flex max-w-md flex-col gap-4 p-6">
    <h1 class="text-2xl font-semibold">{{ t('Log in') }}</h1>
    <form class="flex flex-col gap-3" @submit.prevent="submit">
      <input
        v-model="form.email"
        type="email"
        autocomplete="username"
        class="rounded-md border-input bg-background"
        :placeholder="t('Email')"
      />
      <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
      <input
        v-model="form.password"
        type="password"
        autocomplete="current-password"
        class="rounded-md border-input bg-background"
        :placeholder="t('Password')"
      />
      <button
        type="submit"
        class="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
        :disabled="form.processing"
      >
        {{ t('Log in') }}
      </button>
    </form>
  </main>
</template>
