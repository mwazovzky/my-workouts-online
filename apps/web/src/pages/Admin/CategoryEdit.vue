<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('Edit category')" />
    </template>

    <PageLayout>
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-16 w-full rounded-xl" />
        <Skeleton class="h-10 w-32 rounded-xl" />
      </div>
      <CategoryForm v-else-if="record" :category="record" @saved="onSaved" @cancel="goBack" />
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
import CategoryForm from '@/components/Admin/CategoryForm.vue';
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
const isLoading = ref(true);

function goBack() {
  router.push({ name: 'admin.categories' });
}

function onSaved() {
  toast.success(t('Category saved'));
  goBack();
}

onMounted(async () => {
  try {
    const { data } = await get(`/admin/categories/${props.id}`);
    record.value = data.data;
  } catch {
    toast.error(t('Failed to load categories'));
    goBack();
  } finally {
    isLoading.value = false;
  }
});
</script>
