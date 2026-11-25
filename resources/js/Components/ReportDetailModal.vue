<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { fmtDate, STATUS_BADGE, REVIEW_BADGE } from '@/utils'

// Jendela detail laporan: foto bukti, komentar dua arah, dan (untuk Ka. URS) tombol review.
const props = defineProps({ reportId: { type: Number, required: true } })
const emit = defineEmits(['close', 'changed'])

const data = ref(null)
const error = ref('')
const body = ref('')
const note = ref('')
const busy = ref(false)
const zoom = ref(null)

async function load() {
  try {
    data.value = (await axios.get(route('daily-reports.detail', props.reportId))).data
  } catch (e) {
    error.value = 'Gagal memuat detail laporan.'
  }
}

async function call(fn) {
  busy.value = true
  error.value = ''
  try {
    await fn()
    await load()
    emit('changed')
  } catch (e) {
    error.value = e.response?.data?.errors ? Object.values(e.response.data.errors)[0][0] : (e.response?.data?.message ?? 'Terjadi kesalahan.')
  } finally {
    busy.value = false
  }
}

const sendComment = () => body.value.trim() && call(async () => {
  await axios.post(route('daily-reports.comments.store', props.reportId), { body: body.value })
  body.value = ''
})
const review = (status) => call(async () => {
  await axios.post(route('daily-reports.review', props.reportId), { review_status: status, note: note.value })
  note.value = ''
})

onMounted(load)
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4" @click.self="emit('close')">
    <div class="card my-6 w-full max-w-2xl">
      <div class="card-header">
        <h3 class="font-serif font-semibold">Detail Laporan</h3>
        <button class="btn-ghost btn-sm" @click="emit('close')">Tutup</button>
      </div>

      <div v-if="!data" class="card-body text-sm text-ink-500">{{ error || 'Memuat…' }}</div>

      <div v-else class="card-body space-y-4">
        <div>
          <p class="font-semibold">{{ data.report.employee }} <span class="font-mono text-xs text-ink-400">{{ data.report.nip }}</span></p>
          <p class="text-sm text-ink-500">
            {{ fmtDate(data.report.date) }} · Tim {{ data.report.team || '—' }} · {{ data.report.area }} ·
            <span class="font-mono">{{ data.report.start }}–{{ data.report.end }}</span> ({{ data.report.hours }} jam)
          </p>
          <div class="mt-2 flex gap-2">
            <span :class="STATUS_BADGE[data.report.status]">{{ data.report.status_label }}</span>
            <span :class="REVIEW_BADGE[data.report.review_status]">{{ data.report.review_label }}</span>
          </div>
        </div>

        <div>
          <p class="label">Uraian Pekerjaan</p>
          <p class="whitespace-pre-line text-sm">{{ data.report.task }}</p>
          <p v-if="data.report.issue" class="mt-2 text-sm text-red-600 dark:text-red-400">Kendala: {{ data.report.issue }}</p>
        </div>

        <div v-if="data.photos.length">
          <p class="label">Foto Bukti Kerja</p>
          <div class="flex flex-wrap gap-2">
            <img v-for="p in data.photos" :key="p.id" :src="p.url" alt="Foto bukti" class="h-24 w-24 cursor-zoom-in border border-ink-200 object-cover dark:border-ink-700" @click="zoom = p.url" />
          </div>
        </div>
        <p v-else class="text-xs text-ink-400">Tidak ada foto bukti pada laporan ini.</p>

        <div v-if="data.review_note" class="border-l-4 border-seal-500 bg-ink-50 px-3 py-2 text-sm dark:bg-ink-800">
          <b>Catatan review</b> ({{ data.reviewer }}, {{ data.reviewed_at }}): {{ data.review_note }}
        </div>

        <div v-if="data.can_review" class="space-y-2 border-t border-ink-200 pt-3 dark:border-ink-700">
          <p class="label">Review Ka. URS</p>
          <textarea v-model="note" rows="2" class="input" placeholder="Catatan (wajib bila Perlu Revisi)"></textarea>
          <div class="flex gap-2">
            <button class="btn-accent btn-sm" :disabled="busy" @click="review('disetujui')">Setujui</button>
            <button class="btn-danger btn-sm" :disabled="busy" @click="review('perlu_revisi')">Perlu Revisi</button>
          </div>
        </div>

        <div class="space-y-2 border-t border-ink-200 pt-3 dark:border-ink-700">
          <p class="label">Komentar</p>
          <p v-if="!data.comments.length" class="text-xs text-ink-400">Belum ada komentar.</p>
          <div v-for="c in data.comments" :key="c.id" class="text-sm" :class="c.mine ? 'text-right' : ''">
            <span class="inline-block max-w-[85%] border border-ink-200 px-3 py-2 text-left dark:border-ink-700" :class="c.mine ? 'bg-ink-50 dark:bg-ink-800' : ''">
              <b>{{ c.author }}</b> <span class="text-xs text-ink-400">{{ c.at }}</span><br />{{ c.body }}
            </span>
          </div>
          <div class="flex gap-2">
            <input v-model="body" class="input" placeholder="Tulis komentar…" @keyup.enter="sendComment" />
            <button class="btn-secondary btn-sm" :disabled="busy" @click="sendComment">Kirim</button>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      </div>
    </div>

    <div v-if="zoom" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4" @click="zoom = null">
      <img :src="zoom" alt="Foto bukti" class="max-h-full max-w-full" />
    </div>
  </div>
</template>
