<script setup>
import { useRoute, useRouter } from 'vue-router';
import GuestLayout from '@/layouts/GuestLayout.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useApiForm } from '@/composables/useApiForm';
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslation } from '@/composables/useTranslation';
import { useAuthStore } from '@/stores/auth';

const { t } = useTranslation();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

usePageTitle(() => t('Log in'));

const form = useApiForm({
  email: '',
  password: '',
  client: 'web',
});

const status = route.query.reset ? t('Your password has been reset.') : null;

const submit = () => {
  form.post('/auth/login', {
    onSuccess: ({ data }) => {
      auth.applySession(data);
      router.push(route.query.redirect || { name: 'dashboard' });
    },
    onFinish: () => {
      form.password = '';
    },
  });
};
</script>

<template>
  <GuestLayout>
    <div
      v-if="status"
      class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"
    >
      {{ status }}
    </div>

    <form @submit.prevent="submit">
      <div>
        <InputLabel for="email" :value="t('Email')" />

        <TextInput
          id="email"
          v-model="form.email"
          type="email"
          class="mt-1 block w-full"
          required
          autofocus
          autocomplete="username"
        />

        <InputError class="mt-2" :message="form.errors.email" />
      </div>

      <div class="mt-4">
        <InputLabel for="password" :value="t('Password')" />

        <TextInput
          id="password"
          v-model="form.password"
          type="password"
          class="mt-1 block w-full"
          required
          autocomplete="current-password"
        />

        <InputError class="mt-2" :message="form.errors.password" />
      </div>

      <div class="mt-4 flex items-center justify-end">
        <RouterLink
          :to="{ name: 'password.request' }"
          class="rounded-md text-sm text-primary underline underline-offset-4 hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background"
        >
          {{ t('Forgot your password?') }}
        </RouterLink>

        <PrimaryButton
          class="ms-4"
          :class="{ 'opacity-25': form.processing }"
          :disabled="form.processing"
        >
          {{ t('Log in') }}
        </PrimaryButton>
      </div>

      <div class="mt-4 text-center text-sm text-muted-foreground">
        <RouterLink :to="{ name: 'register' }" class="text-primary underline underline-offset-4">
          {{ t('Register') }}
        </RouterLink>
      </div>
    </form>
  </GuestLayout>
</template>
