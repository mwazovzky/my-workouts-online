<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader title="Workout Index" />
    </template>

    <PageLayout>
      <Empty v-if="workouts.data.length === 0">
        <EmptyTitle>No workouts yet</EmptyTitle>
        <EmptyDescription>Start a workout to see it listed here</EmptyDescription>
      </Empty>
      <ul v-else class="space-y-3">
        <li v-for="workout in workouts.data" :key="workout.id">
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
                    workout.name ?? workout.workout_template?.name ?? 'Workout'
                  }}</span>
                </div>
                <div class="text-sm text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                  <span
                    >{{ formatDate(workout.created_at) }} ·
                    {{ workout.activities_count ?? 0 }} activities</span
                  >
                  <Badge :variant="workout.status === 'completed' ? 'success' : 'warning'">
                    {{ formatStatus(workout.status) }}
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
                  Show
                </Button>

                <Button
                  v-if="workout.user_id === currentUserId() && workout.status === 'in_progress'"
                  as="a"
                  :href="route('workouts.edit', { id: workout.id })"
                  variant="default"
                  size="sm"
                >
                  Continue
                </Button>

                <Button variant="destructive" size="sm" @click="deleteWorkout(workout.id)">
                  Delete
                </Button>
              </div>
            </div>
          </Card>
        </li>
      </ul>

      <!-- Pagination Links -->
      <nav v-if="workouts.links.length > 3" class="flex items-center justify-center gap-1 mt-6">
        <template v-for="link in workouts.links" :key="link.label">
          <Button
            v-if="link.url"
            as="a"
            :href="link.url"
            :variant="link.active ? 'default' : 'outline'"
            size="sm"
          >
            {{ decodeHtmlEntities(link.label) }}
          </Button>
          <span v-else class="px-3 py-1 text-muted-foreground text-sm">
            {{ decodeHtmlEntities(link.label) }}
          </span>
        </template>
      </nav>
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
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Empty, EmptyDescription, EmptyTitle } from '@/Components/ui/empty';
import { Lock } from 'lucide-vue-next';
import { formatDate } from '@/utils/date';
import { formatStatus } from '@/utils/format';

defineProps({
  workouts: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const currentUserId = () => page.props.auth?.user?.id ?? null;

// Decode HTML entities in pagination labels
function decodeHtmlEntities(text) {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = text;
  return textarea.value;
}

// Confirm dialog state
const confirmDialog = ref({
  open: false,
  title: '',
  description: '',
  confirmLabel: 'Delete',
  onConfirm: null,
});

function openConfirm({ title, description, confirmLabel = 'Delete', onConfirm }) {
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
    title: 'Delete workout?',
    description: 'This action cannot be undone.',
    confirmLabel: 'Delete',
    onConfirm: () => {
      router.delete(route('workouts.destroy', { workout: id }), {
        preserveScroll: true,
        onError: () => {
          toast.error('Failed to delete workout');
        },
      });
    },
  });
}
</script>
