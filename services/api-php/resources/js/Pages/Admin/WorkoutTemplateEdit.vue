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
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WorkoutTemplateForm from '@/Components/Admin/WorkoutTemplateForm.vue';
import { Skeleton } from '@/Components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
  id: { type: Number, required: true },
});

const { t } = useTranslation();
const { get } = useApi();

const record = ref(null);
const exercises = ref([]);
const isLoading = ref(true);

function goBack() {
  router.visit(route('admin.workout-templates'));
}

function onSaved() {
  toast.success(t('Workout template saved'));
  goBack();
}

onMounted(async () => {
  try {
    const [template, exerciseList] = await Promise.all([
      get(`/api/v1/admin/workout-templates/${props.id}`),
      get('/api/v1/admin/exercises'),
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
