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
import { useRouter } from 'vue-router';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageLayout from '@/components/PageLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProgramForm from '@/components/Admin/ProgramForm.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { useTranslation } from '@/composables/useTranslation';

const router = useRouter();

const { t } = useTranslation();
const { get } = useApi();

const templateOptions = ref([]);
const isLoading = ref(true);

function goBack() {
  router.push({ name: 'admin.programs' });
}

function onSaved() {
  toast.success(t('Program saved'));
  goBack();
}

onMounted(async () => {
  try {
    const { data } = await get('/admin/workout-templates');
    templateOptions.value = data.data;
  } catch {
    toast.error(t('Failed to load programs'));
    goBack();
  } finally {
    isLoading.value = false;
  }
});
</script>
