<script setup>
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/composables/useTranslation';
import { computed } from 'vue';

const { t } = useTranslation();

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: null },
  description: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  cancelLabel: { type: String, default: null },
  variant: { type: String, default: 'destructive' },
});

const resolvedTitle = computed(() => props.title ?? t('Are you sure?'));
const resolvedDescription = computed(() => props.description ?? t('This action cannot be undone.'));
const resolvedConfirmLabel = computed(() => props.confirmLabel ?? t('Continue'));
const resolvedCancelLabel = computed(() => props.cancelLabel ?? t('Cancel'));

defineEmits(['confirm', 'cancel']);
</script>

<template>
  <AlertDialog :open="open">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ resolvedTitle }}</AlertDialogTitle>
        <AlertDialogDescription>{{ resolvedDescription }}</AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel @click="$emit('cancel')">{{ resolvedCancelLabel }}</AlertDialogCancel>
        <AlertDialogAction :class="cn(buttonVariants({ variant }))" @click="$emit('confirm')">
          {{ resolvedConfirmLabel }}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
