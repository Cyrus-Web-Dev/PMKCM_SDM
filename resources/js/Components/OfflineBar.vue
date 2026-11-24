<script setup>
import { computed, ref, watch } from 'vue'
import { offlineState, syncAll, removeItem, retryItem } from '@/offline'

const open = ref(false)
const pending = computed(() => offlineState.items.filter((i) => i.status !== 'failed'))
const failed = computed(() => offlineState.items.filter((i) => i.status === 'failed'))

// Pesan singkat (mis. "3 data berhasil disinkronkan") hilang sendiri.
let timer = null
watch(() => offlineState.message, (m) => {
  clearTimeout(timer)
  if (m) timer = setTimeout(() => { offlineState.message = '' }, 7000)
})

const fmt = (iso) => new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div v-if="!offlineState.online || offlineState.items.length || offlineState.message" class="noprint space-y-2">
    <div v-if="!offlineState.online" class="border-l-4 border-amber-500 bg-white px-4 py-3 text-sm dark:bg-ink-800">
      <b>Kamu sedang offline.</b> Absen dan laporan harian tetap bisa diisi; datanya disimpan di perangkat dan dikirim otomatis saat sinyal kembali.
    </div>

    <div v-if="offlineState.message" class="border-l-4 border-emerald-600 bg-white px-4 py-3 text-sm text-emerald-800 dark:bg-ink-800 dark:text-emerald-300">{{ offlineState.message }}</div>

    <div v-if="offlineState.items.length" class="border-l-4 border-seal-500 bg-white px-4 py-3 text-sm dark:bg-ink-800">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p>
          <b>{{ pending.length }}</b> data menunggu sinkron<template v-if="failed.length">, <b class="text-red-600 dark:text-red-400">{{ failed.length }}</b> gagal</template>.
        </p>
        <div class="flex gap-2">
          <button class="btn-accent btn-sm" :disabled="offlineState.syncing || !offlineState.online || !pending.length" @click="syncAll">{{ offlineState.syncing ? 'Mengirim…' : 'Sinkronkan sekarang' }}</button>
          <button class="btn-ghost btn-sm" @click="open = !open">{{ open ? 'Sembunyikan' : 'Lihat' }}</button>
        </div>
      </div>
      <ul v-if="open" class="mt-3 divide-y divide-ink-200 dark:divide-ink-700">
        <li v-for="i in offlineState.items" :key="i.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
          <div>
            <p class="font-medium">{{ i.label }} <span class="font-mono text-xs text-ink-400">{{ fmt(i.createdAt) }}</span></p>
            <p v-if="i.status === 'failed'" class="text-xs text-red-600 dark:text-red-400">Ditolak server: {{ i.error }}</p>
            <p v-else class="text-xs text-ink-400">Menunggu sinkron<template v-if="i.fileCount"> · {{ i.fileCount }} foto</template></p>
          </div>
          <div class="flex gap-2">
            <button v-if="i.status === 'failed'" class="btn-ghost btn-sm" @click="retryItem(i.id)">Coba lagi</button>
            <button class="btn-ghost btn-sm text-red-600" @click="removeItem(i.id)">Hapus</button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
