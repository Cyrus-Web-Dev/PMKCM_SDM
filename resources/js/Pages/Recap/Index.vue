<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import PeriodFilter from '@/Components/PeriodFilter.vue'
import ExportButtons from '@/Components/ExportButtons.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { fmtDate, STATUS_BADGE, gradeBadge, mailto } from '@/utils'

const props = defineProps({ rows: Array, detail: Object, period: Object, periodLabels: Object, filters: Object, canEmail: Boolean })
const page = usePage()
const exportParams = computed(() => ({ period: props.period.type, date: props.period.date, team: props.filters.team }))

const totals = computed(() => ({
  reports: props.rows.reduce((a, r) => a + r.reports, 0),
  done: props.rows.reduce((a, r) => a + r.done, 0),
  hours: props.rows.reduce((a, r) => a + r.hours, 0).toFixed(1),
}))

function emailHref(r) {
  return mailto(r.email, `Hasil Rekap Kinerja ${props.period.type_label} ${props.period.label}`, [
    `Halo ${r.name},`, '',
    `Berikut hasil rekap kinerja Anda (${props.period.type_label}: ${props.period.label}):`,
    `- Jumlah laporan: ${r.reports}`,
    `- Pekerjaan selesai: ${r.done}`,
    `- Total jam kerja: ${r.hours} jam`,
    `- Nilai rata-rata: ${r.score ?? '-'} (${r.grade})`, '',
    'Terima kasih.',
  ])
}
</script>

<template>
  <AppLayout>
    <Head title="Rekap" />
    <template #title>Rekap Kinerja</template>
    <template #actions><ExportButtons v-if="($page.props.auth.user.permissions ?? []).includes('reports.export')" route-name="recap.export" :params="exportParams" /></template>

    <PeriodFilter route-name="recap.index" :period="period" :period-labels="periodLabels" :extra="{ team: filters.team }">
      <template #default="{ f, apply }">
        <div>
          <label class="label">Tim</label>
          <input v-model="f.team" @change="apply" class="input" maxlength="30" placeholder="Tim" />
        </div>
      </template>
    </PeriodFilter>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Karyawan</th><th>Tim</th><th class="text-right">Laporan</th><th class="text-right">Selesai</th><th class="text-right">Total Jam</th><th>Nilai Rata-rata</th><th v-if="canEmail"></th></tr></thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id">
              <td>{{ r.name }}<p class="text-xs text-ink-400"><span class="font-mono">{{ r.nip }}</span></p></td>
              <td>{{ r.team || '—' }}</td>
              <td class="num text-right">{{ r.reports }}</td>
              <td class="num text-right">{{ r.done }}</td>
              <td class="num text-right">{{ r.hours }}</td>
              <td><span v-if="r.score != null" :class="gradeBadge(r.score)">{{ r.score }} · {{ r.grade }}</span><span v-else class="text-ink-400">—</span></td>
              <td v-if="canEmail" class="text-right"><a v-if="r.email" :href="emailHref(r)" class="btn-ghost btn-sm">Email</a></td>
            </tr>
            <tr v-if="!rows.length"><td :colspan="canEmail ? 7 : 6" class="py-10 text-center text-sm text-ink-400">Tidak ada karyawan pada filter ini.</td></tr>
          </tbody>
          <tfoot v-if="rows.length">
            <tr class="font-semibold">
              <td colspan="2" class="px-4 py-3 text-right">Total</td>
              <td class="num px-4 py-3 text-right">{{ totals.reports }}</td>
              <td class="num px-4 py-3 text-right">{{ totals.done }}</td>
              <td class="num px-4 py-3 text-right">{{ totals.hours }}</td>
              <td :colspan="canEmail ? 2 : 1"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-serif font-semibold">Rincian Laporan Periode Ini</h3></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Tim</th><th>Area</th><th>Jam</th><th>Uraian</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="r in detail.data" :key="r.id">
              <td class="num whitespace-nowrap">{{ fmtDate(r.date) }}</td>
              <td>{{ r.employee || '—' }}</td>
              <td>{{ r.team || '—' }}</td>
              <td>{{ r.area }}</td>
              <td class="num">{{ r.hours }}</td>
              <td class="max-w-xs">{{ r.task }}</td>
              <td><span :class="STATUS_BADGE[r.status]">{{ r.status_label }}</span></td>
            </tr>
            <tr v-if="!detail.data.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Belum ada laporan.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="detail.links" /></div>
    </div>
  </AppLayout>
</template>
