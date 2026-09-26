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

const { t } = useTranslation();
const { get } = useApi();

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
    const [equipment, categories] = await Promise.all([
      get('/admin/equipment'),
      get('/admin/categories'),
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
