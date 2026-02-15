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

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Are you sure?' },
  description: { type: String, default: 'This action cannot be undone.' },
  confirmLabel: { type: String, default: 'Continue' },
  cancelLabel: { type: String, default: 'Cancel' },
  variant: { type: String, default: 'destructive' },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
  <AlertDialog :open="open">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ title }}</AlertDialogTitle>
        <AlertDialogDescription>{{ description }}</AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel @click="$emit('cancel')">{{ cancelLabel }}</AlertDialogCancel>
        <AlertDialogAction :class="cn(buttonVariants({ variant }))" @click="$emit('confirm')">
          {{ confirmLabel }}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
