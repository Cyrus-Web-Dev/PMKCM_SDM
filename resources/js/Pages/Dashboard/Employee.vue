<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { fmtDate } from '@/utils'

defineProps({ employee: Object, today_reports: Number, work_orders_active: Number, latest_assessment: Object })
const page = usePage()
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <template #title>Halo, {{ employee?.full_name || page.props.auth.user.name }}</template>

    <div v-if="!employee" class="card card-body text-sm text-ink-600 dark:text-ink-300">
      Akunmu belum dihubungkan ke data karyawan, sehingga laporan harian dan perintah kerja belum bisa dipakai. Hubungi Owner.
    </div>

    <template v-else>
      <p class="text-sm text-ink-500 dark:text-ink-400">
        {{ employee.position || '—' }} · {{ employee.department || '—' }} · bergabung {{ fmtDate(employee.join_date) }}
      </p>

      <div class="grid gap-4 md:grid-cols-3">
        <Link :href="route('daily-reports.index')" class="card card-body block">
          <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Laporan Hari Ini</p>
          <p class="mt-2 font-mono text-4xl font-bold text-ink-900 dark:text-ink-50">{{ today_reports }}</p>
          <p class="mt-1 text-xs text-seal-600 dark:text-seal-400">Isi laporan harian &rarr;</p>
        </Link>

        <Link :href="route('work-orders.index', { mine: 1 })" class="card card-body block">
          <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Perintah Kerja Aktif</p>
          <p class="mt-2 font-mono text-4xl font-bold text-ink-900 dark:text-ink-50">{{ work_orders_active }}</p>
          <p class="mt-1 text-xs text-seal-600 dark:text-seal-400">Lihat perintah kerja &rarr;</p>
        </Link>

        <Link :href="route('assessments.mine')" class="card card-body block">
          <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Penilaian Terakhir</p>
          <template v-if="latest_assessment">
            <p class="mt-2 font-mono text-4xl font-bold text-ink-900 dark:text-ink-50">{{ latest_assessment.total }}</p>
            <p class="mt-1 text-xs text-ink-500">{{ latest_assessment.grade }} · {{ latest_assessment.period_label }}</p>
          </template>
          <p v-else class="mt-2 text-sm text-ink-400">Belum ada penilaian.</p>
        </Link>
      </div>
    </template>
  </AppLayout>
</template>
