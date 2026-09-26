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
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CategoryForm from '@/Components/Admin/CategoryForm.vue';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

function goBack() {
  router.visit(route('admin.categories'));
}

function onSaved() {
  toast.success(t('Category saved'));
  goBack();
}
</script>
