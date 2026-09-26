<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Workout templates')" />
    </template>

    <PageLayout>
      <div class="mb-4 flex justify-end">
        <Button size="sm" :disabled="items === null" @click="openCreate">
          {{ t('New workout template') }}
        </Button>
      </div>

      <div v-if="items === null" class="space-y-3">
        <Skeleton v-for="i in 3" :key="i" class="h-16 w-full rounded-xl" />
      </div>

      <template v-else>
        <Empty v-if="items.length === 0">
          <EmptyTitle>{{ t('No workout templates yet') }}</EmptyTitle>
          <EmptyDescription>{{
            t('Create your first workout template to get started')
          }}</EmptyDescription>
        </Empty>
        <ul v-else class="space-y-3">
          <li v-for="item in items" :key="item.id">
            <Card class="flex items-center justify-between gap-4 p-4">
              <div class="min-w-0">
                <div class="truncate font-medium">{{ item.name }}</div>
                <div class="mt-1 text-sm text-muted-foreground">
                  {{ item.activities_count }} {{ t('activities') }}
                </div>
              </div>
              <div class="flex flex-shrink-0 gap-2">
                <Button variant="outline" size="sm" @click="openEdit(item)">{{ t('Edit') }}</Button>
                <Button variant="destructive" size="sm" @click="confirmDelete(item)">
                  {{ t('Delete') }}
                </Button>
              </div>
            </Card>
          </li>
        </ul>
      </template>
    </PageLayout>

    <ConfirmDialog
      :open="deleteTarget !== null"
      :title="t('Delete workout template')"
      :description="t('This action cannot be undone.')"
      :confirm-label="t('Delete')"
      @confirm="performDelete"
      @cancel="deleteTarget = null"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageLayout from '@/components/PageLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const router = useRouter();

const { t } = useTranslation();
const { get, del } = useApi();

const items = ref(null);
const deleteTarget = ref(null);

async function load() {
  try {
    const { data } = await get('/admin/workout-templates');
    items.value = data.data;
  } catch {
    toast.error(t('Failed to load workout templates'));
    items.value = [];
  }
}

onMounted(load);

function openCreate() {
  router.push({ name: 'admin.workout-templates.create' });
}

function openEdit(item) {
  router.push({ name: 'admin.workout-templates.edit', params: { id: item.id } });
}

function confirmDelete(item) {
  deleteTarget.value = item;
}

async function performDelete() {
  const target = deleteTarget.value;
  deleteTarget.value = null;

  try {
    await del(`/admin/workout-templates/${target.id}`);
    toast.success(t('Workout template deleted'));
    load();
  } catch {
    toast.error(t('Failed to delete workout template'));
  }
}
</script>
