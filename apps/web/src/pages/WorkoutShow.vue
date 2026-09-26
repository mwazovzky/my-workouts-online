<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Workout Show')" />
    </template>

    <template #header-actions>
      <div class="flex gap-2">
        <Button v-if="canRepeat" size="sm" :disabled="repeatNav" @click="repeatWorkout">
          {{ repeatNav ? t('Starting…') : t('Repeat') }}
        </Button>
        <Button v-if="canEdit" size="sm" variant="outline" :disabled="editingNav" @click="goEdit">
          {{ editingNav ? t('Opening…') : t('Continue') }}
        </Button>
      </div>
    </template>

    <PageLayout>
      <!-- Loading -->
      <div v-if="workout === null" class="space-y-4">
        <Skeleton class="h-24 w-full rounded-xl" />
        <Card v-for="i in 2" :key="i" class="p-4">
          <div class="mb-4 pb-4 border-b">
            <Skeleton class="h-6 w-64 mb-3" />
            <div class="space-y-2">
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-full" />
            </div>
          </div>
          <Skeleton class="h-10 w-full" />
        </Card>
      </div>

      <template v-else>
        <WorkoutCard :workout="workout" />
        <ActivitiesList
          v-if="activities.length"
          :activities="activities"
          :editable="false"
          @add-set="() => {}"
          @remove-set="() => {}"
          @update-activity="() => {}"
        />
        <div v-else class="space-y-4">
          <Card v-for="i in 2" :key="i" class="p-4">
            <div class="mb-4 pb-4 border-b">
              <Skeleton class="h-6 w-64 mb-3" />
              <div class="space-y-2">
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-full" />
              </div>
            </div>
            <Skeleton class="h-10 w-full" />
          </Card>
        </div>
      </template>
    </PageLayout>

    <WorkoutFooter :show="canEdit || canRepeat" class="hidden sm:block">
      <div class="flex items-center gap-2">
        <Button
          v-if="canRepeat"
          :disabled="repeatNav"
          variant="default"
          size="lg"
          class="px-8"
          @click="repeatWorkout"
        >
          <span v-if="!repeatNav">{{ t('Repeat workout') }}</span>
          <span v-else>{{ t('Starting…') }}</span>
        </Button>

        <Button
          v-if="canEdit"
          :disabled="editingNav"
          variant="outline"
          size="lg"
          class="px-8"
          @click="goEdit"
        >
          <span v-if="!editingNav">{{ t('Continue') }}</span>
          <span v-else>{{ t('Opening…') }}</span>
        </Button>
      </div>
    </WorkoutFooter>
  </AuthenticatedLayout>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { computed, ref, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/components/ActivitiesList.vue';
import WorkoutCard from '@/components/WorkoutCard.vue';
import WorkoutFooter from '@/components/WorkoutFooter.vue';
import PageLayout from '@/components/PageLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();

const { t } = useTranslation();
const { get, post } = useApi();

const props = defineProps({
  id: { type: Number, required: true },
});

const workout = ref(null);
const activities = ref([]);

onMounted(async () => {
  try {
    const { data } = await get(`/workouts/${props.id}`);
    workout.value = data.data;
    activities.value = data.data.activities ?? [];
  } catch {
    toast.error(t('Failed to load workout'));
    router.push({ name: 'workouts.index' });
  }
});

const auth = useAuthStore();
const currentUserId = computed(() => auth.user?.id ?? null);

const editingNav = ref(false);
const repeatNav = ref(false);

const canEdit = computed(() => {
  return (
    !!currentUserId.value &&
    workout.value?.status === 'in_progress' &&
    workout.value?.user_id === currentUserId.value
  );
});

const canRepeat = computed(() => {
  return (
    !!currentUserId.value &&
    workout.value?.status === 'completed' &&
    workout.value?.user_id === currentUserId.value
  );
});

function goEdit() {
  if (!canEdit.value) return;
  editingNav.value = true;
  router.push({ name: 'workouts.edit', params: { id: workout.value.id } }).finally(() => {
    editingNav.value = false;
  });
}

async function repeatWorkout() {
  if (!canRepeat.value) return;
  repeatNav.value = true;
  try {
    const { data } = await post(`/workouts/${workout.value.id}/repeat`);
    router.push({ name: 'workouts.edit', params: { id: data.data.id } }).finally(() => {
      repeatNav.value = false;
    });
  } catch {
    toast.error(t('Failed to repeat workout'));
    repeatNav.value = false;
  }
}
</script>
