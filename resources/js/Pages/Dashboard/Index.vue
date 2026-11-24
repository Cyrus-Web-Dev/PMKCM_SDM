<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PeriodFilter from '@/Components/PeriodFilter.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { Bar, Doughnut, Line, Radar } from 'vue-chartjs'
import {
  Chart as ChartJS, ArcElement, BarElement, LineElement, PointElement, CategoryScale, LinearScale,
  RadialLinearScale, Tooltip, Legend, Filler,
} from 'chart.js'

ChartJS.register(ArcElement, BarElement, LineElement, PointElement, CategoryScale, LinearScale, RadialLinearScale, Tooltip, Legend, Filler)

const props = defineProps({
  period: Object, periodLabels: Object, filters: Object, roles: Array,
  cards: Object, doughnut: Array, attendanceTrend: Object, radar: Object, teamScores: Array,
  scoreTrend: Object, reportsChart: Object, topIssues: Array, ranking: Array, alerts: Object,
  upcoming_holidays: { type: Array, default: () => [] },
})
const page = usePage()
const teams = computed(() => page.props.teams ?? [])

// Warna sumbu/teks mengikuti mode gelap saat halaman dimuat.
const dark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
ChartJS.defaults.color = dark ? '#9aa5b1' : '#5d6672'
ChartJS.defaults.borderColor = dark ? '#343c45' : '#d3d7dc'
ChartJS.defaults.font.family = '"IBM Plex Sans", sans-serif'

const C = { orange: '#e0590f', ink: '#343c45', green: '#15803d', red: '#b91c1c', amber: '#d97706', gray: '#8c96a2', blue: '#2563eb' }
const base = { responsive: true, maintainAspectRatio: false }
const intAxis = { y: { beginAtZero: true, ticks: { precision: 0 } } }

const doughnutData = computed(() => ({
  labels: props.doughnut.map((d) => d.label),
  datasets: [{ data: props.doughnut.map((d) => d.value), backgroundColor: [C.green, C.amber, C.blue, C.ink, C.red, C.gray], borderWidth: 0 }],
}))
const doughnutOptions = { ...base, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }

const trendData = computed(() => ({
  labels: props.attendanceTrend.labels,
  datasets: [
    { label: 'Hadir', data: props.attendanceTrend.present, borderColor: C.green, backgroundColor: C.green, tension: 0.3, pointRadius: 2 },
    { label: 'Terlambat', data: props.attendanceTrend.late, borderColor: C.amber, backgroundColor: C.amber, tension: 0.3, pointRadius: 2 },
    { label: 'Tidak hadir', data: props.attendanceTrend.absent, borderColor: C.red, backgroundColor: C.red, tension: 0.3, pointRadius: 2 },
  ],
}))
const trendOptions = { ...base, plugins: { legend: { position: 'bottom' } }, scales: intAxis }

const radarData = computed(() => ({
  labels: props.radar.labels,
  datasets: [{ label: 'Rata-rata nilai', data: props.radar.values, borderColor: C.orange, backgroundColor: 'rgba(224,89,15,0.2)', pointBackgroundColor: C.orange }],
}))
const radarOptions = { ...base, plugins: { legend: { display: false } }, scales: { r: { min: 0, max: 100, ticks: { stepSize: 25, backdropColor: 'transparent' } } } }

const teamData = computed(() => ({
  labels: props.teamScores.map((t) => t.label),
  datasets: [{ label: 'Rata-rata nilai', data: props.teamScores.map((t) => t.score), backgroundColor: C.orange, maxBarThickness: 42 }],
}))
const scoreBarOptions = { ...base, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100 } } }

const scoreTrendData = computed(() => ({
  labels: props.scoreTrend.labels,
  datasets: [{ label: 'Rata-rata nilai', data: props.scoreTrend.values, borderColor: C.ink, backgroundColor: 'rgba(52,60,69,0.15)', fill: true, tension: 0.3, spanGaps: true, pointRadius: 3 }],
}))

const reportsData = computed(() => ({
  labels: props.reportsChart.labels,
  datasets: [
    { label: 'Selesai', data: props.reportsChart.selesai, backgroundColor: C.green },
    { label: 'Proses', data: props.reportsChart.proses, backgroundColor: C.amber },
    { label: 'Kendala', data: props.reportsChart.kendala, backgroundColor: C.red },
  ],
}))
const reportsOptions = { ...base, plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }

const hasScores = computed(() => props.radar.values.some((v) => v !== null))
const cardList = computed(() => [
  { label: 'Karyawan Aktif', value: props.cards.employees },
  { label: 'Sudah Absen Hari Ini', value: props.cards.present_today, sub: `${props.cards.late_today} terlambat · ${props.cards.not_yet_today} belum` },
  { label: 'Rata-rata Nilai', value: props.cards.avg_score ?? '—' },
  { label: 'Total Laporan', value: props.cards.reports },
  { label: 'Izin / Cuti Menunggu', value: props.cards.pending_leaves },
])
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <template #title>Dashboard</template>

    <PeriodFilter route-name="dashboard" :period="period" :period-labels="periodLabels" :extra="{ team: filters.team, role: filters.role }">
      <template #default="{ f, apply }">
        <div>
          <label class="label">Tim</label>
          <select v-model="f.team" @change="apply" class="input"><option value="">Semua tim</option><option v-for="t in teams" :key="t" :value="t">{{ t }}</option></select>
        </div>
        <div>
          <label class="label">Role</label>
          <select v-model="f.role" @change="apply" class="input"><option value="">Semua role</option><option v-for="r in roles" :key="r" :value="r">{{ r }}</option></select>
        </div>
      </template>
    </PeriodFilter>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
      <div v-for="c in cardList" :key="c.label" class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">{{ c.label }}</p>
        <p class="mt-2 font-mono text-3xl font-bold text-ink-900 dark:text-ink-50">{{ c.value }}</p>
        <p v-if="c.sub" class="mt-1 text-xs text-ink-400">{{ c.sub }}</p>
      </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="card card-body"><h3 class="mb-3 font-serif font-semibold">Absensi Hari Ini</h3><div class="h-64"><Doughnut :data="doughnutData" :options="doughnutOptions" /></div></div>
      <div class="card card-body lg:col-span-2"><h3 class="mb-3 font-serif font-semibold">Tren Absensi (maks. 30 hari)</h3><div class="h-64"><Line :data="trendData" :options="trendOptions" /></div></div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="card card-body">
        <h3 class="mb-3 font-serif font-semibold">Nilai per Kriteria</h3>
        <div class="h-64"><Radar v-if="hasScores" :data="radarData" :options="radarOptions" /><p v-else class="pt-20 text-center text-sm text-ink-400">Belum ada penilaian pada periode ini.</p></div>
      </div>
      <div class="card card-body">
        <h3 class="mb-3 font-serif font-semibold">Nilai per Tim</h3>
        <div class="h-64"><Bar v-if="teamScores.length" :data="teamData" :options="scoreBarOptions" /><p v-else class="pt-20 text-center text-sm text-ink-400">Belum ada penilaian pada periode ini.</p></div>
      </div>
      <div class="card card-body"><h3 class="mb-3 font-serif font-semibold">Tren Nilai 6 Bulan</h3><div class="h-64"><Line :data="scoreTrendData" :options="scoreBarOptions" /></div></div>
    </div>

    <div class="card card-body"><h3 class="mb-3 font-serif font-semibold">Laporan Harian per Status</h3><div class="h-64"><Bar :data="reportsData" :options="reportsOptions" /></div></div>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="card card-body">
        <h3 class="mb-3 font-serif font-semibold">Kendala Terbanyak (per area)</h3>
        <ul class="space-y-2 text-sm">
          <li v-for="i in topIssues" :key="i.area" class="flex justify-between gap-2"><span>{{ i.area }}</span><b class="font-mono">{{ i.n }}</b></li>
          <li v-if="!topIssues.length" class="text-ink-400">Tidak ada laporan berkendala.</li>
        </ul>
      </div>
      <div class="card card-body">
        <h3 class="mb-3 font-serif font-semibold">Peringkat Nilai</h3>
        <ol class="space-y-2 text-sm">
          <li v-for="(r, i) in ranking" :key="r.name" class="flex justify-between gap-2"><span>{{ i + 1 }}. {{ r.name }} <span class="text-xs text-ink-400">{{ r.team }}</span></span><b class="font-mono">{{ r.score }}</b></li>
          <li v-if="!ranking.length" class="text-ink-400">Belum ada penilaian.</li>
        </ol>
      </div>
      <div class="card card-body">
        <h3 class="mb-3 font-serif font-semibold">Perlu Perhatian</h3>
        <p class="label">Belum isi laporan hari ini ({{ alerts.missing_count }})</p>
        <ul class="mb-3 space-y-1 text-sm">
          <li v-for="m in alerts.missing" :key="m.name">{{ m.name }} <span class="text-xs text-ink-400">{{ m.team }}</span></li>
          <li v-if="alerts.missing_count > alerts.missing.length" class="text-xs text-ink-400">…dan {{ alerts.missing_count - alerts.missing.length }} lainnya. <Link :href="route('daily-reports.all', { period: 'hari' })" class="text-seal-600 hover:underline">Lihat laporan</Link></li>
          <li v-if="!alerts.missing_count" class="text-emerald-700 dark:text-emerald-400">Semua sudah melapor.</li>
        </ul>
        <p class="label">Nilai di bawah 70</p>
        <ul class="space-y-1 text-sm">
          <li v-for="(l, i) in alerts.low_scores" :key="i" class="flex justify-between gap-2"><span>{{ l.name }} <span class="text-xs text-ink-400">{{ l.period }}</span></span><b class="font-mono text-red-600 dark:text-red-400">{{ l.score }}</b></li>
          <li v-if="!alerts.low_scores.length" class="text-ink-400">Tidak ada.</li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
