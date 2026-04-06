<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Program Index')" />
    </template>

    <PageLayout>
      <!-- Filter Toggle -->
      <div class="flex items-center justify-between mb-6 p-4 border rounded-lg bg-muted/50">
        <span class="text-sm font-medium">{{ t('Enrolled programs only') }}</span>
        <Switch v-model="filterEnrolled" />
      </div>

      <!-- Loading -->
      <div v-if="programs === null" class="space-y-3">
        <Skeleton v-for="i in 3" :key="i" class="h-20 w-full rounded-xl" />
      </div>

      <!-- Program List -->
      <template v-else>
        <Empty v-if="filteredPrograms.length === 0">
          <EmptyTitle>{{ t('No programs found') }}</EmptyTitle>
          <EmptyDescription>{{
            t('There are no programs matching your filter')
          }}</EmptyDescription>
        </Empty>
        <ul v-else class="space-y-3">
          <li v-for="program in filteredPrograms" :key="program.id">
            <Card class="p-4">
              <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4"
              >
                <div class="flex-1 min-w-0">
                  <div class="font-medium truncate">
                    {{ program.name }}
                  </div>
                  <div class="text-sm text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                    <span class="truncate">{{ program.description ?? t('No description') }}</span>
                    <Badge v-if="program.is_enrolled" variant="success">{{
                      t('Enrolled')
                    }}</Badge>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <Button
                    v-if="!program.is_enrolled"
                    variant="default"
                    size="sm"
                    @click="enrollInProgram(program)"
                  >
                    {{ t('Enroll') }}
                  </Button>
                  <Button
                    as="a"
                    :href="route('programs.show', { id: program.id })"
                    variant="outline"
                    size="sm"
                  >
                    {{ t('Show') }}
                  </Button>
                </div>
              </div>
            </Card>
          </li>
        </ul>
      </template>
    </PageLayout>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Switch } from '@/Components/ui/switch';
import { Skeleton } from '@/Components/ui/skeleton';
import { Empty, EmptyDescription, EmptyTitle } from '@/Components/ui/empty';
import { useEnrollment } from '@/composables/useEnrollment';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get } = useApi();
const { enroll } = useEnrollment();

const programs = ref(null);
const filterEnrolled = ref(false);

const filteredPrograms = computed(() => {
  if (!programs.value) {
    return [];
  }
  if (!filterEnrolled.value) {
    return programs.value;
  }
  return programs.value.filter(p => p.is_enrolled);
});

onMounted(async () => {
  const { data } = await get('/api/v1/programs');
  programs.value = data.data;
});

async function enrollInProgram(program) {
  await enroll(program.id);
  program.is_enrolled = true;
}
</script>
