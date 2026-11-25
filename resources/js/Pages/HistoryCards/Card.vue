<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Logo from '@/Components/Logo.vue'
import { Head, Link } from '@inertiajs/vue3'
import { fmtDate } from '@/utils'

defineProps({ card: Object })

function printCard() {
  window.print()
}
</script>

<template>
  <AppLayout>
    <Head :title="'History Card ' + card.number" />
    <template #title>History Card</template>
    <template #actions>
      <button class="btn-accent btn-sm" @click="printCard">Cetak</button>
      <a :href="route('history-cards.pdf', card.id)" class="btn-secondary btn-sm">Unduh PDF</a>
      <Link :href="route('history-cards.index')" class="btn-ghost btn-sm">&larr; Daftar</Link>
    </template>

    <div class="sheet">
      <div style="display:flex;align-items:center;gap:14px;border-bottom:4px solid #e0590f;padding-bottom:12px;margin-bottom:16px;color:#14181d">
        <Logo :size="46" />
        <div>
          <h2 style="margin:0;font-size:20px;letter-spacing:.08em">History Card</h2>
          <p style="margin:2px 0 0;color:#5d6672;font-size:13px">PMKCM V · Indarung 5 — {{ card.number }}</p>
        </div>
      </div>

      <table style="margin-bottom:16px">
        <tr><th style="width:28%">Uraian Pekerjaan</th><td>{{ card.description }}</td></tr>
        <tr><th>Tanggal Mulai</th><td>{{ fmtDate(card.start_date) }}</td></tr>
        <tr><th>Tanggal Selesai</th><td>{{ fmtDate(card.end_date) }}<span v-if="card.duration_days"> ({{ card.duration_days }} hari)</span></td></tr>
        <tr><th>Kepala Kerja</th><td>{{ card.leader }}</td></tr>
        <tr><th>Anggota Kerja</th><td><div v-for="(m, i) in card.members" :key="m.id">{{ i + 1 }}. {{ m.name }}</div></td></tr>
        <tr><th>Tim / Area</th><td>{{ card.team || '-' }} / {{ card.area || '-' }}</td></tr>
      </table>

      <table style="margin-top:28px;border:0">
        <tr>
          <td v-for="(who, i) in [['Kepala Kerja', card.leader], ['Ka. URS', '']]" :key="i" style="border:0;text-align:center;width:50%">
            {{ who[0] }}
            <div style="margin-top:64px;border-top:1px solid #111;padding-top:4px;font-weight:600">{{ who[1] || '(.........................)' }}</div>
          </td>
        </tr>
      </table>
    </div>
  </AppLayout>
</template>
