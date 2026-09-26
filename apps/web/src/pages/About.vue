<script setup>
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageLayout from '@/components/PageLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslation } from '@/composables/useTranslation';

const version = import.meta.env.VITE_APP_VERSION || 'dev';

const { t } = useTranslation();

usePageTitle(() => t('About'));
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('About')" />
    </template>

    <PageLayout>
      <Card>
        <CardHeader>
          <CardTitle>{{ t('My Workouts Online') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <p class="text-muted-foreground">
            {{ t('Workout planning and logging') }}
          </p>
          <div class="flex items-center gap-3 border-t border-border pt-4">
            <span class="text-sm text-muted-foreground">{{ t('Version') }}</span>
            <span class="rounded-full bg-primary/10 px-3 py-0.5 text-sm font-medium text-primary">
              {{ version }}
            </span>
          </div>
        </CardContent>
      </Card>
    </PageLayout>
  </AuthenticatedLayout>
</template>
