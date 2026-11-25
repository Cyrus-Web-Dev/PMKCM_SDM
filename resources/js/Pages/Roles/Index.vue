<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

defineProps({ roles: Array })

function remove(r) {
  if (confirm(`Hapus role "${r.name}"?`)) router.delete(route('roles.destroy', r.id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Role & Hak Akses" />
    <template #title>Role &amp; Hak Akses</template>
    <template #actions><Link :href="route('roles.create')" class="btn-accent btn-sm">+ Role Baru</Link></template>

    <p class="text-sm text-ink-500 dark:text-ink-400">
      Buat role sesuai kebutuhan (mis. <b>KA Unit</b> boleh kelola data, <b>KA Gudang</b> hanya melihat), lalu tentukan hak aksesnya dengan centang.
      Role dipasang ke pengguna di menu <b>Kelola Pengguna</b>.
    </p>

    <div class="grid gap-4 lg:grid-cols-2">
      <div v-for="r in roles" :key="r.id" class="card card-body space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h3 class="font-serif text-lg font-semibold">{{ r.name }}</h3>
            <p class="text-xs text-ink-400">{{ r.users }} pengguna<span v-if="r.owner"> · Owner</span><span v-else-if="r.protected"> · role sistem</span></p>
          </div>
          <div v-if="!r.owner" class="flex gap-2">
            <Link :href="route('roles.edit', r.id)" class="btn-secondary btn-sm">Ubah</Link>
            <button v-if="!r.protected" class="btn-danger btn-sm" @click="remove(r)">Hapus</button>
          </div>
        </div>

        <p v-if="r.owner" class="text-sm text-ink-600 dark:text-ink-300">Akses penuh ke semua fitur. Tidak bisa diubah atau dihapus.</p>
        <ul v-else-if="r.access.length" class="space-y-1 text-sm">
          <li v-for="a in r.access" :key="a.group" class="flex justify-between gap-3 border-b border-ink-100 pb-1 dark:border-ink-800">
            <span>{{ a.group }}</span><b class="shrink-0 text-seal-600 dark:text-seal-400">{{ a.levels }}</b>
          </li>
        </ul>
        <p v-else class="text-sm text-ink-400">Tanpa hak akses khusus: hanya halaman pribadi (laporan harian, perintah kerja saya, nilai saya).</p>
      </div>
    </div>
  </AppLayout>
</template>
