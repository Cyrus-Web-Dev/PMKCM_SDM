<script setup>
import { computed, ref } from 'vue'

// Pemilih banyak karyawan (anggota kerja): cari, centang, dan tampil sebagai chip.
const props = defineProps({
  employees: { type: Array, required: true },
  modelValue: { type: Array, default: () => [] },
  exclude: { type: [Number, String], default: null }, // mis. kepala kerja, tidak boleh jadi anggota
})
const emit = defineEmits(['update:modelValue'])

const search = ref('')
const options = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.employees.filter((e) => String(e.id) !== String(props.exclude) && (!q || `${e.full_name} ${e.nip ?? ''}`.toLowerCase().includes(q)))
})
const selected = computed(() => props.modelValue.map((id) => props.employees.find((e) => e.id === id)).filter(Boolean))

function toggle(id) {
  emit('update:modelValue', props.modelValue.includes(id) ? props.modelValue.filter((x) => x !== id) : [...props.modelValue, id])
}
</script>

<template>
  <div class="space-y-2">
    <div v-if="selected.length" class="flex flex-wrap gap-1.5">
      <span v-for="e in selected" :key="e.id" class="inline-flex items-center gap-1 border border-seal-500 px-2 py-0.5 text-xs">
        {{ e.full_name }}
        <button type="button" class="font-bold text-red-600" title="Hapus" @click="toggle(e.id)">&times;</button>
      </span>
    </div>
    <input v-model="search" class="input" placeholder="Cari nama / NIP anggota…" />
    <ul class="max-h-56 divide-y divide-ink-200 overflow-y-auto border border-ink-200 dark:divide-ink-700 dark:border-ink-700">
      <li v-for="e in options" :key="e.id">
        <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-ink-50 dark:hover:bg-ink-800">
          <input type="checkbox" :checked="modelValue.includes(e.id)" class="border-ink-300 text-seal-600 focus:ring-seal-500" @change="toggle(e.id)" />
          <span>{{ e.full_name }} <span class="font-mono text-xs text-ink-400">{{ e.nip }}</span><span v-if="e.team" class="ml-1 text-xs text-ink-400">· {{ e.team }}</span></span>
        </label>
      </li>
      <li v-if="!options.length" class="px-3 py-4 text-center text-sm text-ink-400">Tidak ada karyawan yang cocok.</li>
    </ul>
    <p class="text-xs text-ink-400">{{ modelValue.length }} anggota dipilih.</p>
  </div>
</template>
