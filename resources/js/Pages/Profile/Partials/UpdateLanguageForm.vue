<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useTranslation } from '@/composables/useTranslation.js';
import { useForm, usePage } from '@inertiajs/vue3';

const { t } = useTranslation();
const page = usePage();

const availableLocales = page.props.availableLocales;

const form = useForm({
  locale: page.props.locale,
});

function submit() {
  form.patch(route('profile.locale'), {
    preserveScroll: true,
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
