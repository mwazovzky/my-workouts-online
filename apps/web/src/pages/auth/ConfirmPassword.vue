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

/**
 * Step-up re-authentication: proves the password and refreshes the access
 * token's auth_time, then returns to the intended route.
 */
const { t } = useTranslation();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

usePageTitle(() => t('Confirm Password'));

const form = useApiForm({
  password: '',
});

const submit = () => {
  form.post('/auth/reauth', {
    onSuccess: ({ data }) => {
      auth.accessToken = data.access_token;
      auth.recentlyAuthenticatedAt = Date.now();
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
    <div class="mb-4 text-sm text-muted-foreground">
      {{
        t(
          'This is a secure area of the application. Please confirm your password before continuing.'
        )
      }}
    </div>

    <form @submit.prevent="submit">
      <div>
        <InputLabel for="password" :value="t('Password')" />
        <TextInput
          id="password"
          v-model="form.password"
          type="password"
          class="mt-1 block w-full"
          required
          autocomplete="current-password"
          autofocus
        />
        <InputError class="mt-2" :message="form.errors.password" />
      </div>

      <div class="mt-4 flex justify-end">
        <PrimaryButton
          class="ms-4"
          :class="{ 'opacity-25': form.processing }"
          :disabled="form.processing"
        >
          {{ t('Confirm') }}
        </PrimaryButton>
      </div>
    </form>
  </GuestLayout>
</template>
