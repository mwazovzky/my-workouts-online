<script setup>
import { ref } from 'vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useApiForm } from '@/composables/useApiForm';
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

usePageTitle(() => t('Forgot Password'));

const form = useApiForm({
  email: '',
});

const status = ref(null);

const submit = () => {
  form.post('/auth/password/forgot', {
    onSuccess: () => {
      status.value = t('We have emailed your password reset link.');
    },
  });
};
</script>

<template>
  <GuestLayout>
    <div class="mb-4 text-sm text-muted-foreground">
      {{
        t(
          'Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.'
        )
      }}
    </div>

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

      <div class="mt-4 flex items-center justify-end">
        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
          {{ t('Email Password Reset Link') }}
        </PrimaryButton>
      </div>
    </form>
  </GuestLayout>
</template>
