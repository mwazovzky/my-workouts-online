<script setup>
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SUPPORT_EMAIL } from '@/lib/contact';
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

usePageTitle(() => t('Privacy Policy'));

// One translation key per sentence, like the rest of the UI catalogs.
const sections = [
  {
    title: 'What we collect',
    paragraphs: [
      'Account details: your name, email address and a hashed password.',
      'Workout data: the programs you enroll in, the workouts you log, every set with its effort and difficulty values, and your language and theme preferences.',
      'Session details: a device name and the time of each sign-in, so you can tell your sessions apart and log them out.',
      'Server logs: request identifiers and IP addresses, kept for a short time to keep the service running and to investigate errors.',
    ],
  },
  {
    title: 'What we use it for',
    paragraphs: [
      'To sign you in, send verification and password reset emails, and show your own data back to you.',
      'To operate and secure the service.',
      'We do not use analytics, advertising, tracking or profiling, and we never sell or share your data for marketing.',
    ],
  },
  {
    title: 'Who else processes it',
    paragraphs: [
      'Our hosting provider stores the servers and the database.',
      'Verification and password reset emails are delivered through an email service provider.',
      'No other third party receives your data.',
    ],
  },
  {
    title: 'How long we keep it',
    paragraphs: [
      'Account and workout data stay until you delete your account. Server logs are kept for a short period only.',
    ],
  },
  {
    title: 'Your rights',
    paragraphs: [
      'You can view and edit your details in the app at any time.',
      'You can delete your account in the profile settings; this permanently removes your account and all your workouts.',
      'To ask for a copy of your data, or for anything else about this policy, write to us.',
    ],
  },
];
</script>

<template>
  <GuestLayout wide>
    <Card class="border-0 shadow-none">
      <CardHeader>
        <CardTitle>{{ t('Privacy Policy') }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-6">
        <p class="text-muted-foreground">
          {{
            t(
              'This policy explains what My Workouts Online stores about you, why, and how to remove it. It applies to the website and the iOS app.'
            )
          }}
        </p>
        <p class="text-sm text-muted-foreground">{{ t('Last updated: October 2026') }}</p>

        <section v-for="section in sections" :key="section.title" class="space-y-2">
          <h2 class="text-base font-semibold text-card-foreground">{{ t(section.title) }}</h2>
          <p
            v-for="paragraph in section.paragraphs"
            :key="paragraph"
            class="text-sm leading-6 text-muted-foreground"
          >
            {{ t(paragraph) }}
          </p>
        </section>

        <section class="space-y-2 border-t border-border pt-4">
          <h2 class="text-base font-semibold text-card-foreground">{{ t('Contact') }}</h2>
          <p class="text-sm leading-6 text-muted-foreground">
            <a :href="`mailto:${SUPPORT_EMAIL}`" class="text-primary underline underline-offset-4">
              {{ SUPPORT_EMAIL }}
            </a>
          </p>
        </section>
      </CardContent>
    </Card>
  </GuestLayout>
</template>
