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

const { t } = useTranslation();
const route = useRoute();
const router = useRouter();

usePageTitle(() => t('Reset Password'));

const form = useApiForm({
  token: String(route.params.token ?? ''),
  email: String(route.query.email ?? ''),
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post('/auth/password/reset', {
    onSuccess: () => router.push({ name: 'login', query: { reset: 1 } }),
    onFinish: () => {
      form.password = '';
      form.password_confirmation = '';
    },
  });
};
</script>

<template>
  <GuestLayout>
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
          autocomplete="new-password"
        />

        <InputError class="mt-2" :message="form.errors.password" />
      </div>

      <div class="mt-4">
        <InputLabel for="password_confirmation" :value="t('Confirm Password')" />

        <TextInput
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          class="mt-1 block w-full"
          required
          autocomplete="new-password"
        />

        <InputError class="mt-2" :message="form.errors.password_confirmation" />
      </div>

      <InputError class="mt-2" :message="form.errors.token" />

      <div class="mt-4 flex items-center justify-end">
        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
          {{ t('Reset Password') }}
        </PrimaryButton>
      </div>
    </form>
  </GuestLayout>
</template>
