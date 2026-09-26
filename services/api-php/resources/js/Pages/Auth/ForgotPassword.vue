<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

defineProps({
  status: {
    type: String,
  },
});

const form = useForm({
  email: '',
});

const submit = () => {
  form.post(route('password.email'));
};
</script>

<template>
  <GuestLayout>
    <Head :title="t('Forgot Password')" />

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
