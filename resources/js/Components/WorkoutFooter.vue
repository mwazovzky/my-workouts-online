<script setup>
defineProps({
    show: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div v-if="show" class="fixed bottom-0 left-0 right-0 bg-background/95 backdrop-blur-sm border-t border-border">
        <div class="max-w-4xl mx-auto px-4 py-3 flex justify-end">
            <slot />
        </div>
    </div>
</template>
