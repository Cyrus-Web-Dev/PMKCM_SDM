// Antrean offline: laporan harian yang dikirim saat sinyal buruk disimpan di IndexedDB perangkat,
// lalu dikirim otomatis begitu online. Tiap data punya ID unik (header X-Offline-Id) sehingga pengiriman ulang
// tidak pernah menghasilkan data ganda (server menolak ID yang sudah diproses).
import axios from 'axios'
import { reactive } from 'vue'

const DB_NAME = 'sdm-offline'
const STORE = 'queue'
const MAX_ATTEMPTS = 5

export const offlineState = reactive({
  online: typeof navigator === 'undefined' ? true : navigator.onLine,
  items: [], // ringkasan item antrean (tanpa berkas)
  syncing: false,
  message: '',
})

// ---------- IndexedDB ----------
function openDb() {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === 'undefined') return reject(new Error('Perangkat tidak mendukung penyimpanan offline.'))
    const req = indexedDB.open(DB_NAME, 1)
    req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'id' })
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

async function run(mode, fn) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const t = db.transaction(STORE, mode)
    const request = fn(t.objectStore(STORE))
    t.oncomplete = () => { db.close(); resolve(request?.result) }
    t.onerror = () => { db.close(); reject(t.error) }
    t.onabort = () => { db.close(); reject(t.error) }
  })
}
const readAll = () => run('readonly', (s) => s.getAll())
const putItem = (item) => run('readwrite', (s) => s.put(item))
const delItem = (id) => run('readwrite', (s) => s.delete(id))

export async function refresh() {
  try {
    const items = (await readAll()) ?? []
    offlineState.items = items
      .sort((a, b) => a.createdAt.localeCompare(b.createdAt))
      .map(({ files, fields, ...rest }) => ({ ...rest, fileCount: files?.length ?? 0 }))
  } catch (e) {
    offlineState.items = []
  }
}

const uuid = () => (globalThis.crypto?.randomUUID ? crypto.randomUUID() : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`)

// ---------- pengiriman ----------
function send(url, fields, files, id) {
  const fd = new FormData()
  for (const [k, v] of Object.entries(fields ?? {})) {
    if (v !== null && v !== undefined && v !== '') fd.append(k, v)
  }
  for (const f of files ?? []) fd.append(f.field, f.blob, f.name)
  return axios.post(url, fd, { headers: { Accept: 'application/json', 'X-Offline-Id': id }, timeout: 20000 })
}

function classify(e) {
  if (!e.response) return 'network'
  const s = e.response.status
  if ([502, 503, 504].includes(s)) return 'network'
  if (s === 401 || s === 419) return 'auth'
  if ([400, 403, 404, 409, 422].includes(s)) return 'rejected'
  return 'server'
}

function messageOf(e) {
  const d = e.response?.data
  if (d?.errors) return Object.values(d.errors)[0]?.[0] ?? 'Data ditolak server.'
  return d?.message ?? 'Gagal mengirim data.'
}

/**
 * Kirim sekarang; bila tidak ada koneksi, simpan ke antrean.
 * Mengembalikan { status: 'sent' | 'queued', message }. Melempar Error (dengan .errors / .kind) bila DITOLAK server
 * (mis. validasi) - penolakan tidak diantrekan karena percuma diulang.
 */
export async function postOrQueue({ type, url, label, fields = {}, files = [] }) {
  const id = uuid()
  const data = { ...fields, captured_at: new Date().toISOString() }

  try {
    const res = await send(url, data, files, id)
    return { status: 'sent', message: res.data?.message ?? 'Tersimpan.' }
  } catch (e) {
    const kind = classify(e)
    if (kind === 'network') {
      await putItem({ id, type, url, label, fields: data, files, createdAt: new Date().toISOString(), status: 'pending', attempts: 0, error: null })
      await refresh()
      return { status: 'queued', message: 'Tidak ada koneksi. Data disimpan di perangkat dan dikirim otomatis saat online.' }
    }
    const err = new Error(messageOf(e))
    err.kind = kind
    err.errors = e.response?.data?.errors ?? null
    throw err
  }
}

let running = false

export async function syncAll() {
  if (running || (typeof navigator !== 'undefined' && !navigator.onLine)) return 0
  running = true
  offlineState.syncing = true
  let synced = 0

  try {
    const items = ((await readAll()) ?? []).filter((i) => i.status !== 'failed').sort((a, b) => a.createdAt.localeCompare(b.createdAt))

    for (const item of items) {
      try {
        await send(item.url, item.fields, item.files, item.id)
        await delItem(item.id)
        synced++
      } catch (e) {
        const kind = classify(e)
        if (kind === 'network') { offlineState.message = 'Sinkron tertunda: koneksi bermasalah.'; break }
        if (kind === 'auth') { offlineState.message = 'Sesi berakhir. Login ulang lalu sinkronkan lagi.'; break }
        if (kind === 'server') {
          const attempts = (item.attempts ?? 0) + 1
          await putItem({ ...item, attempts, ...(attempts >= MAX_ATTEMPTS ? { status: 'failed', error: 'Server error berulang. Hubungi admin.' } : {}) })
          offlineState.message = 'Sinkron tertunda: server bermasalah.'
          break
        }
        await putItem({ ...item, status: 'failed', error: messageOf(e) }) // ditolak (validasi, sudah absen, dst)
      }
    }
  } catch (e) {
    // IndexedDB tidak tersedia: tidak ada yang bisa disinkronkan
  } finally {
    running = false
    offlineState.syncing = false
    await refresh()
  }

  if (synced) {
    offlineState.message = `${synced} data berhasil disinkronkan.`
    window.dispatchEvent(new CustomEvent('sdm-synced'))
  }
  return synced
}

export async function removeItem(id) {
  await delItem(id)
  await refresh()
}

export async function retryItem(id) {
  const item = ((await readAll()) ?? []).find((i) => i.id === id)
  if (item) {
    await putItem({ ...item, status: 'pending', attempts: 0, error: null })
    await refresh()
    syncAll()
  }
}

let inited = false
export function initOffline() {
  if (inited || typeof window === 'undefined') return
  inited = true

  window.addEventListener('online', () => { offlineState.online = true; syncAll() })
  window.addEventListener('offline', () => { offlineState.online = false })
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') syncAll() })
  setInterval(() => {
    if (navigator.onLine && offlineState.items.some((i) => i.status !== 'failed')) syncAll()
  }, 60000)

  refresh().then(() => syncAll())
}
