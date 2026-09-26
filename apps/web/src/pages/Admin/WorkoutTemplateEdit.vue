<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Edit workout template')" />
    </template>

    <PageLayout>
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-10 w-32 rounded-xl" />
      </div>
      <WorkoutTemplateForm
        v-else-if="record"
        :template="record"
        :exercises="exercises"
        @saved="onSaved"
        @cancel="goBack"
      />
    </PageLayout>
  </AuthenticatedLayout>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageLayout from '@/components/PageLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import WorkoutTemplateForm from '@/components/Admin/WorkoutTemplateForm.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const router = useRouter();

const props = defineProps({
  id: { type: Number, required: true },
});

const { t } = useTranslation();
const { get } = useApi();

const record = ref(null);
const exercises = ref([]);
const isLoading = ref(true);

function goBack() {
  router.push({ name: 'admin.workout-templates' });
}

function onSaved() {
  toast.success(t('Workout template saved'));
  goBack();
}

onMounted(async () => {
  try {
    const [template, exerciseList] = await Promise.all([
      get(`/admin/workout-templates/${props.id}`),
      get('/admin/exercises'),
    ]);
    record.value = template.data.data;
    exercises.value = exerciseList.data.data;
  } catch {
    toast.error(t('Failed to load workout templates'));
    goBack();
  } finally {
    isLoading.value = false;
  }
});
</script>
