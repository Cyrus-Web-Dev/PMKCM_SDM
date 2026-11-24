<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'

defineProps({ notifications: Object })

function readAll() {
  router.post(route('notifications.read-all'), {}, { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Notifikasi" />
    <template #title>Notifikasi</template>
    <template #actions><button class="btn-secondary btn-sm" @click="readAll">Tandai semua dibaca</button></template>

    <div class="card">
      <ul class="divide-y divide-ink-200 dark:divide-ink-700">
        <li v-for="n in notifications.data" :key="n.id">
          <Link :href="route('notifications.open', n.id)" class="block px-5 py-3 hover:bg-ink-50 dark:hover:bg-ink-800/60">
            <div class="flex items-start justify-between gap-3">
              <p :class="n.read ? 'text-ink-600 dark:text-ink-300' : 'font-semibold'"><span v-if="!n.read" class="mr-2 inline-block h-2 w-2 bg-seal-500"></span>{{ n.title }}</p>
              <span class="shrink-0 font-mono text-xs text-ink-400">{{ n.at }}</span>
            </div>
            <p v-if="n.body" class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ n.body }}</p>
          </Link>
        </li>
        <li v-if="!notifications.data.length" class="px-5 py-10 text-center text-sm text-ink-400">Belum ada notifikasi.</li>
      </ul>
      <div class="card-body"><Pagination :links="notifications.links" /></div>
    </div>
  </AppLayout>
</template>
