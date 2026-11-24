<script setup>
defineProps({ size: { type: Number, default: 40 } })
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 64 64" role="img" aria-label="Logo PMKCM V" class="shrink-0">
    <path d="M32 3 57 17.5v29L32 61 7 46.5v-29z" fill="none" stroke="#f26b1d" stroke-width="3" />
    <path d="M32 9.5 51.5 20.7v22.6L32 54.5 12.5 43.3V20.7z" fill="#f26b1d" fill-opacity=".12" />
    <path d="M19 22 32 43 45 22" fill="none" stroke="currentColor" stroke-width="5.5" stroke-linejoin="miter" />
    <path d="M20 50h24" stroke="#f26b1d" stroke-width="3" />
  </svg>
</template>
