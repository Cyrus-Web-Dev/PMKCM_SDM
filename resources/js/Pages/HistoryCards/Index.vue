<script setup>
import { computed, reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ExportButtons from '@/Components/ExportButtons.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { fmtDate } from '@/utils'

const props = defineProps({ cards: Object, filters: Object, employees: Array })
const page = usePage()
const teams = computed(() => page.props.teams ?? [])

const f = reactive({ ...props.filters })
function apply() {
  const q = {}
  for (const [k, v] of Object.entries(f)) if (v !== '' && v !== null) q[k] = v
  router.get(route('history-cards.index'), q, { preserveState: true, replace: true })
}
function reset() {
  Object.keys(f).forEach((k) => { f[k] = '' })
  apply()
}
const exportParams = computed(() => ({ ...props.filters }))
</script>

<template>
  <AppLayout>
    <Head title="History Card" />
    <template #title>History Card</template>
    <template #actions><ExportButtons route-name="history-cards.export" :params="exportParams" /></template>

    <div class="card card-body flex flex-wrap items-end gap-3">
      <div><label class="label">Cari</label><input v-model="f.q" class="input" placeholder="Uraian, nama, area…" @change="apply" @keyup.enter="apply" /></div>
      <div><label class="label">Tim</label><select v-model="f.team" class="input" @change="apply"><option value="">Semua tim</option><option v-for="t in teams" :key="t" :value="t">{{ t }}</option></select></div>
      <div v-if="employees.length"><label class="label">Terlibat: karyawan</label><select v-model="f.employee_id" class="input" @change="apply"><option value="">Semua</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option></select></div>
      <div><label class="label">Selesai dari</label><input v-model="f.from" type="date" class="input" @change="apply" /></div>
      <div><label class="label">Sampai</label><input v-model="f.to" type="date" class="input" @change="apply" /></div>
      <button class="btn-ghost btn-sm" @click="reset">Reset</button>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>No.</th><th>Uraian Pekerjaan</th><th>Tgl Mulai</th><th>Tgl Selesai</th><th>Kepala Kerja</th><th>Anggota Kerja</th><th></th></tr></thead>
          <tbody>
            <tr v-for="c in cards.data" :key="c.id">
              <td class="whitespace-nowrap font-mono text-xs">{{ c.number }}</td>
              <td class="max-w-xs">{{ c.description }}<p v-if="c.team || c.area" class="text-xs text-ink-400">{{ [c.team, c.area].filter(Boolean).join(' · ') }}</p></td>
              <td class="num whitespace-nowrap">{{ fmtDate(c.start_date) }}</td>
              <td class="num whitespace-nowrap">{{ fmtDate(c.end_date) }}<p v-if="c.duration_days" class="text-xs text-ink-400">{{ c.duration_days }} hari</p></td>
              <td>{{ c.leader }}</td>
              <td class="max-w-[220px]"><span v-for="(m, i) in c.members" :key="m.id">{{ m.name }}<template v-if="i < c.members.length - 1">, </template></span></td>
              <td class="text-right"><Link :href="route('history-cards.show', c.id)" class="btn-ghost btn-sm">Kartu</Link></td>
            </tr>
            <tr v-if="!cards.data.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Belum ada pekerjaan yang selesai.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="cards.links" /></div>
    </div>
  </AppLayout>
</template>
