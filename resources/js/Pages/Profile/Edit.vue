<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdateLanguageForm from './Partials/UpdateLanguageForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdateThemeForm from './Partials/UpdateThemeForm.vue';
import { useTranslation } from '@/composables/useTranslation.js';
import { Head, Link } from '@inertiajs/vue3';

const { t } = useTranslation();

defineProps({
  mustVerifyEmail: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});
</script>

<template>
  <Head :title="t('Profile')" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-foreground">
        {{ t('Profile') }}
      </h2>
    </template>

    <div class="py-4 sm:py-12">
      <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-8">
          <UpdateProfileInformationForm
            :must-verify-email="mustVerifyEmail"
            :status="status"
            class="max-w-xl"
          />
        </div>

        <div class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-8">
          <UpdateLanguageForm class="max-w-xl" />
        </div>

        <div class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-8">
          <UpdateThemeForm class="max-w-xl" />
        </div>

        <div class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-8">
          <UpdatePasswordForm class="max-w-xl" />
        </div>

        <div class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-8">
          <DeleteUserForm class="max-w-xl" />
        </div>

        <div class="flex items-center justify-between px-4 sm:hidden">
          <Link :href="route('about')" class="text-sm text-muted-foreground hover:text-foreground">
            {{ t('About') }}
          </Link>
          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="text-sm font-medium text-destructive hover:text-destructive/80"
          >
            {{ t('Log Out') }}
          </Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
