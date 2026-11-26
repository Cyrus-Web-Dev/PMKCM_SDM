<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'

defineProps({ files: Array, keep: Number, driver: String, nativeTool: String, pdfCounts: Object })

function createBackup() {
  router.post(route('backup.store'))
}
function destroy(file) {
  if (confirm(`Hapus file backup ${file.name}?`)) router.delete(route('backup.destroy', file.name))
}
function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}
</script>

<template>
  <AppLayout>
    <Head title="Backup Data" />
    <template #title>Backup Data</template>

    <p class="text-sm text-ink-500 dark:text-ink-400">Ada 4 jenis backup. Backup Database dipakai untuk memulihkan sistem; tiga lainnya berupa dokumen PDF untuk arsip.</p>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="card card-body flex flex-col gap-2">
        <p class="font-serif text-xs font-semibold uppercase tracking-widest text-seal-600 dark:text-seal-400">1 · Database (keseluruhan)</p>
        <p class="flex-1 text-sm text-ink-600 dark:text-ink-300">Salinan seluruh database untuk pemulihan sistem. Maksimal {{ keep }} file terakhir disimpan; backup otomatis tiap hari 02:00.</p>
        <button class="btn-accent btn-sm" @click="createBackup">Buat Backup Sekarang</button>
      </div>

      <div class="card card-body flex flex-col gap-2">
        <p class="font-serif text-xs font-semibold uppercase tracking-widest text-seal-600 dark:text-seal-400">2 · Data Owner</p>
        <p class="flex-1 text-sm text-ink-600 dark:text-ink-300">Semua milik super admin: akun, data karyawan, perintah kerja yang dibuat, penilaian, review, dan log aktivitas. ({{ pdfCounts.owner }} akun)</p>
        <a :href="route('backup.pdf', 'owner')" class="btn-secondary btn-sm">Unduh PDF</a>
      </div>

      <div class="card card-body flex flex-col gap-2">
        <p class="font-serif text-xs font-semibold uppercase tracking-widest text-seal-600 dark:text-seal-400">3 · Data Role Tambahan</p>
        <p class="flex-1 text-sm text-ink-600 dark:text-ink-300">Role buatan Owner (mis. KA Unit, KA Gudang): hak akses, daftar pengguna, dan log aktivitasnya. ({{ pdfCounts.roles }} role)</p>
        <a :href="route('backup.pdf', 'roles')" class="btn-secondary btn-sm">Unduh PDF</a>
      </div>

      <div class="card card-body flex flex-col gap-2">
        <p class="font-serif text-xs font-semibold uppercase tracking-widest text-seal-600 dark:text-seal-400">4 · Data Karyawan</p>
        <p class="flex-1 text-sm text-ink-600 dark:text-ink-300">Seluruh data karyawan beserta tim, status kepegawaian, dan akun login. ({{ pdfCounts.employees }} karyawan)</p>
        <a :href="route('backup.pdf', 'employees')" class="btn-secondary btn-sm">Unduh PDF</a>
      </div>
    </div>

    <div class="card card-body flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-ink-600 dark:text-ink-300"><b>Semua data dalam 1 file PDF</b> — Owner + Role Tambahan + Karyawan digabung.</p>
      <a :href="route('backup.pdf', 'all')" class="btn-accent btn-sm">Unduh PDF Gabungan</a>
    </div>
    <p class="text-xs text-ink-400">PDF tidak memuat data sensitif (gaji, rekening, NPWP, BPJS, password) dan tidak bisa dipakai memulihkan sistem. File PDF dibuat saat diminta dan tidak disimpan di server.</p>

    <div class="card card-body text-sm text-ink-600 dark:text-ink-300">
      <p>Database: <span class="font-mono">{{ driver }}</span> &middot; Tool native: <span class="font-mono">{{ nativeTool || 'tidak ditemukan (memakai mode fallback PHP)' }}</span></p>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="font-serif font-semibold">File Backup Database</h3></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Nama File</th><th>Mode</th><th>Ukuran</th><th>Dibuat</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="f in files" :key="f.name">
              <td class="font-mono text-xs">{{ f.name }}</td>
              <td><span :class="f.mode === 'full' ? 'badge-green' : 'badge-amber'">{{ f.mode === 'full' ? 'Lengkap' : 'Data saja' }}</span></td>
              <td class="num">{{ formatSize(f.size) }}</td>
              <td class="text-ink-400 text-xs">{{ f.modified }}</td>
              <td class="text-right">
                <div class="flex justify-end gap-2">
                  <a :href="route('backup.download', f.name)" class="btn-ghost btn-sm">Unduh</a>
                  <button @click="destroy(f)" class="btn-ghost btn-sm text-red-600">Hapus</button>
                </div>
              </td>
            </tr>
            <tr v-if="!files.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Belum ada backup database.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
