<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { fmtDate, STATUS_BADGE, WO_BADGE, gradeOf, gradeBadge } from '@/utils'

const props = defineProps({
  order: Object, members: Array, reports: Array, transitions: Array, statuses: Object,
  canManage: Boolean, canChangeStatus: Boolean, canRate: Boolean, criteria: Array, today: String,
})

// ---- ubah status ----
const endDate = ref(props.today)
function changeStatus(to) {
  const label = props.statuses[to]
  if (!confirm(`Ubah status menjadi "${label}"?`)) return
  router.post(route('work-orders.status', props.order.id), { status: to, end_date: to === 'selesai' ? endDate.value : undefined }, { preserveScroll: true })
}
function remove() {
  if (confirm(`Hapus perintah kerja ${props.order.number}?`)) router.delete(route('work-orders.destroy', props.order.id))
}

// ---- penilaian anggota ----
const rating = ref(null) // anggota yang sedang dinilai
const form = useForm({ employee_id: '', quality: 80, discipline: 80, teamwork: 80, initiative: 80, safety: 80, note: '' })

function openRating(m) {
  rating.value = m
  form.clearErrors()
  form.employee_id = m.id
  for (const c of props.criteria) form[c.key] = m.rating?.scores[c.key] ?? 80
  form.note = m.rating?.note ?? ''
}
function submitRating() {
  form.post(route('work-orders.rate', props.order.id), { preserveScroll: true, onSuccess: () => { rating.value = null } })
}
const total = () => Math.round(props.criteria.reduce((a, c) => a + Number(form[c.key] || 0), 0) / props.criteria.length)
</script>

<template>
  <AppLayout>
    <Head :title="order.number" />
    <template #title>{{ order.number }}</template>
    <template #actions>
      <Link v-if="canManage" :href="route('work-orders.edit', order.id)" class="btn-secondary btn-sm">Ubah</Link>
      <button v-if="canManage && order.status !== 'selesai'" class="btn-danger btn-sm" @click="remove">Hapus</button>
      <Link v-if="order.status === 'selesai'" :href="route('history-cards.show', order.id)" class="btn-secondary btn-sm">History Card</Link>
      <Link :href="route('work-orders.index')" class="btn-ghost btn-sm">&larr; Daftar</Link>
    </template>

    <div class="card card-body space-y-4">
      <div class="flex flex-wrap items-center gap-3">
        <span :class="WO_BADGE[order.status]" class="text-sm">{{ order.status_label }}</span>
        <span class="text-sm text-ink-500">Dibuat {{ order.created_at }}<template v-if="order.creator"> oleh {{ order.creator }}</template></span>
      </div>
      <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div><p class="label">Tanggal Perintah</p>{{ fmtDate(order.order_date) }}</div>
        <div><p class="label">Tanggal Mulai</p>{{ fmtDate(order.start_date) }}</div>
        <div><p class="label">Tanggal Selesai</p>{{ fmtDate(order.end_date) }}<span v-if="order.duration_days" class="text-xs text-ink-400"> ({{ order.duration_days }} hari)</span></div>
        <div><p class="label">Kepala Kerja</p><b>{{ order.leader }}</b></div>
        <div><p class="label">Tim</p>{{ order.team || '—' }}</div>
        <div class="sm:col-span-3"><p class="label">Area / Peralatan</p>{{ order.area || '—' }}</div>
      </div>
      <div><p class="label">Uraian Pekerjaan</p><p class="whitespace-pre-line text-sm">{{ order.description }}</p></div>

      <div v-if="canChangeStatus" class="flex flex-wrap items-end gap-3 border-t border-ink-200 pt-4 dark:border-ink-700">
        <div v-if="transitions.includes('selesai')">
          <label class="label">Tanggal Selesai</label>
          <input v-model="endDate" type="date" :max="today" class="input" />
        </div>
        <button v-for="t in transitions" :key="t" :class="t === 'dibatalkan' ? 'btn-danger btn-sm' : 'btn-accent btn-sm'" @click="changeStatus(t)">
          {{ order.status === 'selesai' || order.status === 'dibatalkan' ? 'Buka kembali: ' : 'Tandai ' }}{{ statuses[t] }}
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-serif font-semibold">Anggota Kerja &amp; Penilaian ({{ members.length }})</h3></div>
      <p v-if="order.status !== 'selesai'" class="px-5 pt-4 text-xs text-ink-400">Penilaian anggota dibuka oleh kepala kerja setelah perintah kerja berstatus <b>Selesai</b>.</p>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Anggota</th><th>Nilai</th><th>Catatan</th><th></th></tr></thead>
          <tbody>
            <template v-for="m in members" :key="m.id">
              <tr>
                <td>{{ m.name }}<p class="font-mono text-xs text-ink-400">{{ m.nip }}</p></td>
                <td><span v-if="m.rating" :class="gradeBadge(m.rating.total)">{{ m.rating.total }} · {{ m.rating.grade }}</span><span v-else class="text-ink-400">—</span></td>
                <td class="max-w-xs text-xs text-ink-500">{{ m.rating?.note }}</td>
                <td class="text-right"><button v-if="canRate" class="btn-secondary btn-sm" @click="openRating(m)">{{ m.rating ? 'Ubah Nilai' : 'Beri Nilai' }}</button></td>
              </tr>
              <tr v-if="rating && rating.id === m.id">
                <td colspan="4" class="bg-ink-50 dark:bg-ink-800/60">
                  <form class="space-y-3 py-2" @submit.prevent="submitRating">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                      <div v-for="c in criteria" :key="c.key">
                        <div class="flex items-center justify-between"><label class="label !mb-0">{{ c.label }}</label><input v-model.number="form[c.key]" type="number" min="0" max="100" class="input !w-20 text-center font-mono" /></div>
                        <input v-model.number="form[c.key]" type="range" min="0" max="100" class="mt-1 w-full accent-seal-500" />
                        <p v-if="form.errors[c.key]" class="error-text">{{ form.errors[c.key] }}</p>
                      </div>
                    </div>
                    <textarea v-model="form.note" rows="2" class="input" placeholder="Catatan penilaian (opsional)"></textarea>
                    <p v-if="form.errors.employee_id" class="error-text">{{ form.errors.employee_id }}</p>
                    <div class="flex items-center gap-3">
                      <button type="submit" class="btn-accent btn-sm" :disabled="form.processing">Simpan Nilai</button>
                      <button type="button" class="btn-ghost btn-sm" @click="rating = null">Batal</button>
                      <span class="ml-auto text-sm">Nilai akhir <b class="font-mono text-lg">{{ total() }}</b> <span :class="gradeBadge(total())">{{ gradeOf(total()) }}</span></span>
                    </div>
                  </form>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-serif font-semibold">Laporan Harian Terkait ({{ reports.length }})</h3></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Area</th><th>Uraian</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="r in reports" :key="r.id">
              <td class="num whitespace-nowrap">{{ fmtDate(r.date) }}</td>
              <td>{{ r.employee || '—' }}</td>
              <td>{{ r.area }}</td>
              <td class="max-w-xs">{{ r.task }}</td>
              <td><span :class="STATUS_BADGE[r.status]">{{ r.status_label }}</span></td>
            </tr>
            <tr v-if="!reports.length"><td colspan="5" class="py-8 text-center text-sm text-ink-400">Belum ada laporan harian yang ditautkan ke perintah kerja ini.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
