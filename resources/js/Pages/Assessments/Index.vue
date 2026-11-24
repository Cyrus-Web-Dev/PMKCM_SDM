<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ExportButtons from '@/Components/ExportButtons.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { MONTHS, gradeOf, gradeBadge, mailto } from '@/utils'

const props = defineProps({
  employees: Array, assessments: Object, criteria: Array, years: Array, filters: Object,
  canManage: Boolean, canEmail: Boolean, now: Object,
})
const page = usePage()
const teams = computed(() => page.props.teams ?? [])

// ---------- formulir penilaian ----------
const form = useForm({
  employee_id: '', month: props.now.month, year: props.now.year,
  quality: 80, discipline: 80, teamwork: 80, initiative: 80, safety: 80, note: '',
})
const total = computed(() => Math.round(props.criteria.reduce((a, c) => a + Number(form[c.key] || 0), 0) / props.criteria.length))

function submit() {
  form.post(route('assessments.store'), { preserveScroll: true, onSuccess: () => form.reset('employee_id', 'note') })
}

// ---------- filter riwayat ----------
const fy = ref(props.filters.year ?? '')
const fm = ref(props.filters.month ?? '')
const ft = ref(props.filters.team ?? '')
function applyFilter() {
  router.get(route('assessments.index'), { year: fy.value || undefined, month: fm.value || undefined, team: ft.value || undefined }, { preserveState: true, preserveScroll: true, replace: true })
}
const exportParams = computed(() => ({ year: props.filters.year, month: props.filters.month, team: props.filters.team }))

// ---------- aksi baris ----------
function sheetHref(a) {
  const [y, m] = a.period.split('-')
  return route('assessments.sheet', { employee_id: a.employee_id, month: Number(m), year: Number(y) })
}
function emailHref(a) {
  return mailto(a.email, `Hasil Penilaian Kinerja ${a.period_label}`, [
    `Halo ${a.employee},`, '',
    `Berikut hasil penilaian kinerja Anda untuk periode ${a.period_label}:`,
    ...props.criteria.map((c) => `- ${c.label}: ${a.scores[c.key]}`),
    `Nilai akhir: ${a.total} (${a.grade})`,
    ...(a.note ? ['', `Catatan: ${a.note}`] : []), '',
    'Terima kasih.',
  ])
}
function remove(a) {
  if (confirm(`Hapus penilaian ${a.employee} periode ${a.period_label}?`)) router.delete(route('assessments.destroy', a.id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Penilaian" />
    <template #title>Penilaian Outsourcing</template>
    <template #actions><ExportButtons route-name="assessments.export" :params="exportParams" /></template>

    <div class="grid items-start gap-5" :class="canManage ? 'lg:grid-cols-[400px_1fr]' : ''">
      <form v-if="canManage" @submit.prevent="submit" class="card card-body space-y-3">
        <h3 class="font-serif font-semibold">Input Penilaian</h3>
        <div>
          <label class="label">Karyawan</label>
          <select v-model="form.employee_id" class="input" required>
            <option value="" disabled>Pilih karyawan…</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}{{ e.team ? ' · ' + e.team : '' }}</option>
          </select>
          <p v-if="form.errors.employee_id" class="error-text">{{ form.errors.employee_id }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">Bulan</label>
            <select v-model="form.month" class="input"><option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option></select>
          </div>
          <div>
            <label class="label">Tahun</label>
            <select v-model="form.year" class="input"><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
          </div>
        </div>

        <div v-for="c in criteria" :key="c.key">
          <div class="flex items-center justify-between">
            <label class="label !mb-0">{{ c.label }}</label>
            <input v-model.number="form[c.key]" type="number" min="0" max="100" class="input !w-20 text-center font-mono" />
          </div>
          <input v-model.number="form[c.key]" type="range" min="0" max="100" class="mt-1 w-full accent-seal-500" />
          <p v-if="form.errors[c.key]" class="error-text">{{ form.errors[c.key] }}</p>
        </div>

        <div class="flex items-center justify-between border-t border-ink-200 pt-3 dark:border-ink-700">
          <span class="text-sm text-ink-500">Nilai akhir</span>
          <span class="flex items-center gap-2"><b class="font-mono text-2xl">{{ total }}</b><span :class="gradeBadge(total)">{{ gradeOf(total) }}</span></span>
        </div>
        <div>
          <label class="label">Catatan</label>
          <textarea v-model="form.note" rows="2" class="input"></textarea>
        </div>
        <button type="submit" :disabled="form.processing" class="btn-accent">{{ form.processing ? 'Menyimpan…' : 'Simpan Penilaian' }}</button>
        <p class="text-xs text-ink-400">Menyimpan ulang untuk karyawan dan bulan yang sama akan memperbarui nilai sebelumnya.</p>
      </form>

      <div class="card">
        <div class="card-header flex-wrap">
          <h3 class="font-serif font-semibold">Riwayat Penilaian</h3>
          <div class="flex flex-wrap gap-2">
            <select v-model="fm" @change="applyFilter" class="input !w-auto"><option value="">Semua bulan</option><option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option></select>
            <select v-model="fy" @change="applyFilter" class="input !w-auto"><option value="">Semua tahun</option><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
            <select v-model="ft" @change="applyFilter" class="input !w-auto"><option value="">Semua tim</option><option v-for="t in teams" :key="t" :value="t">{{ t }}</option></select>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Periode</th><th>Karyawan</th><th v-for="c in criteria" :key="c.key" class="text-right" :title="c.label">{{ c.label.split(' ')[0] }}</th><th>Nilai Akhir</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
              <tr v-for="a in assessments.data" :key="a.id">
                <td class="whitespace-nowrap">{{ a.period_label }}</td>
                <td>{{ a.employee || '—' }}<p class="text-xs text-ink-400">{{ a.team || '—' }}<template v-if="a.assessor"> · dinilai {{ a.assessor }}</template></p></td>
                <td v-for="c in criteria" :key="c.key" class="num text-right">{{ a.scores[c.key] }}</td>
                <td><span :class="gradeBadge(a.total)">{{ a.total }} · {{ a.grade }}</span></td>
                <td class="text-right">
                  <div class="flex justify-end gap-1">
                    <Link :href="sheetHref(a)" class="btn-ghost btn-sm">Form</Link>
                    <a v-if="canEmail && a.email" :href="emailHref(a)" class="btn-ghost btn-sm">Email</a>
                    <button v-if="canManage" @click="remove(a)" class="btn-ghost btn-sm text-red-600">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!assessments.data.length"><td :colspan="criteria.length + 4" class="py-10 text-center text-sm text-ink-400">Belum ada penilaian.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-body"><Pagination :links="assessments.links" /></div>
      </div>
    </div>
  </AppLayout>
</template>
