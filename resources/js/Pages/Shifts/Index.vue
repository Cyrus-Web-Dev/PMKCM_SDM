<script setup>
import { reactive, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({ shifts: Array })

// Salinan yang bisa diedit per baris; disegarkan setiap data dari server berubah.
const rows = reactive(props.shifts.map((s) => ({ ...s })))
watch(() => props.shifts, (v) => rows.splice(0, rows.length, ...v.map((s) => ({ ...s }))))

const onError = (e) => alert(Object.values(e)[0] || 'Gagal menyimpan shift.')

function save(r) {
  router.put(route('shifts.update', r.id), { name: r.name, start_time: r.start_time, end_time: r.end_time }, { preserveScroll: true, onError })
}
function remove(r) {
  if (confirm(`Hapus ${r.name}? Laporan lama tetap utuh.`)) router.delete(route('shifts.destroy', r.id), { preserveScroll: true })
}

const form = useForm({ name: '', start_time: '07:00', end_time: '15:00' })
function add() {
  form.post(route('shifts.store'), { preserveScroll: true, onSuccess: () => form.reset('name') })
}
</script>

<template>
  <AppLayout>
    <Head title="Shift" />
    <template #title>Shift Kerja</template>

    <div class="grid items-start gap-5 lg:grid-cols-[1fr_340px]">
      <div class="card">
        <div class="card-header"><h3 class="font-serif font-semibold">Daftar Shift</h3></div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Nama</th><th>Mulai</th><th>Selesai</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
              <tr v-for="r in rows" :key="r.id">
                <td><input v-model="r.name" class="input min-w-[140px]" /></td>
                <td><input v-model="r.start_time" type="time" class="input" /></td>
                <td><input v-model="r.end_time" type="time" class="input" /></td>
                <td class="text-right">
                  <div class="flex justify-end gap-2">
                    <button @click="save(r)" class="btn-accent btn-sm">Simpan</button>
                    <button @click="remove(r)" class="btn-danger btn-sm">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!rows.length"><td colspan="4" class="py-10 text-center text-sm text-ink-400">Belum ada shift.</td></tr>
            </tbody>
          </table>
        </div>
        <p class="card-body pt-0 text-xs text-ink-400">Shift yang jam selesainya lebih kecil dari jam mulai dianggap melewati tengah malam (mis. 23:00 – 07:00).</p>
      </div>

      <form @submit.prevent="add" class="card card-body space-y-3">
        <h3 class="font-serif font-semibold">Tambah Shift</h3>
        <div>
          <label class="label">Nama</label>
          <input v-model="form.name" class="input" placeholder="mis. Shift 4" required />
          <p v-if="form.errors.name" class="error-text">{{ form.errors.name }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Mulai</label><input v-model="form.start_time" type="time" class="input" required /></div>
          <div><label class="label">Selesai</label><input v-model="form.end_time" type="time" class="input" required /></div>
        </div>
        <button type="submit" :disabled="form.processing" class="btn-accent">Tambah</button>
      </form>
    </div>
  </AppLayout>
</template>
