<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import PeriodFilter from '@/Components/PeriodFilter.vue'
import ExportButtons from '@/Components/ExportButtons.vue'
import ReportDetailModal from '@/Components/ReportDetailModal.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { fmtDate, STATUS_BADGE, REVIEW_BADGE } from '@/utils'

const props = defineProps({ reports: Object, period: Object, periodLabels: Object, filters: Object, summary: Object, employees: Array })
const page = usePage()
const teams = computed(() => page.props.teams ?? [])
const exportParams = computed(() => ({ period: props.period.type, date: props.period.date, team: props.filters.team, employee_id: props.filters.employee_id, q: props.filters.q, status: props.filters.status, review: props.filters.review }))
const detailId = ref(null)
const reload = () => router.reload({ only: ['reports', 'summary'] })
</script>

<template>
  <AppLayout>
    <Head title="Semua Laporan" />
    <template #title>Semua Laporan Harian</template>
    <template #actions>
      <ExportButtons route-name="daily-reports.export" :params="exportParams" />
      <a :href="route('daily-reports.export', { ...exportParams, format: 'pdf', photos: 1 })" class="btn-secondary btn-sm" title="Maks. 40 laporan per berkas">PDF + Foto</a>
    </template>

    <PeriodFilter route-name="daily-reports.all" :period="period" :period-labels="periodLabels" :extra="{ team: filters.team, employee_id: filters.employee_id, q: filters.q, status: filters.status, review: filters.review }">
      <template #default="{ f, apply }">
        <div>
          <label class="label">Tim</label>
          <select v-model="f.team" @change="apply" class="input">
            <option value="">Semua tim</option>
            <option v-for="t in teams" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label class="label">Cari</label>
          <input v-model="f.q" @keyup.enter="apply" @change="apply" class="input" placeholder="Nama, area, uraian…" />
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model="f.status" @change="apply" class="input">
            <option value="">Semua</option><option value="selesai">Selesai</option><option value="proses">Proses</option><option value="kendala">Kendala</option>
          </select>
        </div>
        <div>
          <label class="label">Review</label>
          <select v-model="f.review" @change="apply" class="input">
            <option value="">Semua</option><option value="menunggu">Menunggu Review</option><option value="disetujui">Disetujui</option><option value="perlu_revisi">Perlu Revisi</option>
          </select>
        </div>
        <div>
          <label class="label">Karyawan</label>
          <select v-model="f.employee_id" @change="apply" class="input">
            <option value="">Semua karyawan</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
          </select>
        </div>
      </template>
    </PeriodFilter>

    <div class="grid grid-cols-3 gap-4">
      <div class="card card-body"><p class="label">Jumlah Laporan</p><p class="font-mono text-3xl font-bold">{{ summary.count }}</p></div>
      <div class="card card-body"><p class="label">Total Jam Kerja</p><p class="font-mono text-3xl font-bold">{{ summary.hours }}</p></div>
      <div class="card card-body"><p class="label">Laporan Berkendala</p><p class="font-mono text-3xl font-bold text-red-600 dark:text-red-400">{{ summary.kendala }}</p></div>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Tim</th><th>Area</th><th>Waktu</th><th>Uraian</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-for="r in reports.data" :key="r.id">
              <td class="num whitespace-nowrap">{{ fmtDate(r.date) }}</td>
              <td>{{ r.employee || '—' }}<p class="font-mono text-xs text-ink-400">{{ r.nip }}</p></td>
              <td>{{ r.team || '—' }}</td>
              <td>{{ r.area }}</td>
              <td class="num whitespace-nowrap">{{ r.start }}–{{ r.end }}<p class="text-xs text-ink-400">{{ r.hours }} jam</p></td>
              <td class="max-w-xs">{{ r.task }}<p v-if="r.issue" class="mt-1 text-xs text-red-600 dark:text-red-400">Kendala: {{ r.issue }}</p></td>
              <td><span :class="STATUS_BADGE[r.status]">{{ r.status_label }}</span><br /><span :class="REVIEW_BADGE[r.review_status]" class="mt-1">{{ r.review_label }}</span></td>
              <td class="text-right"><button class="btn-ghost btn-sm" @click="detailId = r.id">Detail<template v-if="r.photos_count || r.comments_count"> ({{ r.photos_count }}f/{{ r.comments_count }}k)</template></button></td>
            </tr>
            <tr v-if="!reports.data.length"><td colspan="8" class="py-10 text-center text-sm text-ink-400">Tidak ada laporan pada periode ini.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="reports.links" /></div>
    </div>

    <ReportDetailModal v-if="detailId" :report-id="detailId" @close="detailId = null" @changed="reload" />
  </AppLayout>
</template>
