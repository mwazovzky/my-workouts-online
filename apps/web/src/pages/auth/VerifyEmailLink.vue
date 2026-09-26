<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslation } from '@/composables/useTranslation';
import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';

/**
 * Target of the emailed verification link: posts the signed parameters to the
 * API, then continues to the dashboard.
 */
const { t } = useTranslation();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

usePageTitle(() => t('Email Verification'));

const error = ref(null);

onMounted(async () => {
  try {
    await api.post('/auth/email/verify', {
      id: Number(route.params.id),
      hash: route.params.hash,
      expires: Number(route.query.expires),
      signature: route.query.signature,
    });
    await auth.fetchUser();
    router.replace({ name: 'dashboard', query: { verified: 1 } });
  } catch (e) {
    error.value =
      e.response?.data?.message ?? t('This verification link is invalid or has expired.');
  }
});
</script>

<template>
  <GuestLayout>
    <div v-if="error" class="space-y-4 text-sm">
      <p class="text-destructive">{{ error }}</p>
      <RouterLink
        :to="{ name: 'verification.notice' }"
        class="text-primary underline underline-offset-4"
      >
        {{ t('Resend Verification Email') }}
      </RouterLink>
    </div>
    <p v-else class="text-sm text-muted-foreground">{{ t('Verifying your email address…') }}</p>
  </GuestLayout>
</template>
