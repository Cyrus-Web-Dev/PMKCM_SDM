<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  routeName: { type: String, required: true },
  period: { type: Object, required: true },
  periodLabels: { type: Object, required: true },
  extra: { type: Object, default: () => ({}) },
})

// Nilai filter disimpan di sini; setiap perubahan langsung memuat ulang halaman dengan filter baru.
const f = reactive({ period: props.period.type, date: props.period.date, from: props.period.from, to: props.period.to, ...props.extra })

function apply() {
  const q = {}
  for (const [k, v] of Object.entries(f)) {
    if (v === '' || v === null || v === undefined) continue
    // Rentang kustom memakai from/to; periode lain memakai satu tanggal acuan.
    if (f.period === 'custom' ? k === 'date' : k === 'from' || k === 'to') continue
    q[k] = v
  }
  router.get(route(props.routeName), q, { preserveState: true, preserveScroll: true, replace: true })
}
</script>

<template>
  <div class="card card-body flex flex-wrap items-end gap-3">
    <div>
      <label class="label">Periode</label>
      <select v-model="f.period" @change="apply" class="input">
        <option v-for="(label, key) in periodLabels" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>
    <div v-if="f.period !== 'custom'">
      <label class="label">Tanggal acuan</label>
      <input v-model="f.date" type="date" @change="apply" class="input" />
    </div>
    <template v-else>
      <div><label class="label">Dari</label><input v-model="f.from" type="date" @change="apply" class="input" /></div>
      <div><label class="label">Sampai</label><input v-model="f.to" type="date" @change="apply" class="input" /></div>
    </template>
    <slot :f="f" :apply="apply" />
    <p class="ml-auto text-sm text-ink-500 dark:text-ink-400">
      {{ period.type_label }}: <b class="font-mono text-ink-900 dark:text-ink-100">{{ period.label }}</b>
    </p>
  </div>
</template>
