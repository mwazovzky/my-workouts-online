<template>
  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="t('New equipment')" />
    </template>

    <PageLayout>
      <EquipmentForm @saved="onSaved" @cancel="goBack" />
    </PageLayout>
  </AuthenticatedLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLayout from '@/Components/PageLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EquipmentForm from '@/Components/Admin/EquipmentForm.vue';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

function goBack() {
  router.visit(route('admin.equipment'));
}

function onSaved() {
  toast.success(t('Equipment saved'));
  goBack();
}
</script>
