<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ employee: Object })
const page = usePage()
const canManage = () => (page.props.auth.user.permissions ?? []).includes('employees.manage')

function fmt(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <AppLayout>
    <Head :title="employee.full_name" />
    <template #title>Detail Karyawan</template>
    <template #actions>
      <Link v-if="canManage()" :href="route('employees.edit', employee.id)" class="btn-accent btn-sm">Ubah Data</Link>
    </template>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-1">
        <div class="card-body text-center">
          <UserAvatar :src="employee.photo_url" :name="employee.full_name" :size="88" class="mx-auto" />
          <h2 class="mt-4 font-serif text-lg font-semibold text-ink-900 dark:text-ink-100">{{ employee.full_name }}</h2>
          <p class="text-sm text-ink-500">{{ employee.position?.title || 'Jabatan belum diatur' }}</p>
          <p class="mt-1 text-xs font-mono text-ink-400">{{ employee.nip }}</p>
          <div class="mt-4 flex justify-center gap-2">
            <span class="badge-seal">{{ employee.department?.name || 'Tanpa Departemen' }}</span>
          </div>
        </div>
        <div class="card-header !border-t !border-b-0"><h3 class="text-sm font-semibold text-ink-700 dark:text-ink-300">Kontak</h3></div>
        <div class="card-body space-y-2 text-sm">
          <p><span class="text-ink-400">No. HP:</span> {{ employee.phone || '-' }}</p>
          <p><span class="text-ink-400">Email:</span> {{ employee.personal_email || '-' }}</p>
          <p><span class="text-ink-400">Alamat:</span> {{ employee.address || '-' }}</p>
          <p><span class="text-ink-400">Kontak Darurat:</span> {{ employee.emergency_contact_name || '-' }} ({{ employee.emergency_contact_phone || '-' }})</p>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-6">
        <div class="card">
          <div class="card-header"><h3 class="font-serif font-semibold">Kepegawaian</h3></div>
          <div class="card-body grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div><p class="text-ink-400">Tipe</p><p class="font-medium">{{ employee.employment_type_label }}</p></div>
            <div><p class="text-ink-400">Status</p><p class="font-medium">{{ employee.employment_status_label }}</p></div>
            <div><p class="text-ink-400">Bergabung</p><p class="font-medium num">{{ fmt(employee.join_date) }}</p></div>
            <div><p class="text-ink-400">Tim Kerja</p><p class="font-medium">{{ employee.team || '-' }}</p></div>
            <div><p class="text-ink-400">Atasan</p><p class="font-medium">{{ employee.supervisor?.full_name || '-' }}</p></div>
            <div><p class="text-ink-400">Status Pernikahan</p><p class="font-medium">{{ employee.marital_status }}</p></div>
            <div><p class="text-ink-400">Tanggungan</p><p class="font-medium">{{ employee.dependents_count }}</p></div>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
