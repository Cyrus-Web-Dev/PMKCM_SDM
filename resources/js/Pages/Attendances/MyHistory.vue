<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CameraCapture from '@/Components/CameraCapture.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { fmtDate } from '@/utils'
import { postOrQueue, offlineState } from '@/offline'

const props = defineProps({
  attendances: Array, month: String, shifts: Array, suggestedShift: Number,
  open: Object, todayRecord: Object, tolerance: Number, employeeName: String,
})
const page = usePage()

const shiftId = ref(props.suggestedShift ?? props.shifts[0]?.id ?? '')
const camera = ref(null) // 'in' | 'out' | null
const busy = ref(false)
const errorMsg = ref('')
const notice = ref('')
const firstError = computed(() => errorMsg.value || Object.values(page.props.errors ?? {})[0] || '')

// Antrean offline: absen yang tersimpan di perangkat tetapi belum terkirim.
const queued = computed(() => offlineState.items.filter((i) => i.status !== 'failed'))
const queuedIn = computed(() => queued.value.some((i) => i.type === 'checkin'))
const queuedOut = computed(() => queued.value.some((i) => i.type === 'checkout'))
const canCheckOut = computed(() => (!!props.open || queuedIn.value) && !queuedOut.value)

const checkedIn = computed(() => !!props.open || !!props.todayRecord?.check_in)
const blockedStatus = computed(() => (['izin', 'sakit', 'cuti', 'libur'].includes(props.todayRecord?.status) ? props.todayRecord.status : null))

const statusBadge = { hadir: 'badge-green', terlambat: 'badge-amber', izin: 'badge-slate', sakit: 'badge-slate', alpha: 'badge-red', cuti: 'badge-seal', wfh: 'badge-green', libur: 'badge-slate' }
const statusLabels = { hadir: 'Tepat Waktu', terlambat: 'Terlambat', izin: 'Izin', sakit: 'Sakit', alpha: 'Alfa', cuti: 'Cuti', wfh: 'WFH', libur: 'Libur' }

function getLocation() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null)
    navigator.geolocation.getCurrentPosition((p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }), () => resolve(null), { timeout: 6000 })
  })
}

async function sendAttendance(kind, file) {
  busy.value = true
  errorMsg.value = ''
  notice.value = ''
  const loc = await getLocation()
  const fields = { ...(loc ?? {}) }
  if (kind === 'in') fields.shift_id = shiftId.value
  try {
    const r = await postOrQueue({
      type: kind === 'in' ? 'checkin' : 'checkout',
      url: route(kind === 'in' ? 'attendances.check-in' : 'attendances.check-out'),
      label: kind === 'in' ? 'Absen datang' : 'Absen pulang',
      fields,
      files: file ? [{ field: 'photo', blob: file, name: 'absen.jpg' }] : [],
    })
    notice.value = r.message
    if (r.status === 'sent') router.reload()
  } catch (e) {
    errorMsg.value = e.message
  } finally {
    busy.value = false
  }
}

function submitPhoto(file) {
  const kind = camera.value
  camera.value = null
  sendAttendance(kind, file)
}

// Check-out tanpa foto (foto opsional).
const checkOutNoPhoto = () => sendAttendance('out', null)

function changeMonth(e) {
  router.get(route('attendances.my-history'), { month: e.target.value || props.month }, { preserveState: true, preserveScroll: true, replace: true })
}

function duration(a) {
  if (!a.check_in || !a.check_out) return '-'
  const [ih, im] = a.check_in.split(':').map(Number)
  const [oh, om] = a.check_out.split(':').map(Number)
  let m = oh * 60 + om - (ih * 60 + im)
  if (m < 0) m += 1440
  return `${Math.floor(m / 60)}j ${m % 60}m`
}
</script>

<template>
  <AppLayout>
    <Head title="Absen Saya" />
    <template #title>Absen Saya</template>

    <div class="card card-body space-y-3">
      <h3 class="font-serif font-semibold">Absensi</h3>
      <p v-if="firstError" class="border-l-4 border-red-600 bg-ink-50 px-3 py-2 text-sm text-red-700 dark:bg-ink-800 dark:text-red-300">{{ firstError }}</p>

      <p v-if="notice" class="border-l-4 border-emerald-600 bg-ink-50 px-3 py-2 text-sm text-emerald-800 dark:bg-ink-800 dark:text-emerald-300">{{ notice }}</p>
      <p v-if="queuedIn && !open" class="border-l-4 border-seal-500 bg-ink-50 px-3 py-2 text-sm dark:bg-ink-800">Absen datang tersimpan di perangkat dan menunggu sinkron.</p>

      <p v-if="blockedStatus" class="text-sm text-ink-600 dark:text-ink-300">Hari ini tercatat sebagai <b>{{ statusLabels[blockedStatus] }}</b>. Tidak perlu absen.</p>

      <template v-else-if="!checkedIn && !queuedIn">
        <div class="max-w-sm">
          <label class="label">Shift</label>
          <select v-model="shiftId" class="input">
            <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start }} – {{ s.end }})</option>
          </select>
          <p v-if="!shifts.length" class="error-text">Belum ada shift. Hubungi Owner / Ka. URS.</p>
        </div>
        <p class="text-xs text-ink-400">Toleransi keterlambatan {{ tolerance }} menit dari jam mulai shift. Foto wajib diambil langsung dari kamera.</p>
        <button class="btn-accent self-start" :disabled="busy || !shiftId" @click="camera = 'in'">{{ busy ? 'Memproses…' : 'Absen Datang (Foto)' }}</button>
      </template>

      <template v-if="checkedIn || queuedIn">
        <p v-if="checkedIn" class="text-sm">
          Check-in pukul <b class="font-mono">{{ (open ?? todayRecord).check_in?.slice(0, 5) }}</b>
          <span :class="statusBadge[(open ?? todayRecord).status]" class="ml-2">{{ statusLabels[(open ?? todayRecord).status] }}<template v-if="(open ?? todayRecord).late_minutes > 0"> {{ (open ?? todayRecord).late_minutes }} mnt</template></span>
          <span v-if="(open ?? todayRecord).shift_name" class="ml-2 text-ink-400">· {{ (open ?? todayRecord).shift_name }}</span>
        </p>
        <div v-if="canCheckOut" class="flex flex-wrap gap-2">
          <button class="btn-accent" :disabled="busy" @click="camera = 'out'">Absen Pulang (Foto)</button>
          <button class="btn-secondary" :disabled="busy" @click="checkOutNoPhoto">Pulang tanpa foto</button>
        </div>
        <p v-else-if="todayRecord?.check_out && !queuedOut" class="text-sm text-emerald-700 dark:text-emerald-400">Absensi lengkap — check-out pukul <b class="font-mono">{{ todayRecord.check_out?.slice(0, 5) }}</b>.</p>
      </template>
    </div>

    <CameraCapture v-if="camera" :name="employeeName" :title="camera === 'in' ? 'Foto Absen Datang' : 'Foto Absen Pulang'" facing="user" @captured="submitPhoto" @cancel="camera = null" />

    <div class="card">
      <div class="card-header">
        <h3 class="font-serif font-semibold">Riwayat Absensi</h3>
        <input type="month" :value="month" @change="changeMonth" class="input max-w-[170px]" />
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Durasi</th><th>Status</th><th>Catatan</th></tr></thead>
          <tbody>
            <tr v-for="a in attendances" :key="a.id">
              <td class="num whitespace-nowrap">{{ fmtDate(a.date) }}</td>
              <td>{{ a.shift_name || '—' }}</td>
              <td class="num">{{ a.check_in?.slice(0, 5) || '-' }}<a v-if="a.has_in_photo" :href="route('attendances.photo', { attendance: a.id, type: 'in' })" target="_blank" class="ml-2 text-xs text-seal-600 hover:underline">foto</a></td>
              <td class="num">{{ a.check_out?.slice(0, 5) || '-' }}<a v-if="a.has_out_photo" :href="route('attendances.photo', { attendance: a.id, type: 'out' })" target="_blank" class="ml-2 text-xs text-seal-600 hover:underline">foto</a></td>
              <td class="num">{{ duration(a) }}</td>
              <td><span :class="statusBadge[a.status]">{{ statusLabels[a.status] }}</span><span v-if="a.late_minutes > 0" class="ml-1 text-xs text-ink-400">{{ a.late_minutes }} mnt</span><span v-if="a.is_offline" class="badge-amber ml-1" :title="'Dicatat offline, waktu perangkat ' + (a.device_time || '')">Offline</span></td>
              <td class="max-w-xs text-xs text-ink-500">{{ a.notes }}</td>
            </tr>
            <tr v-if="!attendances.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Belum ada data absensi bulan ini.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
