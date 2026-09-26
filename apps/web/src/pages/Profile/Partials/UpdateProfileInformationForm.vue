<script setup>
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { toast } from 'vue-sonner';
import { computed, ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useApiForm } from '@/composables/useApiForm';
import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';

const { t } = useTranslation();
const auth = useAuthStore();

const mustVerifyEmail = computed(() => !auth.isVerified);
const status = ref(null);

const form = useApiForm({
  name: auth.user?.name ?? '',
  email: auth.user?.email ?? '',
});

function submit() {
  form.patch('/profile', {
    onSuccess: ({ data }) => auth.setUser(data.data),
    onFail: () => toast.error(t('Failed to save profile')),
  });
}

async function resendVerification() {
  try {
    await api.post('/auth/email/resend');
    status.value = 'verification-link-sent';
  } catch {
    toast.error(t('Failed to send verification email'));
  }
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-foreground">
        {{ t('Profile Information') }}
      </h2>

      <p class="mt-1 text-sm text-muted-foreground">
        {{ t("Update your account's profile information and email address.") }}
      </p>
    </header>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <div>
        <InputLabel for="name" :value="t('Name')" />

        <TextInput
          id="name"
          v-model="form.name"
          type="text"
          class="mt-1 block w-full"
          required
          autofocus
          autocomplete="name"
        />

        <InputError class="mt-2" :message="form.errors.name" />
      </div>

      <div>
        <InputLabel for="email" :value="t('Email')" />

        <TextInput
          id="email"
          v-model="form.email"
          type="email"
          class="mt-1 block w-full"
          required
          autocomplete="username"
        />

        <InputError class="mt-2" :message="form.errors.email" />
      </div>

      <div v-if="mustVerifyEmail">
        <p class="mt-2 text-sm text-foreground">
          {{ t('Your email address is unverified.') }}
          <button
            type="button"
            class="rounded-md text-sm text-primary underline underline-offset-4 hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background"
            @click="resendVerification"
          >
            {{ t('Click here to re-send the verification email.') }}
          </button>
        </p>

        <div
          v-show="status === 'verification-link-sent'"
          class="mt-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"
        >
          {{ t('A new verification link has been sent to your email address.') }}
        </div>
      </div>

      <div class="flex items-center gap-4">
        <PrimaryButton :disabled="form.processing">{{ t('Save') }}</PrimaryButton>

        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">
            {{ t('Saved.') }}
          </p>
        </Transition>
      </div>
    </form>
  </section>
</template>
