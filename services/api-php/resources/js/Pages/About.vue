<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PageLayout from '@/Components/PageLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useTranslation } from '@/composables/useTranslation';
import { Head } from '@inertiajs/vue3';

defineProps({
  version: {
    type: String,
    required: true,
  },
});

const { t } = useTranslation();
</script>

<template>
  <Head :title="t('About')" />

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
