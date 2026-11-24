<script setup>
import { computed, reactive, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ExportButtons from '@/Components/ExportButtons.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ logs: Object, filters: Object, users: Array, actions: Array })

const f = reactive({ ...props.filters })
const open = ref(null)

function apply() {
  const q = {}
  for (const [k, v] of Object.entries(f)) if (v !== '' && v != null) q[k] = v
  router.get(route('activity-log.index'), q, { preserveState: true, replace: true })
}
function reset() {
  Object.keys(f).forEach((k) => { f[k] = '' })
  apply()
}
const exportParams = computed(() => ({ ...props.filters }))

const actionColor = (action) => {
  if (action.includes('deleted') || action.includes('rejected') || action.includes('failed') || action.includes('reset')) return 'badge-red'
  if (action.includes('created') || action.includes('approved') || action.includes('setup')) return 'badge-green'
  if (action.includes('updated') || action.includes('corrected') || action.includes('reviewed')) return 'badge-amber'
  return 'badge-slate'
}
const fmt = (v) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v))
</script>

<template>
  <AppLayout>
    <Head title="Log Aktivitas" />
    <template #title>Log Aktivitas (Audit Trail)</template>
    <template #actions><ExportButtons route-name="activity-log.export" :params="exportParams" /></template>

    <div class="card card-body flex flex-wrap items-end gap-3">
      <div>
        <label class="label">Aksi</label>
        <select v-model="f.action" @change="apply" class="input max-w-[220px]">
          <option value="">Semua aksi</option>
          <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>
      <div>
        <label class="label">Pengguna</label>
        <select v-model="f.user_id" @change="apply" class="input max-w-[200px]">
          <option value="">Semua</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div><label class="label">Dari</label><input v-model="f.from" type="date" @change="apply" class="input" /></div>
      <div><label class="label">Sampai</label><input v-model="f.to" type="date" @change="apply" class="input" /></div>
      <div>
        <label class="label">Cari keterangan</label>
        <input v-model="f.q" @change="apply" @keyup.enter="apply" class="input" placeholder="nama, NIP, email…" />
      </div>
      <button class="btn-ghost btn-sm" @click="reset">Reset</button>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th></th></tr></thead>
          <tbody>
            <template v-for="log in logs.data" :key="log.id">
              <tr>
                <td class="whitespace-nowrap font-mono text-xs text-ink-400">{{ log.created_at }}</td>
                <td class="font-medium">{{ log.user?.name || 'Sistem' }}</td>
                <td><span :class="actionColor(log.action)" class="font-mono">{{ log.action }}</span></td>
                <td class="max-w-md text-ink-600 dark:text-ink-300">{{ log.description }}</td>
                <td class="text-right"><button class="btn-ghost btn-sm" @click="open = open === log.id ? null : log.id">{{ open === log.id ? 'Tutup' : 'Detail' }}</button></td>
              </tr>
              <tr v-if="open === log.id">
                <td colspan="5" class="bg-ink-50 dark:bg-ink-800/60">
                  <div class="grid gap-1 text-xs">
                    <p><b>IP:</b> <span class="font-mono">{{ log.ip_address || '-' }}</span> · <b>Perangkat:</b> {{ log.user_agent || '-' }}</p>
                    <p v-if="log.subject_type"><b>Objek:</b> <span class="font-mono">{{ log.subject_type.split('\\').pop() }} #{{ log.subject_id }}</span></p>
                    <div v-if="log.properties && Object.keys(log.properties).length" class="mt-1">
                      <b>Perubahan:</b>
                      <table class="mt-1 w-full max-w-xl text-xs">
                        <tr v-for="(v, k) in log.properties" :key="k" class="border-b border-ink-200 dark:border-ink-700">
                          <td class="py-1 pr-3 font-mono text-ink-500">{{ k }}</td>
                          <td class="py-1 pr-3 text-red-600 line-through dark:text-red-400">{{ fmt(v.dari) }}</td>
                          <td class="py-1 text-emerald-700 dark:text-emerald-400">{{ fmt(v.ke) }}</td>
                        </tr>
                      </table>
                    </div>
                    <p v-else class="text-ink-400">Tidak ada rincian perubahan data.</p>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!logs.data.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Tidak ada aktivitas yang cocok.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="logs.links" /></div>
    </div>
  </AppLayout>
</template>
