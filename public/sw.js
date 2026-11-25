/* Service worker SDM PMKCM V: HANYA menyimpan berkas statis (JS/CSS/gambar) agar aplikasi cepat dibuka & bisa dipasang
   di HP. Halaman dan data login TIDAK pernah disimpan. Pengiriman data butuh koneksi; laporan yang dibuat offline diantre di perangkat. */
const CACHE = 'sdm-static-v2'
const OFFLINE = '/offline.html'

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/images/logo.svg'])).then(() => self.skipWaiting()))
})

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())
  )
})

self.addEventListener('fetch', (e) => {
  const req = e.request
  if (req.method !== 'GET') return
  const url = new URL(req.url)
  if (url.origin !== location.origin) return

  // Halaman: selalu dari jaringan; bila offline tampilkan halaman offline.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)))
    return
  }

  // Berkas statis ber-hash (build Vite) & ikon: cache-first.
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/images/')) {
    e.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)) }
        return res
      }))
    )
  }
})
