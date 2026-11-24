// Fungsi kecil yang dipakai bersama oleh halaman Laporan Harian, Rekap, dan Penilaian.

export const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

/** '2026-11-23' -> '23/11/2026' (tanpa Date, supaya tidak tergeser zona waktu). */
export const fmtDate = (d) => (d ? d.split('-').reverse().join('/') : '-')

export const STATUS_BADGE = { selesai: 'badge-green', proses: 'badge-amber', kendala: 'badge-red' }
export const REVIEW_BADGE = { menunggu: 'badge-slate', disetujui: 'badge-green', perlu_revisi: 'badge-amber' }

/** Predikat dari nilai 0-100 (aturan sama dengan Assessment::grade di server). */
export const gradeOf = (n) => (n == null ? '-' : n >= 90 ? 'A · Sangat Baik' : n >= 80 ? 'B · Baik' : n >= 70 ? 'C · Cukup' : 'D · Perlu Pembinaan')
export const gradeBadge = (n) => (n == null ? 'badge-slate' : n >= 90 ? 'badge-green' : n >= 80 ? 'badge-seal' : n >= 70 ? 'badge-amber' : 'badge-red')

/** Tautan mailto: (membuka aplikasi email di perangkat pengguna, seperti pada template PMKCM V). */
export const mailto = (to, subject, lines) =>
  `mailto:${to}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(lines.join('\n'))}`
