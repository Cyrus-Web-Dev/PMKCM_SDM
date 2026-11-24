<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({ values: Object })

const form = useForm({
  late_tolerance_minutes: props.values.late_tolerance_minutes,
  idle_timeout_minutes: props.values.idle_timeout_minutes,
})

function submit() {
  form.put(route('settings.update'), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Pengaturan" />
    <template #title>Pengaturan</template>

    <form @submit.prevent="submit" class="card card-body max-w-xl space-y-5">
      <div>
        <label class="label">Toleransi keterlambatan (menit)</label>
        <input v-model.number="form.late_tolerance_minutes" type="number" min="0" max="120" class="input max-w-[160px] font-mono" required />
        <p class="mt-1 text-xs text-ink-400">Check-in sampai sekian menit setelah jam mulai shift tetap dihitung <b>Tepat Waktu</b>. Lewat dari itu menjadi <b>Terlambat</b>.</p>
        <p v-if="form.errors.late_tolerance_minutes" class="error-text">{{ form.errors.late_tolerance_minutes }}</p>
      </div>
      <div>
        <label class="label">Auto-logout saat tidak aktif (menit)</label>
        <input v-model.number="form.idle_timeout_minutes" type="number" min="0" max="480" class="input max-w-[160px] font-mono" required />
        <p class="mt-1 text-xs text-ink-400">Pengguna otomatis keluar bila tidak ada aktivitas selama waktu ini. Isi <b>0</b> untuk menonaktifkan. Cocok untuk komputer bersama di area pabrik.</p>
        <p v-if="form.errors.idle_timeout_minutes" class="error-text">{{ form.errors.idle_timeout_minutes }}</p>
      </div>
      <button type="submit" :disabled="form.processing" class="btn-accent self-start">{{ form.processing ? 'Menyimpan…' : 'Simpan Pengaturan' }}</button>
    </form>
  </AppLayout>
</template>
