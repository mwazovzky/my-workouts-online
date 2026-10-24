<script setup>
import Dropdown from '@/components/Dropdown.vue';
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { publicPagePath } from '@/lib/crawl';
import { useLocaleStore } from '@/stores/locale';
import { useMetaStore } from '@/stores/meta';

const props = defineProps({
  inverted: {
    type: Boolean,
    default: false,
  },
  buttonClass: {
    type: String,
    default: 'px-3 py-2 text-xs uppercase tracking-[0.18em]',
  },
});

const localeStore = useLocaleStore();
const meta = useMetaStore();
const route = useRoute();
const router = useRouter();

onMounted(() => meta.load().catch(() => {}));

const availableLocales = computed(() => meta.availableLocales ?? {});
const currentLocale = computed(() => localeStore.locale);
const currentLocaleLabel = computed(
  () => availableLocales.value[currentLocale.value] ?? currentLocale.value.toUpperCase()
);

// On a public page the language is part of the URL, so switch to that version.
function updateLocale(locale) {
  localeStore.setLocale(locale);

  const page = route?.meta.publicPage;
  if (page) {
    router.push(publicPagePath(page, locale));
  }
}
</script>

<template>
  <Dropdown align="right" width="48">
    <template #trigger>
      <button
        type="button"
        :class="[
          'inline-flex items-center gap-2 rounded-full border font-semibold shadow-sm transition backdrop-blur',
          props.buttonClass,
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
