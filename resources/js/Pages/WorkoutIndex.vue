<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Workout Index')" />
    </template>

    <PageLayout>
      <!-- Loading -->
      <div v-if="workouts === null" class="space-y-3">
        <Skeleton v-for="i in 5" :key="i" class="h-20 w-full rounded-xl" />
      </div>

      <template v-else>
        <Empty v-if="workouts.length === 0">
          <EmptyTitle>{{ t('No workouts yet') }}</EmptyTitle>
          <EmptyDescription>{{ t('Start a workout to see it listed here') }}</EmptyDescription>
        </Empty>
        <ul v-else class="space-y-3">
          <li v-for="workout in workouts" :key="workout.id">
            <Card class="p-4">
              <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4"
              >
                <div class="flex-1 min-w-0">
                  <div class="font-medium flex items-center gap-2">
                    <Lock
                      v-if="workout.status === 'completed'"
                      class="w-4 h-4 text-muted-foreground flex-shrink-0"
                    />
                    <span class="truncate">{{
                      workout.name ?? workout.workout_template?.name ?? t('Workout')
                    }}</span>
                  </div>
                  <div class="text-sm text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                    <span
                      >{{ formatDate(workout.created_at) }} ·
                      {{ t(':count activities', { count: workout.activities_count ?? 0 }) }}</span
                    >
                    <Badge :variant="workout.status === 'completed' ? 'success' : 'warning'">
                      {{ workout.status_label }}
                    </Badge>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <Button
                    as="a"
                    :href="route('workouts.show', { id: workout.id })"
                    variant="outline"
                    size="sm"
                  >
                    {{ t('Show') }}
                  </Button>

                  <Button
                    v-if="workout.user_id === currentUserId && workout.status === 'in_progress'"
                    as="a"
                    :href="route('workouts.edit', { id: workout.id })"
                    variant="default"
                    size="sm"
                  >
                    {{ t('Continue') }}
                  </Button>

                  <Button variant="destructive" size="sm" @click="deleteWorkout(workout.id)">
                    {{ t('Delete') }}
                  </Button>
                </div>
              </div>
            </Card>
          </li>
        </ul>

        <!-- Pagination -->
        <nav
          v-if="pagination && pagination.last_page > 1"
          class="flex items-center justify-center gap-1 mt-6"
        >
          <template v-for="item in paginationItems" :key="item.key">
            <span v-if="item.ellipsis" class="px-2 text-muted-foreground text-sm">…</span>
            <Button
              v-else
              :variant="item.page === pagination.current_page ? 'default' : 'outline'"
              size="sm"
              @click="goToPage(item.page)"
            >
              {{ item.page }}
            </Button>
          </template>
        </nav>
      </template>
    </PageLayout>

    <ConfirmDialog
      :open="confirmDialog.open"
      :title="confirmDialog.title"
      :description="confirmDialog.description"
      :confirm-label="confirmDialog.confirmLabel"
      @confirm="onConfirmDialogConfirm"
      @cancel="onConfirmDialogCancel"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Empty, EmptyDescription, EmptyTitle } from '@/Components/ui/empty';
import { Lock } from 'lucide-vue-next';
import { formatDate } from '@/utils/date';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get, del } = useApi();

const workouts = ref(null);
const pagination = ref(null);
const currentPage = ref(1);

const paginationItems = computed(() => {
  if (!pagination.value) return [];

  const last = pagination.value.last_page;
  const current = pagination.value.current_page;
  const delta = 2;

  const pages = new Set([1, last]);
  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    pages.add(i);
  }

  const sorted = [...pages].sort((a, b) => a - b);
  const items = [];

  for (let i = 0; i < sorted.length; i++) {
    if (i > 0 && sorted[i] - sorted[i - 1] > 1) {
      items.push({ key: `ellipsis-${sorted[i]}`, ellipsis: true });
    }
    items.push({ key: sorted[i], page: sorted[i], ellipsis: false });
  }

  return items;
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

async function fetchWorkouts(pageNum = 1) {
  workouts.value = null;
  try {
    const { data } = await get('/api/v1/workouts', { page: pageNum });
    workouts.value = data.data;
    pagination.value = data.meta;
    currentPage.value = data.meta.current_page;
  } catch {
    toast.error(t('Failed to load workouts'));
    workouts.value = [];
    pagination.value = null;
    currentPage.value = 1;
  }
}

async function goToPage(pageNum) {
  await fetchWorkouts(pageNum);
}

onMounted(() => fetchWorkouts(1));

// Confirm dialog state
const confirmDialog = ref({
  open: false,
  title: '',
  description: '',
  confirmLabel: '',
  onConfirm: null,
});

function openConfirm({ title, description, confirmLabel = t('Delete'), onConfirm }) {
  confirmDialog.value = { open: true, title, description, confirmLabel, onConfirm };
}

function onConfirmDialogConfirm() {
  const callback = confirmDialog.value.onConfirm;
  confirmDialog.value.open = false;
  callback?.();
}

function onConfirmDialogCancel() {
  confirmDialog.value.open = false;
}

async function deleteWorkout(id) {
  openConfirm({
    title: t('Delete workout?'),
    description: t('This action cannot be undone.'),
    confirmLabel: t('Delete'),
    onConfirm: async () => {
      try {
        await del(`/api/v1/workouts/${id}`);
        await fetchWorkouts(currentPage.value);
      } catch {
        toast.error(t('Failed to delete workout'));
      }
    },
  });
}
</script>
