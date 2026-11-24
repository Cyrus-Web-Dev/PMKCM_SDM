<script setup>
import { computed, reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Logo from '@/Components/Logo.vue'
import { Head, router } from '@inertiajs/vue3'
import { MONTHS, mailto } from '@/utils'

const props = defineProps({
  employees: Array, employee: Object, month: Number, year: Number, period_label: String,
  assessment: Object, assessor: String, stat: Object, owner: String, criteria: Array,
  years: Array, canPickEmployee: Boolean, canEmail: Boolean,
})

const f = reactive({ employee_id: props.employee?.id ?? '', month: props.month, year: props.year })
function apply() {
  router.get(route('assessments.sheet'), { employee_id: f.employee_id || undefined, month: f.month, year: f.year }, { preserveState: true, preserveScroll: true, replace: true })
}

function printSheet() {
  window.print()
}

const pdfHref = computed(() => route('assessments.sheet.pdf', { employee_id: props.employee?.id, month: props.month, year: props.year }))
const emailHref = computed(() => {
  const a = props.assessment
  if (!a || !props.employee?.email) return null
  return mailto(props.employee.email, `Hasil Penilaian Kinerja ${props.period_label}`, [
    `Halo ${props.employee.full_name},`, '',
    `Berikut hasil penilaian kinerja Anda untuk periode ${props.period_label}:`,
    ...props.criteria.map((c) => `- ${c.label}: ${a.scores[c.key]}`),
    `Nilai akhir: ${a.total} (${a.grade})`,
    ...(a.note ? ['', `Catatan: ${a.note}`] : []), '',
    'Terima kasih.',
  ])
})
</script>

<template>
  <AppLayout>
    <Head title="Form Penilaian" />
    <template #title>Form Penilaian</template>
    <template #actions>
      <button v-if="assessment" @click="printSheet" class="btn-accent btn-sm">Cetak</button>
      <a v-if="assessment" :href="pdfHref" class="btn-secondary btn-sm">Unduh PDF</a>
      <a v-if="canEmail && emailHref" :href="emailHref" class="btn-secondary btn-sm">Email</a>
    </template>

    <div class="noprint card card-body flex flex-wrap items-end gap-3">
      <div v-if="canPickEmployee">
        <label class="label">Karyawan</label>
        <select v-model="f.employee_id" @change="apply" class="input min-w-[220px]">
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
        </select>
      </div>
      <div>
        <label class="label">Bulan</label>
        <select v-model="f.month" @change="apply" class="input"><option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option></select>
      </div>
      <div>
        <label class="label">Tahun</label>
        <select v-model="f.year" @change="apply" class="input"><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
      </div>
    </div>

    <div v-if="!employee" class="card card-body text-sm text-ink-500">Tidak ada karyawan yang bisa ditampilkan.</div>
    <div v-else-if="!assessment" class="card card-body text-sm text-ink-500">Belum ada penilaian untuk {{ employee.full_name }} pada {{ period_label }}.</div>

    <div v-else class="sheet">
      <div style="display:flex;align-items:center;gap:14px;border-bottom:4px solid #e0590f;padding-bottom:12px;margin-bottom:16px;color:#14181d">
        <Logo :size="46" />
        <div>
          <h2 style="margin:0;font-size:20px;letter-spacing:.08em">Form Penilaian Outsourcing</h2>
          <p style="margin:2px 0 0;color:#5d6672;font-size:13px">PMKCM V · Indarung 5 — Periode {{ period_label }}</p>
        </div>
      </div>

      <table style="margin-bottom:16px">
        <tr><th style="width:26%">Nama</th><td>{{ employee.full_name }}</td></tr>
        <tr><th>NIP</th><td>{{ employee.nip }}</td></tr>
        <tr><th>Jabatan</th><td>{{ employee.position || '-' }}</td></tr>
        <tr><th>Tim</th><td>{{ employee.team || '-' }}</td></tr>
      </table>

      <table style="margin-bottom:16px">
        <tr><th style="width:8%">No</th><th>Kriteria</th><th style="width:20%">Nilai (0-100)</th></tr>
        <tr v-for="(c, i) in criteria" :key="c.key"><td>{{ i + 1 }}</td><td>{{ c.label }}</td><td>{{ assessment.scores[c.key] }}</td></tr>
        <tr><th colspan="2">Nilai Akhir (rata-rata)</th><th>{{ assessment.total }}</th></tr>
        <tr><th colspan="2">Predikat</th><th>{{ assessment.grade }}</th></tr>
      </table>

      <table style="margin-bottom:16px">
        <tr><th colspan="2">Ringkasan Laporan Harian Periode Ini</th></tr>
        <tr><td style="width:60%">Jumlah laporan</td><td>{{ stat.reports }}</td></tr>
        <tr><td>Pekerjaan selesai</td><td>{{ stat.done }}</td></tr>
        <tr><td>Total jam kerja</td><td>{{ stat.hours }} jam</td></tr>
      </table>

      <p style="color:#111"><b>Catatan penilai:</b><br />{{ assessment.note || '-' }}</p>

      <table style="margin-top:28px;border:0">
        <tr>
          <td v-for="(who, i) in [['Karyawan', employee.full_name], ['Ka. URS', assessor], ['Owner', owner]]" :key="i" style="border:0;text-align:center;width:33%">
            {{ who[0] }}
            <div style="margin-top:64px;border-top:1px solid #111;padding-top:4px;font-weight:600">{{ who[1] || '(.........................)' }}</div>
          </td>
        </tr>
      </table>
    </div>
  </AppLayout>
</template>
