<script setup>
defineProps({
  variant: {
    type: String,
    default: 'dumbbell',
    validator: value => ['monogram', 'barbell', 'progress', 'dumbbell'].includes(value),
  },
});
</script>

<template>
  <svg
    v-if="variant === 'dumbbell'"
    viewBox="0 0 128 128"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <rect x="4" y="4" width="120" height="120" rx="38" fill="currentColor" opacity="0.12" />
    <g transform="rotate(-12 64 64)">
      <rect x="15" y="43" width="10" height="42" rx="5" fill="currentColor" />
      <rect x="28" y="36" width="10" height="56" rx="5" fill="currentColor" />
      <rect x="41" y="28" width="10" height="72" rx="5" fill="currentColor" />

      <rect x="44" y="58" width="40" height="12" rx="6" fill="currentColor" />

      <rect x="77" y="28" width="10" height="72" rx="5" fill="currentColor" />
      <rect x="90" y="36" width="10" height="56" rx="5" fill="currentColor" />
      <rect x="103" y="43" width="10" height="42" rx="5" fill="currentColor" />
    </g>
  </svg>

  <svg
    v-else-if="variant === 'monogram'"
    viewBox="0 0 128 128"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <rect x="14" y="14" width="100" height="100" rx="28" fill="currentColor" opacity="0.14" />
    <path d="M31 36h13l11 40 9-25h9l9 25 11-40h13l-18 56H79l-11-27-11 27H49z" fill="currentColor" />
    <path
      d="M42 24h44"
      fill="none"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-width="6"
      opacity="0.4"
    />
  </svg>

  <svg
    v-else-if="variant === 'progress'"
    viewBox="0 0 128 128"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <circle
      cx="64"
      cy="64"
      r="48"
      fill="none"
      stroke="currentColor"
      stroke-width="12"
      opacity="0.16"
    />
    <path
      d="M64 16a48 48 0 0 1 45.78 34.32"
      fill="none"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-width="12"
    />
    <path d="M38 84V43h10l16 22 16-22h10v41H79V60L64 80 49 60v24z" fill="currentColor" />
    <circle cx="103" cy="51" r="6" fill="currentColor" />
  </svg>

  <svg v-else viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <rect x="16" y="16" width="96" height="96" rx="28" fill="currentColor" opacity="0.12" />
    <path
      d="M26 64h14m48 0h14"
      fill="none"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-width="8"
    />
    <rect x="32" y="52" width="8" height="24" rx="3" fill="currentColor" />
    <rect x="42" y="56" width="7" height="16" rx="3" fill="currentColor" opacity="0.82" />
    <rect x="79" y="56" width="7" height="16" rx="3" fill="currentColor" opacity="0.82" />
    <rect x="88" y="52" width="8" height="24" rx="3" fill="currentColor" />
    <path d="M40 84l8-33h6l10 19 10-19h6l8 33h-9l-4-17-8 15h-6l-8-15-4 17z" fill="currentColor" />
  </svg>
</template>
