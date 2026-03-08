<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  inverted: {
    type: Boolean,
    default: false,
  },
});

const page = usePage();

const availableLocales = computed(() => page.props.availableLocales ?? {});
const currentLocale = computed(() => page.props.locale ?? 'en');
const currentLocaleLabel = computed(
  () => availableLocales.value[currentLocale.value] ?? currentLocale.value.toUpperCase()
);

function updateLocale(locale) {
  if (locale === currentLocale.value) {
    return;
  }

  router.patch(
    route('locale.update'),
    { locale },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    }
  );
}
</script>

<template>
  <Dropdown align="right" width="48">
    <template #trigger>
      <button
        type="button"
        :class="[
          'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] shadow-sm transition backdrop-blur',
          props.inverted
            ? 'border-background/15 bg-background/10 text-background hover:bg-background/15'
            : 'border-border/80 bg-background/80 text-foreground hover:bg-accent hover:text-accent-foreground',
        ]"
      >
        <span>{{ currentLocaleLabel }}</span>
        <svg class="h-3.5 w-3.5 opacity-70" viewBox="0 0 20 20" fill="currentColor">
          <path
            fill-rule="evenodd"
            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
            clip-rule="evenodd"
          />
        </svg>
      </button>
    </template>

    <template #content>
      <button
        v-for="(label, code) in availableLocales"
        :key="code"
        type="button"
        class="flex w-full items-center justify-between px-4 py-2 text-left text-sm leading-5 text-popover-foreground transition duration-150 ease-in-out hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
        :class="[code === currentLocale ? 'bg-accent text-accent-foreground' : '']"
        @click="updateLocale(code)"
      >
        <span>{{ label }}</span>
        <span
          v-if="code === currentLocale"
          class="text-xs font-semibold uppercase tracking-[0.18em]"
        >
          ✓
        </span>
      </button>
    </template>
  </Dropdown>
</template>
