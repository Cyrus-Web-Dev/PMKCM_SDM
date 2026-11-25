<script setup>
import { computed, reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { fmtDate, WO_BADGE } from '@/utils'

const props = defineProps({ orders: Object, filters: Object, statuses: Object, canManage: Boolean, hasEmployee: Boolean })
const page = usePage()
const teams = computed(() => page.props.teams ?? [])

const f = reactive({ ...props.filters })
function apply() {
  const q = {}
  for (const [k, v] of Object.entries(f)) if (v !== '' && v !== null && v !== false) q[k] = v === true ? 1 : v
  router.get(route('work-orders.index'), q, { preserveState: true, replace: true })
}
function reset() {
  Object.keys(f).forEach((k) => { f[k] = k === 'mine' ? false : '' })
  apply()
}
</script>

<template>
  <AppLayout>
    <Head title="Perintah Kerja" />
    <template #title>Perintah Kerja Harian</template>
    <template #actions><Link v-if="canManage" :href="route('work-orders.create')" class="btn-accent btn-sm">+ Buat Perintah Kerja</Link></template>

    <div class="card card-body flex flex-wrap items-end gap-3">
      <div>
        <label class="label">Cari</label>
        <input v-model="f.q" class="input" placeholder="No., uraian, nama…" @change="apply" @keyup.enter="apply" />
      </div>
      <div>
        <label class="label">Status</label>
        <select v-model="f.status" class="input" @change="apply">
          <option value="">Semua</option>
          <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <div>
        <label class="label">Tim</label>
        <select v-model="f.team" class="input" @change="apply"><option value="">Semua tim</option><option v-for="t in teams" :key="t" :value="t">{{ t }}</option></select>
      </div>
      <div><label class="label">Dari</label><input v-model="f.from" type="date" class="input" @change="apply" /></div>
      <div><label class="label">Sampai</label><input v-model="f.to" type="date" class="input" @change="apply" /></div>
      <label v-if="hasEmployee" class="flex items-center gap-2 pb-2 text-sm"><input v-model="f.mine" type="checkbox" class="border-ink-300 text-seal-600 focus:ring-seal-500" @change="apply" /> Hanya milik saya</label>
      <button class="btn-ghost btn-sm" @click="reset">Reset</button>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>No. / Tanggal</th><th>Kepala Kerja</th><th>Anggota Kerja</th><th>Uraian Pekerjaan</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-for="o in orders.data" :key="o.id">
              <td class="whitespace-nowrap"><Link :href="route('work-orders.show', o.id)" class="font-mono font-semibold text-seal-600 hover:underline dark:text-seal-400">{{ o.number }}</Link><p class="text-xs text-ink-400">{{ fmtDate(o.order_date) }}</p></td>
              <td>{{ o.leader }}</td>
              <td class="max-w-[220px]"><span v-for="(m, i) in o.members" :key="m.id">{{ m.name }}<template v-if="i < o.members.length - 1">, </template></span></td>
              <td class="max-w-xs">{{ o.description }}<p v-if="o.team || o.area" class="text-xs text-ink-400">{{ [o.team, o.area].filter(Boolean).join(' · ') }}</p></td>
              <td><span :class="WO_BADGE[o.status]">{{ o.status_label }}</span></td>
              <td class="text-right"><Link :href="route('work-orders.show', o.id)" class="btn-ghost btn-sm">Detail</Link></td>
            </tr>
            <tr v-if="!orders.data.length"><td colspan="6" class="py-10 text-center text-sm text-ink-400">Belum ada perintah kerja.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="orders.links" /></div>
    </div>
  </AppLayout>
</template>
