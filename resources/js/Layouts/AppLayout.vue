<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import UserAvatar from '@/Components/UserAvatar.vue'
import Logo from '@/Components/Logo.vue'
import OfflineBar from '@/Components/OfflineBar.vue'
import { initOffline } from '@/offline'

const page = usePage()
const isDark = ref(false)
const dismissedError = ref(false)
const tempDismissed = ref(false)
const tempPassword = computed(() => page.props.flash?.temp_password ?? null)
function copyTemp() {
  try { navigator.clipboard.writeText(tempPassword.value.password) } catch (e) { /* abaikan */ }
}

const user = computed(() => page.props.auth?.user ?? null)
const perms = computed(() => user.value?.permissions ?? [])
const has = (p) => perms.value.includes(p)
const isStaff = computed(() => !!user.value?.employee)

// Tab bawah (khusus layar kecil) untuk menu yang paling sering dipakai karyawan di lapangan.
const ICONS = {
  home: 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
  clock: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
  doc: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
  calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
  bell: 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
}
const tabs = computed(() => [
  { name: 'Beranda', href: route('dashboard'), icon: ICONS.home },
  { name: 'Absen', href: route('attendances.my-history'), icon: ICONS.clock },
  { name: 'Laporan', href: route('daily-reports.index'), icon: ICONS.doc },
  { name: 'Cuti', href: route('leaves.mine'), icon: ICONS.calendar },
  { name: 'Notif', href: route('notifications.index'), icon: ICONS.bell, badge: true },
])
function tabActive(href) {
  const path = new URL(href).pathname
  const url = page.url.split('?')[0]
  return url === path || url.startsWith(path + '/')
}

// Tombol "Pasang Aplikasi" (PWA) muncul bila browser menawarkan pemasangan.
const installEvent = ref(null)
function onBeforeInstall(e) {
  e.preventDefault()
  installEvent.value = e
}
async function installApp() {
  if (!installEvent.value) return
  installEvent.value.prompt()
  await installEvent.value.userChoice
  installEvent.value = null
}

const bannerMessages = computed(() => {
  const msgs = []
  if (page.props.flash?.error) msgs.push(page.props.flash.error)
  return msgs
})

// Auto-logout bila tidak ada aktivitas (batas diatur Owner di menu Pengaturan; 0 = nonaktif).
let idleTimer = null
let lastReset = 0
function resetIdle() {
  const now = Date.now()
  if (now - lastReset < 5000 && idleTimer) return
  lastReset = now
  clearTimeout(idleTimer)
  const mins = Number(page.props.idle_timeout) || 0
  if (!mins) return
  idleTimer = setTimeout(() => {
    router.post(route('logout'), {}, { onError: () => window.location.assign(route('login')) })
  }, mins * 60 * 1000)
}
// Segarkan jumlah notifikasi belum dibaca tiap 60 detik (hanya saat tab terlihat).
let pollTimer = null
function pollNotifications() {
  if (document.visibilityState === 'visible' && navigator.onLine) router.reload({ only: ['notifications_unread'], preserveScroll: true, preserveState: true })
}
const IDLE_EVENTS = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll']

// Data yang baru disinkronkan dari antrean offline -> muat ulang halaman supaya tampil.
const onSynced = () => router.reload()

onMounted(() => {
  initOffline()
  window.addEventListener('sdm-synced', onSynced)
  window.addEventListener('beforeinstallprompt', onBeforeInstall)
  window.addEventListener('appinstalled', () => { installEvent.value = null })
  IDLE_EVENTS.forEach((e) => window.addEventListener(e, resetIdle, { passive: true }))
  resetIdle()
  pollTimer = setInterval(pollNotifications, 60000)
  try { isDark.value = localStorage.getItem('sdm-theme') === 'dark' } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
})
onBeforeUnmount(() => {
  clearTimeout(idleTimer)
  clearInterval(pollTimer)
  window.removeEventListener('sdm-synced', onSynced)
  window.removeEventListener('beforeinstallprompt', onBeforeInstall)
  IDLE_EVENTS.forEach((e) => window.removeEventListener(e, resetIdle))
})

function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('sdm-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
}

/**
 * Menu dibagi per kelompok (dipisah garis tipis di bar navigasi):
 *  1) Dashboard   2) Layanan saya (butuh data karyawan)   3) Data & laporan   4) Administrasi
 */
const groups = computed(() => [
  [
    { name: 'Dashboard', href: route('dashboard'), show: true },
  ],
  [
    { name: 'Laporan Harian', href: route('daily-reports.index'), show: isStaff.value },
    { name: 'Absen Saya', href: route('attendances.my-history'), show: isStaff.value },
    { name: 'Cuti Saya', href: route('leaves.mine'), show: isStaff.value },
    { name: 'Nilai Saya', href: route('assessments.mine'), show: isStaff.value },
  ],
  [
    { name: 'Semua Laporan', href: route('daily-reports.all'), show: has('daily_reports.view') },
    { name: 'Rekap', href: route('recap.index'), show: has('reports.view') },
    { name: 'Penilaian', href: route('assessments.index'), show: has('performance.view') },
    { name: 'Form Penilaian', href: route('assessments.sheet'), show: has('performance.view') || isStaff.value },
    { name: 'Karyawan', href: route('employees.index'), show: has('employees.view') },
    { name: 'Departemen', href: route('departments.index'), show: has('departments.view') },
    { name: 'Absensi', href: route('attendances.index'), show: has('attendances.view') },
    { name: 'Cuti & Izin', href: route('leaves.index'), show: has('leaves.view') },
    { name: 'Hari Libur', href: route('holidays.index'), show: has('holidays.view') },
    { name: 'Shift', href: route('shifts.index'), show: has('shifts.manage') },
  ],
  [
    { name: 'Kelola Pengguna', href: route('users.index'), show: has('users.manage') || has('users.view'), badge: 'pending' },
    { name: 'Pengaturan', href: route('settings.index'), show: has('settings.manage') },
    { name: 'Log Aktivitas', href: route('activity-log.index'), show: has('settings.manage') },
    { name: 'Backup Data', href: route('backup.index'), show: has('settings.manage') },
  ],
].map((g) => g.filter((i) => i.show)).filter((g) => g.length))

/** Menu aktif = yang path-nya paling spesifik (terpanjang) cocok dengan URL, supaya /daily-reports dan /daily-reports/all tidak aktif bersamaan. */
const activeHref = computed(() => {
  const url = page.url.split('?')[0]
  let best = null
  let bestLen = -1
  for (const item of groups.value.flat()) {
    const path = new URL(item.href).pathname
    if ((url === path || url.startsWith(path + '/')) && path.length > bestLen) {
      best = item.href
      bestLen = path.length
    }
  }
  return best
})
</script>

<template>
  <div class="min-h-screen">
    <div class="hazard noprint"></div>

    <header class="noprint sticky top-0 z-30 border-b border-ink-200 bg-white pt-[env(safe-area-inset-top)] dark:border-ink-700 dark:bg-ink-900">
      <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2.5">
        <Link :href="route('dashboard')" class="flex items-center gap-3 text-ink-900 dark:text-ink-100">
          <Logo :size="38" />
          <div>
            <b class="block font-serif text-2xl uppercase leading-none tracking-wider">PMKCM V</b>
            <small class="text-[11px] font-semibold tracking-[.32em] text-seal-500">INDARUNG 5 · SDM</small>
          </div>
        </Link>

        <div class="ml-auto flex items-center gap-3 lg:order-3">
          <button v-if="installEvent" type="button" class="btn-secondary btn-sm" @click="installApp">Pasang Aplikasi</button>
          <Link :href="route('notifications.index')" class="relative hidden p-2 lg:block text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" title="Notifikasi" aria-label="Notifikasi">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
            <span v-if="$page.props.notifications_unread > 0" class="absolute -right-0.5 -top-0.5 bg-seal-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $page.props.notifications_unread > 99 ? '99+' : $page.props.notifications_unread }}</span>
          </Link>
          <button type="button" @click="toggleTheme" class="p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" title="Ganti tema" aria-label="Ganti tema">
            <svg v-if="!isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </button>
          <Link :href="route('profile.edit')" class="flex items-center gap-2.5" title="Buka profil saya">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="34" />
            <div class="hidden text-right text-[13px] leading-tight text-ink-500 dark:text-ink-400 sm:block">
              <b class="block text-ink-900 dark:text-ink-100">{{ user?.name }}</b>
              {{ user?.employee?.position_title || (user?.roles ?? [])[0] }}
            </div>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="btn-secondary btn-sm">Keluar</Link>
        </div>

        <nav class="flex w-full items-center gap-1 overflow-x-auto lg:order-2 lg:w-auto lg:flex-1" aria-label="Menu utama">
          <template v-for="(group, gi) in groups" :key="gi">
            <span v-if="gi > 0" class="mx-1 h-5 w-px shrink-0 bg-ink-200 dark:bg-ink-700" aria-hidden="true"></span>
            <Link
              v-for="item in group" :key="item.name" :href="item.href"
              class="relative whitespace-nowrap border-b-[3px] px-3 py-2.5 font-serif text-[13px] font-semibold uppercase tracking-wider transition-colors"
              :class="activeHref === item.href
                ? 'border-seal-500 text-ink-900 dark:text-ink-100'
                : 'border-transparent text-ink-500 hover:bg-ink-50 hover:text-ink-900 dark:text-ink-400 dark:hover:bg-ink-800 dark:hover:text-ink-100'"
            >
              {{ item.name }}
              <span v-if="item.badge === 'pending' && ($page.props.pending_users + $page.props.pending_resets) > 0" class="ml-1 bg-seal-500 px-1.5 py-0.5 text-[10px] font-bold text-white" title="Pendaftar / permintaan reset password menunggu">{{ $page.props.pending_users + $page.props.pending_resets }}</span>
            </Link>
          </template>
        </nav>
      </div>
    </header>

    <main class="page-in mx-auto max-w-[1280px] space-y-5 px-4 pb-24 pt-6 lg:pb-16">
      <OfflineBar />

      <div v-if="tempPassword && !tempDismissed" role="alert" class="noprint border-l-4 border-seal-500 bg-white p-4 text-sm dark:bg-ink-800">
        <p class="font-semibold">Password sementara untuk {{ tempPassword.name }} — tampil SEKALI, catat dan berikan langsung ke yang bersangkutan.</p>
        <div class="mt-2 flex flex-wrap items-center gap-3">
          <span class="font-mono text-ink-500">{{ tempPassword.email }}</span>
          <b class="select-all bg-ink-100 px-2 py-1 font-mono text-base dark:bg-ink-900">{{ tempPassword.password }}</b>
          <button type="button" class="btn-secondary btn-sm" @click="copyTemp">Salin</button>
          <button type="button" class="btn-ghost btn-sm" @click="tempDismissed = true">Tutup</button>
        </div>
        <p class="mt-2 text-xs text-ink-500">Karyawan wajib mengganti password ini saat login pertama.</p>
      </div>
      <div v-if="$page.props.flash?.success" role="status" class="noprint border-l-4 border-emerald-600 bg-white px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-ink-800 dark:text-emerald-300">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="bannerMessages.length && !dismissedError" role="alert" class="noprint flex items-start justify-between gap-3 border-l-4 border-red-600 bg-white px-4 py-3 text-sm text-red-800 dark:bg-ink-800 dark:text-red-300">
        <ul class="space-y-1">
          <li v-for="m in bannerMessages" :key="m">{{ m }}</li>
        </ul>
        <button type="button" class="shrink-0 font-bold opacity-60 hover:opacity-100" title="Tutup" @click="dismissedError = true">&times;</button>
      </div>

      <div class="noprint flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-[26px] font-semibold text-ink-900 dark:text-ink-100"><slot name="title">Dashboard</slot></h1>
        <div class="flex items-center gap-2"><slot name="actions" /></div>
      </div>

      <slot />
    </main>

    <nav v-if="isStaff" class="noprint fixed inset-x-0 bottom-0 z-30 border-t border-ink-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-ink-700 dark:bg-ink-900 lg:hidden" aria-label="Menu cepat">
      <div class="grid grid-cols-5">
        <Link v-for="t in tabs" :key="t.name" :href="t.href" class="relative flex flex-col items-center gap-0.5 py-2 text-[10px] font-semibold uppercase tracking-wide" :class="tabActive(t.href) ? 'text-seal-600 dark:text-seal-400' : 'text-ink-500 dark:text-ink-400'">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" :d="t.icon" /></svg>
          {{ t.name }}
          <span v-if="t.badge && $page.props.notifications_unread > 0" class="absolute right-[22%] top-1 bg-seal-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $page.props.notifications_unread > 99 ? '99+' : $page.props.notifications_unread }}</span>
        </Link>
      </div>
    </nav>
  </div>
</template>
