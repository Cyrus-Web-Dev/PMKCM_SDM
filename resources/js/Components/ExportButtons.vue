<script setup>
const props = defineProps({
  routeName: { type: String, required: true },
  params: { type: Object, default: () => ({}) },
})

// Unduhan memakai tautan biasa (bukan Inertia) karena responsnya berupa file.
function href(format) {
  const q = { format }
  for (const [k, v] of Object.entries(props.params)) {
    if (v !== '' && v !== null && v !== undefined) q[k] = v
  }
  return route(props.routeName, q)
}
</script>

<template>
  <div class="flex items-center gap-2">
    <a :href="href('xlsx')" class="btn-secondary btn-sm">Excel</a>
    <a :href="href('pdf')" class="btn-secondary btn-sm">PDF</a>
  </div>
</template>
