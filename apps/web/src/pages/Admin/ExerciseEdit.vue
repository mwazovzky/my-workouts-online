<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Edit exercise')" />
    </template>

    <PageLayout>
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-10 w-32 rounded-xl" />
      </div>
      <ExerciseForm
        v-else-if="record"
        :exercise="record"
        :equipment-options="equipmentOptions"
        :category-options="categoryOptions"
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
import ExerciseForm from '@/components/Admin/ExerciseForm.vue';
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
const equipmentOptions = ref([]);
const categoryOptions = ref([]);
const isLoading = ref(true);

function goBack() {
  router.push({ name: 'admin.exercises' });
}

function onSaved() {
  toast.success(t('Exercise saved'));
  goBack();
}

onMounted(async () => {
  try {
    const [exercise, equipment, categories] = await Promise.all([
      get(`/admin/exercises/${props.id}`),
      get('/admin/equipment'),
      get('/admin/categories'),
    ]);
    record.value = exercise.data.data;
    equipmentOptions.value = equipment.data.data;
    categoryOptions.value = categories.data.data;
  } catch {
    toast.error(t('Failed to load exercises'));
    goBack();
  } finally {
    isLoading.value = false;
  }
});
</script>
