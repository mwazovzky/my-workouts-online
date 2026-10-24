<script setup>
import PublicPageLayout from '@/layouts/PublicPageLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SUPPORT_EMAIL } from '@/lib/contact';
import { publicPagePath } from '@/lib/crawl';
import { useLocaleStore } from '@/stores/locale';
import { usePageMeta } from '@/composables/usePageMeta';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const localeStore = useLocaleStore();

usePageMeta({
  title: () => t('Support'),
  description: () =>
    t(
      'Need help with My Workouts Online? Write to support, or find out how to delete your account.'
    ),
  page: 'support',
});

const whatToInclude = [
  'The email address of your account',
  'Your device, and whether you use the website or the iOS app',
  'What you expected to happen and what happened instead',
];
</script>

<template>
  <PublicPageLayout>
    <Card class="border-0 shadow-none">
      <CardHeader>
        <CardTitle as="h1">{{ t('Support') }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-6">
        <p class="text-muted-foreground">
          {{ t('Need help with My Workouts Online? Write to us and we will get back to you.') }}
        </p>
        <p>
          <a
            :href="`mailto:${SUPPORT_EMAIL}`"
            class="text-lg font-semibold text-primary underline underline-offset-4"
          >
            {{ SUPPORT_EMAIL }}
          </a>
        </p>

        <section class="space-y-2">
          <h2 class="text-base font-semibold text-card-foreground">{{ t('Please include') }}</h2>
          <ul class="list-disc space-y-1 pl-5 text-sm leading-6 text-muted-foreground">
            <li v-for="item in whatToInclude" :key="item">{{ t(item) }}</li>
          </ul>
        </section>

        <section class="space-y-2 border-t border-border pt-4">
          <h2 class="text-base font-semibold text-card-foreground">
            {{ t('Deleting your account') }}
          </h2>
          <p class="text-sm leading-6 text-muted-foreground">
            {{
              t(
                'You can delete your account yourself at any time from the profile settings; this removes your account and all your workouts.'
              )
            }}
          </p>
          <p class="text-sm">
            <RouterLink
              :to="publicPagePath('privacy', localeStore.locale)"
              class="text-primary underline underline-offset-4"
            >
              {{ t('Privacy Policy') }}
            </RouterLink>
          </p>
        </section>
      </CardContent>
    </Card>
  </PublicPageLayout>
</template>
