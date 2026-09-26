<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Admin')" />
    </template>

    <PageLayout>
      <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="section in sections" :key="section.route">
          <RouterLink :to="{ name: section.route }" class="block h-full">
            <Card class="h-full p-6 transition-colors hover:bg-accent">
              <div class="font-medium">{{ t(section.label) }}</div>
              <div class="mt-1 text-sm text-muted-foreground">{{ t(section.description) }}</div>
            </Card>
          </RouterLink>
        </li>
      </ul>
    </PageLayout>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageLayout from '@/components/PageLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card } from '@/components/ui/card';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const sections = [
  {
    label: 'Equipment',
    description: 'Manage equipment and difficulty units',
    route: 'admin.equipment',
  },
  { label: 'Categories', description: 'Manage exercise categories', route: 'admin.categories' },
  {
    label: 'Exercises',
    description: 'Manage exercises, equipment, and categories',
    route: 'admin.exercises',
  },
  {
    label: 'Workout templates',
    description: 'Build workout templates with activities and sets',
    route: 'admin.workout-templates',
  },
  {
    label: 'Programs',
    description: 'Assemble programs and schedule templates by weekday',
    route: 'admin.programs',
  },
];
</script>
