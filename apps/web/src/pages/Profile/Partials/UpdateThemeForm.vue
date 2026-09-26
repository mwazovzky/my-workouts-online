<script setup>
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { useTranslation } from '@/composables/useTranslation.js';
import { toast } from 'vue-sonner';
import { computed } from 'vue';
import { useApiForm } from '@/composables/useApiForm';
import { applyThemePreference, currentThemePreference } from '@/lib/theme';
import { useAuthStore } from '@/stores/auth';

const { t } = useTranslation();
const auth = useAuthStore();

const availableThemePreferences = computed(() => ({
  light: t('Light'),
  dark: t('Dark'),
  system: t('Match device'),
}));

const form = useApiForm({
  theme_preference: auth.user?.theme_preference ?? currentThemePreference(),
});

function submit() {
  form.patch('/profile/theme', {
    onSuccess: ({ data }) => {
      auth.setUser(data.data);
      applyThemePreference(form.theme_preference);
    },
    onFail: () => toast.error(t('Failed to save theme')),
  });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-foreground">{{ t('Theme') }}</h2>

      <p class="mt-1 text-sm text-muted-foreground">
        {{ t('Choose how the app should handle light and dark appearance.') }}
      </p>
    </header>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <div>
        <InputLabel for="theme_preference" :value="t('Theme')" />

        <select
          id="theme_preference"
          v-model="form.theme_preference"
          class="mt-1 block w-full rounded-md border-input bg-background text-foreground shadow-sm focus:border-primary focus:ring-primary"
        >
          <option v-for="(label, code) in availableThemePreferences" :key="code" :value="code">
            {{ t(label) }}
          </option>
        </select>

        <InputError class="mt-2" :message="form.errors.theme_preference" />
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
