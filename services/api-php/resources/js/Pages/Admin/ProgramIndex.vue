<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Programs')" />
    </template>

    <PageLayout>
      <div class="mb-4 flex justify-end">
        <Button size="sm" :disabled="items === null" @click="openCreate">
          {{ t('New program') }}
        </Button>
      </div>

      <div v-if="items === null" class="space-y-3">
        <Skeleton v-for="i in 3" :key="i" class="h-16 w-full rounded-xl" />
      </div>

      <template v-else>
        <Empty v-if="items.length === 0">
          <EmptyTitle>{{ t('No programs yet') }}</EmptyTitle>
          <EmptyDescription>{{ t('Create your first program to get started') }}</EmptyDescription>
        </Empty>
        <ul v-else class="space-y-3">
          <li v-for="item in items" :key="item.id">
            <Card class="flex items-center justify-between gap-4 p-4">
              <div class="min-w-0">
                <div class="truncate font-medium">{{ item.name }}</div>
                <div class="mt-1 text-sm text-muted-foreground">
                  {{ item.templates_count }} {{ t('workouts scheduled') }}
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
      :title="t('Delete program')"
      :description="t('This action cannot be undone.')"
      :confirm-label="t('Delete')"
      @confirm="performDelete"
      @cancel="deleteTarget = null"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Empty, EmptyDescription, EmptyTitle } from '@/Components/ui/empty';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get, del } = useApi();

const items = ref(null);
const deleteTarget = ref(null);

async function load() {
  try {
    const { data } = await get('/api/v1/admin/programs');
    items.value = data.data;
  } catch {
    toast.error(t('Failed to load programs'));
    items.value = [];
  }
}

onMounted(load);

function openCreate() {
  router.visit(route('admin.programs.create'));
}

function openEdit(item) {
  router.visit(route('admin.programs.edit', item.id));
}

function confirmDelete(item) {
  deleteTarget.value = item;
}

async function performDelete() {
  const target = deleteTarget.value;
  deleteTarget.value = null;

  try {
    await del(`/api/v1/admin/programs/${target.id}`);
    toast.success(t('Program deleted'));
    load();
  } catch {
    toast.error(t('Failed to delete program'));
  }
}
</script>
