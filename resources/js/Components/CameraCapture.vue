<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

// Pengambilan foto LANGSUNG dari kamera (bukan unggah berkas) + watermark nama, tanggal, dan jam.
// Butuh HTTPS (atau localhost) agar browser mengizinkan akses kamera.
const props = defineProps({
  name: { type: String, default: '' },
  title: { type: String, default: 'Ambil Foto' },
  facing: { type: String, default: 'environment' },
  maxWidth: { type: Number, default: 1280 },
})
const emit = defineEmits(['captured', 'cancel'])

const video = ref(null)
const error = ref('')
const shot = ref(null)
let stream = null

async function start() {
  error.value = ''
  if (!navigator.mediaDevices?.getUserMedia) {
    error.value = 'Kamera tidak tersedia. Buka aplikasi lewat HTTPS (atau localhost) di browser yang mendukung kamera.'
    return
  }
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: props.facing }, width: { ideal: 1280 } }, audio: false })
    video.value.srcObject = stream
    await video.value.play()
  } catch (e) {
    error.value = 'Tidak bisa membuka kamera. Izinkan akses kamera pada browser lalu coba lagi.'
  }
}

function stop() {
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
}

function snap() {
  const v = video.value
  if (!v || !v.videoWidth) return
  const scale = Math.min(1, props.maxWidth / v.videoWidth)
  const w = Math.round(v.videoWidth * scale)
  const h = Math.round(v.videoHeight * scale)
  const c = document.createElement('canvas')
  c.width = w
  c.height = h
  const ctx = c.getContext('2d')
  ctx.drawImage(v, 0, 0, w, h)

  // Watermark: nama + tanggal + jam (dari jam perangkat; waktu resmi tetap dicatat server).
  const pad = (n) => String(n).padStart(2, '0')
  const d = new Date()
  const stamp = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
  const lines = [props.name, stamp].filter(Boolean)
  const fs = Math.max(14, Math.round(w / 38))
  ctx.font = `bold ${fs}px sans-serif`
  ctx.textBaseline = 'alphabetic'
  const bar = fs * lines.length * 1.25 + fs * 0.6
  ctx.fillStyle = 'rgba(0,0,0,0.55)'
  ctx.fillRect(0, h - bar, w, bar)
  ctx.fillStyle = '#fff'
  lines.forEach((t, i) => ctx.fillText(t, fs * 0.6, h - bar + fs * 1.1 + i * fs * 1.25))

  c.toBlob((blob) => {
    if (!blob) return
    stop()
    shot.value = { blob, url: URL.createObjectURL(blob) }
  }, 'image/jpeg', 0.82)
}

function retake() {
  if (shot.value) URL.revokeObjectURL(shot.value.url)
  shot.value = null
  start()
}

function use() {
  emit('captured', new File([shot.value.blob], 'foto.jpg', { type: 'image/jpeg' }))
}

function cancel() {
  emit('cancel')
}

onMounted(start)
onBeforeUnmount(() => {
  stop()
  if (shot.value) URL.revokeObjectURL(shot.value.url)
})
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
    <div class="card w-full max-w-lg">
      <div class="card-header"><h3 class="font-serif font-semibold">{{ title }}</h3></div>
      <div class="card-body space-y-3">
        <p v-if="error" class="border-l-4 border-red-600 bg-ink-50 px-3 py-2 text-sm text-red-700 dark:bg-ink-800 dark:text-red-300">{{ error }}</p>
        <video v-show="!shot && !error" ref="video" playsinline muted class="w-full bg-black"></video>
        <img v-if="shot" :src="shot.url" alt="Hasil foto" class="w-full" />
        <div class="flex flex-wrap gap-2">
          <button v-if="!shot && !error" type="button" class="btn-accent" @click="snap">Ambil Foto</button>
          <button v-if="shot" type="button" class="btn-accent" @click="use">Gunakan Foto</button>
          <button v-if="shot || error" type="button" class="btn-secondary" @click="retake">{{ error ? 'Coba Lagi' : 'Ulangi' }}</button>
          <button type="button" class="btn-ghost" @click="cancel">Batal</button>
        </div>
      </div>
    </div>
  </div>
</template>
