<script setup>
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { useTranslation } from '@/composables/useTranslation.js';
import { toast } from 'vue-sonner';
import { computed, onMounted } from 'vue';
import { useApiForm } from '@/composables/useApiForm';
import { useAuthStore } from '@/stores/auth';
import { useLocaleStore } from '@/stores/locale';
import { useMetaStore } from '@/stores/meta';

const { t } = useTranslation();
const auth = useAuthStore();
const localeStore = useLocaleStore();
const meta = useMetaStore();

onMounted(() => meta.load().catch(() => {}));

const availableLocales = computed(() => meta.availableLocales);

const form = useApiForm({
  locale: localeStore.locale,
});

function submit() {
  form.patch('/profile/locale', {
    onSuccess: ({ data }) => {
      auth.setUser(data.data);
      localeStore.setLocale(form.locale);
    },
    onFail: () => toast.error(t('Failed to save language')),
  });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-foreground">{{ t('Language') }}</h2>

      <p class="mt-1 text-sm text-muted-foreground">
        {{ t('Select your preferred language.') }}
      </p>
    </header>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <div>
        <InputLabel for="locale" :value="t('Language')" />

        <select
          id="locale"
          v-model="form.locale"
          class="mt-1 block w-full rounded-md border-input bg-background text-foreground shadow-sm focus:border-primary focus:ring-primary"
        >
          <option v-for="(label, code) in availableLocales" :key="code" :value="code">
            {{ label }}
          </option>
        </select>

        <InputError class="mt-2" :message="form.errors.locale" />
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
