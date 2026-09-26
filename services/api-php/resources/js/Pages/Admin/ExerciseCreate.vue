<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('New exercise')" />
    </template>

    <PageLayout>
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-10 w-32 rounded-xl" />
      </div>
      <ExerciseForm
        v-else
        :equipment-options="equipmentOptions"
        :category-options="categoryOptions"
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
import ExerciseForm from '@/Components/Admin/ExerciseForm.vue';
import { Skeleton } from '@/Components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get } = useApi();

const equipmentOptions = ref([]);
const categoryOptions = ref([]);
const isLoading = ref(true);

function goBack() {
  router.visit(route('admin.exercises'));
}

function onSaved() {
  toast.success(t('Exercise saved'));
  goBack();
}

onMounted(async () => {
  try {
    const [equipment, categories] = await Promise.all([
      get('/api/v1/admin/equipment'),
      get('/api/v1/admin/categories'),
    ]);
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
