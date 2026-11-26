<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { gradeBadge } from '@/utils'

defineProps({ assessments: Array, criteria: Array })

</script>

<template>
  <AppLayout>
    <Head title="Nilai Saya" />
    <template #title>Nilai Saya</template>

    <div v-if="!assessments.length" class="card card-body text-sm text-ink-500">Belum ada penilaian untukmu.</div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="a in assessments" :key="a.id" class="card card-body">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-serif text-lg font-semibold">{{ a.period_label }}</p>
            <span :class="gradeBadge(a.total)">{{ a.grade }}</span>
          </div>
          <p class="font-mono text-4xl font-bold">{{ a.total }}</p>
        </div>
        <div v-for="c in criteria" :key="c.key" class="mt-3">
          <div class="flex justify-between text-sm"><span>{{ c.label }}</span><span class="font-mono">{{ a.scores[c.key] }}</span></div>
          <div class="mt-1 h-1.5 overflow-hidden bg-ink-100 dark:bg-ink-800"><div class="h-full bg-seal-500" :style="{ width: a.scores[c.key] + '%' }"></div></div>
        </div>
        <p v-if="a.note" class="mt-4 text-sm text-ink-600 dark:text-ink-300">Catatan: {{ a.note }}</p>
      </div>
    </div>
  </AppLayout>
</template>
