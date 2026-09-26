<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('New category')" />
    </template>

    <PageLayout>
      <CategoryForm @saved="onSaved" @cancel="goBack" />
    </PageLayout>
  </AuthenticatedLayout>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageLayout from '@/components/PageLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import CategoryForm from '@/components/Admin/CategoryForm.vue';
import { useTranslation } from '@/composables/useTranslation';

const router = useRouter();

const { t } = useTranslation();

function goBack() {
  router.push({ name: 'admin.categories' });
}

function onSaved() {
  toast.success(t('Category saved'));
  goBack();
}
</script>
