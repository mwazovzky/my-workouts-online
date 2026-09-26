<script setup>
import { ref } from 'vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { useLogout } from '@/composables/useLogout';
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslation } from '@/composables/useTranslation';
import { api } from '@/lib/api';

const { t } = useTranslation();
const { logout } = useLogout();

usePageTitle(() => t('Email Verification'));

const processing = ref(false);
const verificationLinkSent = ref(false);
const error = ref(null);

async function submit() {
  processing.value = true;
  error.value = null;

  try {
    await api.post('/auth/email/resend');
    verificationLinkSent.value = true;
  } catch (e) {
    error.value = e.response?.data?.message ?? t('Failed to send verification email');
  } finally {
    processing.value = false;
  }
}
</script>

<template>
  <GuestLayout>
    <div class="mb-4 text-sm text-muted-foreground">
      {{
        t(
          "Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another."
        )
      }}
    </div>

    <div
      v-if="verificationLinkSent"
      class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"
    >
      {{
        t(
          'A new verification link has been sent to the email address you provided during registration.'
        )
      }}
    </div>

    <div v-if="error" class="mb-4 text-sm text-destructive">{{ error }}</div>

    <form @submit.prevent="submit">
      <div class="mt-4 flex items-center justify-between">
        <PrimaryButton :class="{ 'opacity-25': processing }" :disabled="processing">
          {{ t('Resend Verification Email') }}
        </PrimaryButton>

        <button
          type="button"
          class="rounded-md text-sm text-primary underline underline-offset-4 hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background"
          @click="logout"
        >
          {{ t('Log Out') }}
        </button>
      </div>
    </form>
  </GuestLayout>
</template>
