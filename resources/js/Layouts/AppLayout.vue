<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
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
const isOwner = computed(() => (user.value?.roles ?? []).includes('super-admin'))

// Tab bawah (khusus layar kecil) untuk menu yang paling sering dipakai karyawan di lapangan.
const ICONS = {
  home: 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
  doc: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
  clipboard: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
  star: 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z',
}
const tabs = computed(() => [
  { name: 'Beranda', href: route('dashboard'), icon: ICONS.home },
  { name: 'Laporan', href: route('daily-reports.index'), icon: ICONS.doc },
  { name: 'Perintah', href: route('work-orders.index'), icon: ICONS.clipboard },
  { name: 'Nilai', href: route('assessments.mine'), icon: ICONS.star },
])
function tabActive(href) {
  const path = new URL(href).pathname
  const url = page.url.split('?')[0]
  return url === path || url.startsWith(path + '/')
}

// Menu samping (tombol garis tiga di kiri atas): daftar semua halaman, ditutup saat pindah halaman / tombol Esc.
const menuOpen = ref(false)
const closeMenu = () => { menuOpen.value = false }
const onKey = (e) => { if (e.key === 'Escape') closeMenu() }
watch(menuOpen, (open) => { document.body.style.overflow = open ? 'hidden' : '' })
let offNavigate = null

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

// Auto-logout bila tidak ada aktivitas (batas lewat SDM_IDLE_TIMEOUT_MINUTES di .env; 0 = nonaktif).
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
  offNavigate = router.on('navigate', closeMenu)
  window.addEventListener('keydown', onKey)
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
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('beforeinstallprompt', onBeforeInstall)
  if (offNavigate) offNavigate()
  document.body.style.overflow = ''
  IDLE_EVENTS.forEach((e) => window.removeEventListener(e, resetIdle))
})

function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('sdm-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
}

/** Semua halaman, dikelompokkan untuk menu samping. Item yang tidak berhak diakses tidak ditampilkan. */
const groups = computed(() => [
  {
    title: 'Utama',
    items: [{ name: 'Dashboard', href: route('dashboard'), show: true }],
  },
  {
    title: 'Pekerjaan Saya',
    items: [
      { name: 'Laporan Harian', href: route('daily-reports.index'), show: isStaff.value },
      { name: 'Perintah Kerja', href: route('work-orders.index'), show: isStaff.value || has('work_orders.view') },
      { name: 'Penilaian Anggota', href: route('work-orders.ratings'), show: isStaff.value || has('work_orders.view') },
      { name: 'History Card', href: route('history-cards.index'), show: isStaff.value || has('work_orders.view') },
      { name: 'Nilai Saya', href: route('assessments.mine'), show: isStaff.value },
    ],
  },
  {
    title: 'Data & Laporan',
    items: [
      { name: 'Semua Laporan', href: route('daily-reports.all'), show: has('daily_reports.view') },
      { name: 'Rekap', href: route('recap.index'), show: has('reports.view') },
      { name: 'Penilaian', href: route('assessments.index'), show: has('performance.view') },
      { name: 'Form Penilaian', href: route('assessments.sheet'), show: has('performance.view') || isStaff.value },
      { name: 'Karyawan', href: route('employees.index'), show: has('employees.view') },
      { name: 'Departemen', href: route('departments.index'), show: has('departments.view') },
    ],
  },
  {
    title: 'Administrasi',
    items: [
      { name: 'Kelola Pengguna', href: route('users.index'), show: has('users.manage') || has('users.view'), badge: 'pending' },
      { name: 'Role & Hak Akses', href: route('roles.index'), show: isOwner.value },
      { name: 'Log Aktivitas', href: route('activity-log.index'), show: has('settings.manage') },
      { name: 'Backup Data', href: route('backup.index'), show: has('settings.manage') },
    ],
  },
].map((g) => ({ ...g, items: g.items.filter((i) => i.show) })).filter((g) => g.items.length))

/** Menu aktif = yang path-nya paling spesifik (terpanjang) cocok dengan URL, supaya /daily-reports dan /daily-reports/all tidak aktif bersamaan. */
const activeHref = computed(() => {
  const url = page.url.split('?')[0]
  let best = null
  let bestLen = -1
  for (const item of groups.value.flatMap((g) => g.items)) {
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
      <div class="mx-auto flex max-w-[1280px] items-center gap-3 px-3 py-2.5 sm:px-4">
        <button type="button" class="p-2 text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800" aria-label="Buka menu" :aria-expanded="menuOpen" @click="menuOpen = true">
          <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>

        <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2.5 text-ink-900 dark:text-ink-100">
          <Logo :size="34" />
          <div class="min-w-0">
            <b class="block font-serif text-xl uppercase leading-none tracking-wider">PMKCM V</b>
            <small class="block truncate text-[10px] font-semibold tracking-[.3em] text-seal-500">INDARUNG 5 · SDM</small>
          </div>
        </Link>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
          <button v-if="installEvent" type="button" class="btn-secondary btn-sm hidden sm:inline-flex" @click="installApp">Pasang Aplikasi</button>
          <Link :href="route('notifications.index')" class="relative p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" title="Notifikasi" aria-label="Notifikasi">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" :d="ICONS.bell" /></svg>
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
          <Link :href="route('profile.edit')" class="flex items-center gap-2" title="Buka profil saya">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="32" />
            <div class="hidden text-right text-[13px] leading-tight text-ink-500 dark:text-ink-400 md:block">
              <b class="block text-ink-900 dark:text-ink-100">{{ user?.name }}</b>
              {{ user?.employee?.position_title || (user?.roles ?? [])[0] }}
            </div>
          </Link>
        </div>
      </div>
    </header>

    <!-- Menu samping -->
    <div v-if="menuOpen" class="noprint fixed inset-0 z-40 bg-black/50" @click="closeMenu"></div>
    <aside
      class="noprint fixed inset-y-0 left-0 z-50 flex w-[300px] max-w-[85vw] flex-col border-r-4 border-seal-500 bg-white pt-[env(safe-area-inset-top)] transition-transform duration-200 dark:bg-ink-900"
      :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
      :aria-hidden="!menuOpen"
      aria-label="Menu utama"
    >
      <div class="flex items-center justify-between border-b border-ink-200 px-4 py-3 dark:border-ink-700">
        <div class="flex items-center gap-2.5 text-ink-900 dark:text-ink-100">
          <Logo :size="34" />
          <b class="font-serif text-xl uppercase tracking-wider">Menu</b>
        </div>
        <button type="button" class="p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" aria-label="Tutup menu" @click="closeMenu">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </div>

      <div class="border-b border-ink-200 px-4 py-3 text-sm dark:border-ink-700">
        <b class="block text-ink-900 dark:text-ink-100">{{ user?.name }}</b>
        <span class="text-xs text-ink-500 dark:text-ink-400">{{ (user?.roles ?? []).join(', ') || '—' }}</span>
      </div>

      <nav class="flex-1 overflow-y-auto py-2">
        <div v-for="g in groups" :key="g.title" class="py-1">
          <p class="px-4 pb-1 pt-2 font-serif text-[11px] font-semibold uppercase tracking-[.2em] text-ink-400">{{ g.title }}</p>
          <Link
            v-for="item in g.items" :key="item.name" :href="item.href"
            class="flex items-center justify-between border-l-4 px-4 py-3 font-serif text-[15px] font-semibold uppercase tracking-wider transition-colors"
            :class="activeHref === item.href
              ? 'border-seal-500 bg-ink-50 text-ink-900 dark:bg-ink-800 dark:text-ink-100'
              : 'border-transparent text-ink-600 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800'"
          >
            {{ item.name }}
            <span v-if="item.badge === 'pending' && ($page.props.pending_users + $page.props.pending_resets) > 0" class="bg-seal-500 px-1.5 py-0.5 text-[10px] font-bold text-white" title="Pendaftar / permintaan reset password menunggu">{{ $page.props.pending_users + $page.props.pending_resets }}</span>
          </Link>
        </div>
      </nav>

      <div class="space-y-2 border-t border-ink-200 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] dark:border-ink-700">
        <button v-if="installEvent" type="button" class="btn-secondary w-full" @click="installApp">Pasang Aplikasi</button>
        <Link :href="route('logout')" method="post" as="button" class="btn-secondary w-full">Keluar</Link>
      </div>
    </aside>

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
      <div class="grid grid-cols-4">
        <Link v-for="t in tabs" :key="t.name" :href="t.href" class="relative flex flex-col items-center gap-0.5 py-2 text-[10px] font-semibold uppercase tracking-wide" :class="tabActive(t.href) ? 'text-seal-600 dark:text-seal-400' : 'text-ink-500 dark:text-ink-400'">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" :d="t.icon" /></svg>
          {{ t.name }}
        </Link>
      </div>
    </nav>
  </div>
</template>
