<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('New program')" />
    </template>

    <PageLayout>
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-10 w-32 rounded-xl" />
      </div>
      <ProgramForm v-else :templates="templateOptions" @saved="onSaved" @cancel="goBack" />
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
import ProgramForm from '@/Components/Admin/ProgramForm.vue';
import { Skeleton } from '@/Components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
const { get } = useApi();

const templateOptions = ref([]);
const isLoading = ref(true);

function goBack() {
  router.visit(route('admin.programs'));
}

function onSaved() {
  toast.success(t('Program saved'));
  goBack();
}

onMounted(async () => {
  try {
    const { data } = await get('/api/v1/admin/workout-templates');
    templateOptions.value = data.data;
  } catch {
    toast.error(t('Failed to load programs'));
    goBack();
  } finally {
    isLoading.value = false;
  }
});
</script>
