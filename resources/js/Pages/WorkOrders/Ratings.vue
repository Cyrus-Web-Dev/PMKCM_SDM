<script setup>
import { computed, reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ExportButtons from '@/Components/ExportButtons.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { fmtDate, gradeBadge } from '@/utils'

const props = defineProps({ ratings: Object, filters: Object, criteria: Array })

const f = reactive({ ...props.filters })
function apply() {
  const q = {}
  for (const [k, v] of Object.entries(f)) if (v !== '' && v !== null) q[k] = v
  router.get(route('work-orders.ratings'), q, { preserveState: true, replace: true })
}
const exportParams = computed(() => ({ ...props.filters }))
</script>

<template>
  <AppLayout>
    <Head title="Penilaian Anggota" />
    <template #title>Penilaian Anggota</template>
    <template #actions><ExportButtons route-name="work-orders.ratings.export" :params="exportParams" /></template>

    <div class="card card-body flex flex-wrap items-end gap-3">
      <div><label class="label">Cari</label><input v-model="f.q" class="input" placeholder="Nama anggota / no. perintah" @change="apply" @keyup.enter="apply" /></div>
      <div><label class="label">Selesai dari</label><input v-model="f.from" type="date" class="input" @change="apply" /></div>
      <div><label class="label">Sampai</label><input v-model="f.to" type="date" class="input" @change="apply" /></div>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr><th>Perintah Kerja</th><th>Anggota</th><th v-for="c in criteria" :key="c.key" class="text-right" :title="c.label">{{ c.label.split(' ')[0] }}</th><th>Nilai Akhir</th><th>Catatan</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in ratings.data" :key="r.id">
              <td><Link :href="route('work-orders.show', r.order_id)" class="font-mono font-semibold text-seal-600 hover:underline dark:text-seal-400">{{ r.number }}</Link><p class="text-xs text-ink-400">Selesai {{ fmtDate(r.end_date) }} · KK: {{ r.leader }}</p></td>
              <td>{{ r.employee }}</td>
              <td v-for="c in criteria" :key="c.key" class="num text-right">{{ r.scores[c.key] }}</td>
              <td><span :class="gradeBadge(r.total)">{{ r.total }} · {{ r.grade }}</span></td>
              <td class="max-w-xs text-xs text-ink-500">{{ r.note }}</td>
            </tr>
            <tr v-if="!ratings.data.length"><td :colspan="criteria.length + 4" class="py-10 text-center text-sm text-ink-400">Belum ada penilaian anggota.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="ratings.links" /></div>
    </div>
  </AppLayout>
</template>
