<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CameraCapture from '@/Components/CameraCapture.vue'
import ReportDetailModal from '@/Components/ReportDetailModal.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { fmtDate, STATUS_BADGE, REVIEW_BADGE } from '@/utils'
import { postOrQueue } from '@/offline'

const props = defineProps({ reports: Array, month: String, defaultTeam: String, today: String, employeeName: String, maxPhotos: Number, workOrders: { type: Array, default: () => [] } })
const page = usePage()
const teams = computed(() => page.props.teams ?? [])

const form = useForm({
  report_date: props.today,
  team: props.defaultTeam ?? '',
  area: '',
  start_time: '07:00',
  end_time: '15:00',
  task: '',
  issue: '',
  work_order_id: '',
  status: 'selesai',
})

// ---- foto bukti kerja (kamera realtime, 1-3 foto) ----
const photos = ref([])
const camera = ref(false)
const editing = ref(null)
const detailId = ref(null)
const sending = ref(false)
const notice = ref('')
const errorMsg = ref('')

const existingPhotos = computed(() => editing.value?.photos_count ?? 0)
const slotsLeft = computed(() => props.maxPhotos - existingPhotos.value - photos.value.length)
const photoError = computed(() => form.errors.photos || form.errors['photos.0'] || '')

function onShot(file) {
  photos.value.push({ file, url: URL.createObjectURL(file) })
  camera.value = false
}
function removePhoto(i) {
  URL.revokeObjectURL(photos.value[i].url)
  photos.value.splice(i, 1)
}
function clearPhotos() {
  photos.value.forEach((p) => URL.revokeObjectURL(p.url))
  photos.value = []
}
onBeforeUnmount(clearPhotos)

form.transform((data) => ({ ...data, photos: photos.value.map((p) => p.file) }))

function startEdit(r) {
  editing.value = r
  clearPhotos()
  form.clearErrors()
  Object.assign(form, { report_date: r.date, team: r.team ?? '', area: r.area, start_time: r.start, end_time: r.end, task: r.task, issue: r.issue ?? '', status: r.status })
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
function cancelEdit() {
  editing.value = null
  clearPhotos()
  form.reset()
  form.clearErrors()
}

async function submit() {
  notice.value = ''
  errorMsg.value = ''

  // Revisi: butuh koneksi (mengubah laporan yang sudah ada di server).
  if (editing.value) {
    const done = () => {
      clearPhotos()
      editing.value = null
      form.reset('area', 'task', 'issue', 'status', 'report_date')
    }
    form.put(route('daily-reports.update', editing.value.id), { preserveScroll: true, forceFormData: true, onSuccess: done })
    return
  }

  // Laporan baru: dikirim langsung, atau disimpan di perangkat bila tidak ada koneksi (dikirim otomatis nanti).
  form.clearErrors()
  sending.value = true
  try {
    const r = await postOrQueue({
      type: 'report',
      url: route('daily-reports.store'),
      label: `Laporan ${fmtDate(form.report_date)} - ${form.area}`,
      fields: {
        report_date: form.report_date, team: form.team, area: form.area,
        start_time: form.start_time, end_time: form.end_time, task: form.task, issue: form.issue, status: form.status, work_order_id: form.work_order_id,
      },
      files: photos.value.map((p) => ({ field: 'photos[]', blob: p.file, name: 'foto.jpg' })),
    })
    notice.value = r.message
    clearPhotos()
    form.reset('area', 'task', 'issue', 'status', 'report_date')
    if (r.status === 'sent') router.reload({ only: ['reports'] })
  } catch (e) {
    if (e.errors) form.setError(Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])))
    else errorMsg.value = e.message
  } finally {
    sending.value = false
  }
}

function changeMonth(e) {
  router.get(route('daily-reports.index'), { month: e.target.value || props.month }, { preserveState: true, preserveScroll: true, replace: true })
}
function remove(r) {
  if (confirm(`Hapus laporan ${fmtDate(r.date)} (${r.area})?`)) router.delete(route('daily-reports.destroy', r.id), { preserveScroll: true })
}
const reload = () => router.reload({ only: ['reports'] })

const totalHours = computed(() => props.reports.reduce((a, r) => a + r.hours, 0).toFixed(1))
</script>

<template>
  <AppLayout>
    <Head title="Laporan Harian" />
    <template #title>Laporan Harian</template>

    <div class="grid items-start gap-5 lg:grid-cols-[400px_1fr]">
      <form @submit.prevent="submit" class="card card-body space-y-3">
        <h3 class="font-serif font-semibold">{{ editing ? 'Revisi Laporan' : 'Isi Laporan Hari Ini' }}</h3>
        <p v-if="notice" class="border-l-4 border-emerald-600 bg-ink-50 px-3 py-2 text-sm text-emerald-800 dark:bg-ink-800 dark:text-emerald-300">{{ notice }}</p>
        <p v-if="errorMsg" class="border-l-4 border-red-600 bg-ink-50 px-3 py-2 text-sm text-red-700 dark:bg-ink-800 dark:text-red-300">{{ errorMsg }}</p>
        <div v-if="editing?.review_note" class="border-l-4 border-seal-500 bg-ink-50 px-3 py-2 text-sm dark:bg-ink-800"><b>Catatan Ka. URS:</b> {{ editing.review_note }}</div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">Tanggal</label>
            <input v-model="form.report_date" type="date" :max="today" :disabled="!!editing" class="input" required />
            <p v-if="form.errors.report_date" class="error-text">{{ form.errors.report_date }}</p>
          </div>
          <div>
            <label class="label">Tim</label>
            <select v-model="form.team" class="input">
              <option value="">—</option>
              <option v-for="t in teams" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
        </div>
        <div v-if="!editing && workOrders.length">
          <label class="label">Perintah Kerja (opsional)</label>
          <select v-model="form.work_order_id" class="input">
            <option value="">Tidak terkait perintah kerja</option>
            <option v-for="w in workOrders" :key="w.id" :value="w.id">{{ w.label }}</option>
          </select>
          <p v-if="form.errors.work_order_id" class="error-text">{{ form.errors.work_order_id }}</p>
        </div>
        <div>
          <label class="label">Area / Lokasi Kerja</label>
          <input v-model="form.area" class="input" placeholder="mis. Gudang Semen Area B" required />
          <p v-if="form.errors.area" class="error-text">{{ form.errors.area }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">Jam Mulai</label>
            <input v-model="form.start_time" type="time" class="input" required />
            <p v-if="form.errors.start_time" class="error-text">{{ form.errors.start_time }}</p>
          </div>
          <div>
            <label class="label">Jam Selesai</label>
            <input v-model="form.end_time" type="time" class="input" required />
            <p v-if="form.errors.end_time" class="error-text">{{ form.errors.end_time }}</p>
          </div>
        </div>
        <div>
          <label class="label">Uraian Pekerjaan</label>
          <textarea v-model="form.task" rows="3" class="input" required></textarea>
          <p v-if="form.errors.task" class="error-text">{{ form.errors.task }}</p>
        </div>
        <div>
          <label class="label">Kendala (jika ada)</label>
          <textarea v-model="form.issue" rows="2" class="input"></textarea>
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model="form.status" class="input">
            <option value="selesai">Selesai</option>
            <option value="proses">Proses</option>
            <option value="kendala">Kendala</option>
          </select>
        </div>

        <div>
          <label class="label">Foto Bukti Kerja ({{ existingPhotos + photos.length }}/{{ maxPhotos }}) <span v-if="form.status === 'selesai' && !existingPhotos" class="text-red-600">* wajib</span></label>
          <div class="flex flex-wrap gap-2">
            <div v-for="(p, i) in photos" :key="p.url" class="relative">
              <img :src="p.url" alt="Foto" class="h-20 w-20 border border-ink-200 object-cover dark:border-ink-700" />
              <button type="button" class="absolute -right-1 -top-1 bg-red-600 px-1.5 text-xs font-bold text-white" title="Hapus foto" @click="removePhoto(i)">&times;</button>
            </div>
            <button v-if="slotsLeft > 0" type="button" class="btn-secondary btn-sm h-20" @click="camera = true">+ Ambil Foto</button>
          </div>
          <p v-if="editing && existingPhotos" class="mt-1 text-xs text-ink-400">{{ existingPhotos }} foto sebelumnya tetap tersimpan.</p>
          <p v-if="photoError" class="error-text">{{ photoError }}</p>
        </div>

        <div class="flex gap-2">
          <button type="submit" :disabled="form.processing || sending" class="btn-accent">{{ form.processing || sending ? 'Menyimpan…' : editing ? 'Simpan Revisi' : 'Simpan Laporan' }}</button>
          <button v-if="editing" type="button" class="btn-secondary" @click="cancelEdit">Batal</button>
        </div>
      </form>

      <div class="card">
        <div class="card-header flex-wrap">
          <div class="flex items-center gap-3">
            <h3 class="font-serif font-semibold">Riwayat Laporan Saya</h3>
            <input type="month" :value="month" @change="changeMonth" class="input max-w-[170px]" />
          </div>
          <p class="text-sm text-ink-500 dark:text-ink-400">{{ reports.length }} laporan · <b class="font-mono">{{ totalHours }}</b> jam</p>
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Tim</th><th>Area</th><th>Waktu</th><th>Uraian</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <tr v-for="r in reports" :key="r.id">
                <td class="num whitespace-nowrap">{{ fmtDate(r.date) }}</td>
                <td>{{ r.team || '—' }}</td>
                <td>{{ r.area }}<p v-if="r.work_order" class="font-mono text-xs text-seal-600 dark:text-seal-400">{{ r.work_order }}</p></td>
                <td class="num whitespace-nowrap">{{ r.start }}–{{ r.end }}<p class="text-xs text-ink-400">{{ r.hours }} jam</p></td>
                <td class="max-w-xs">{{ r.task }}<p v-if="r.issue" class="mt-1 text-xs text-red-600 dark:text-red-400">Kendala: {{ r.issue }}</p><p class="mt-1 text-xs text-ink-400">{{ r.photos_count }} foto · {{ r.comments_count }} komentar</p></td>
                <td><span :class="STATUS_BADGE[r.status]">{{ r.status_label }}</span><br /><span :class="REVIEW_BADGE[r.review_status]" class="mt-1">{{ r.review_label }}</span></td>
                <td class="text-right">
                  <div class="flex flex-col items-end gap-1">
                    <button class="btn-ghost btn-sm" @click="detailId = r.id">Detail</button>
                    <button v-if="r.can_edit" class="btn-ghost btn-sm" @click="startEdit(r)">{{ r.review_status === 'perlu_revisi' ? 'Revisi' : 'Ubah' }}</button>
                    <button v-if="r.can_delete" class="btn-ghost btn-sm text-red-600" @click="remove(r)">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!reports.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Belum ada laporan bulan ini.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <CameraCapture v-if="camera" :name="employeeName" title="Foto Bukti Kerja" @captured="onShot" @cancel="camera = false" />
    <ReportDetailModal v-if="detailId" :report-id="detailId" @close="detailId = null" @changed="reload" />
  </AppLayout>
</template>
