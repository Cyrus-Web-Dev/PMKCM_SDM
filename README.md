# SDM PMKCM V · Indarung 5 (V2)

Aplikasi manajemen SDM: absensi berbasis kamera, laporan harian dengan foto bukti, izin & cuti, penilaian outsourcing,
rekap, dan dashboard grafik. **Laravel 11 · Inertia + Vue 3 · Tailwind · PostgreSQL.** Tampilan mengikuti template PMKCM V.

## Fitur

| Modul | Ringkasan |
|---|---|
| **Absensi** | Check-in **wajib foto kamera realtime** (watermark nama + tanggal + jam) dan memilih **shift**; Tepat Waktu / Terlambat (X menit) menurut jam shift + toleransi (diatur Owner). Check-out foto opsional. Satu check-in per hari-shift, shift malam didukung. **Alfa otomatis** untuk hari kerja tanpa absen/izin/cuti. Check-in/out hanya lewat web (REST API hanya membaca riwayat). |
| **Laporan Harian** | Isi laporan (tim, shift, area, jam, uraian, kendala, status). **Foto bukti 1–3 dari kamera, wajib bila status Selesai.** Ka. URS memberi review *Disetujui / Perlu Revisi* + catatan, **komentar dua arah**, karyawan dapat merevisi. Pencarian & filter status/review. |
| **Izin & Cuti** | Jenis **Izin** (disetujui Owner atau Ka. URS) dan **Cuti** (hanya Owner). Setelah disetujui otomatis muncul di data absensi sebagai Izin/Sakit/Cuti. |
| **Penilaian Outsourcing** | 5 kriteria (Kualitas Kerja, Kedisiplinan, Kerja Sama, Inisiatif, Kepatuhan K3), nilai 0–100, predikat A–D, **Form Penilaian** cetak / PDF, email lewat `mailto:`. |
| **Rekap** | Harian / mingguan / bulanan / tahunan per karyawan & tim. |
| **Dashboard grafik** (Owner & Ka. URS) | Kartu statistik, doughnut absensi hari ini, tren absensi, radar nilai per kriteria, nilai per tim, tren nilai 6 bulan, laporan per status, kendala terbanyak, peringkat, dan peringatan (belum lapor / nilai < 70). Filter periode (termasuk kustom), tim, role. |
| **Ekspor** | Excel & PDF untuk laporan, rekap, penilaian, karyawan; PDF laporan **dengan foto**; PDF Form Penilaian. |
| **Notifikasi** | Lonceng dalam aplikasi (cuti diajukan/diputuskan, laporan berkendala/direview/dikomentari, terlambat, absen offline, penilaian baru, shift berubah, permintaan reset password). Jumlah belum dibaca diperbarui tiap 60 detik; pengingat otomatis 16:30 bagi yang sudah check-in tetapi belum melapor. |
| **Mode offline** | Absen & laporan baru yang dikirim saat sinyal buruk **disimpan di perangkat** (IndexedDB, termasuk foto) lalu **dikirim otomatis** saat online. ID unik per kiriman mencegah data ganda. Absen offline memakai waktu perangkat (batas wajar 36 jam), ditandai **Offline** dan memberi notifikasi ke Ka. URS/Owner. |
| **Audit log** | Siapa, apa, kapan, IP, perangkat, dan **rincian perubahan (dari -> ke)** untuk data karyawan, koreksi absensi, shift, dan pengaturan. Filter lengkap + ekspor Excel/PDF (Owner). |
| **Mobile & PWA** | Bisa dipasang di HP (tombol "Pasang Aplikasi"), tab menu bawah untuk karyawan, target sentuh besar, halaman offline, pintasan Absen/Laporan/Notifikasi. |
| **Keamanan** | Login email + password (argon2id), rate limit, wajib ganti password sementara, auto-logout saat idle (diatur Owner), wizard setup awal (tanpa kredensial bawaan), reset password lewat permintaan ke Owner (tanpa email). |
| **Tetap dari V1** | Kelola Pengguna & Role (Spatie), Backup Database, Log Aktivitas, Karyawan, Departemen, Hari Libur. |

Dihapus dari V1: Payroll & PAYBRIDGE, Rekrutmen, Pelatihan, Dokumen, Pengumuman, 2FA OTP, pendaftaran mandiri, Gmail API.

## Role bawaan

| Role | Keterangan |
|---|---|
| `super-admin` (**Owner**) | Semua izin. Satu-satunya yang membuat akun, mereset password, mengatur Pengaturan, dan menyetujui **cuti**. |
| `ka-urs` | Menilai, me-review laporan, menyetujui **izin**, dashboard grafik, melihat **semua tim** (izin `team.all`). |
| `karyawan` | Isi laporan, absen, ajukan izin/cuti, lihat nilai sendiri. |
| `hr-admin`, `manajer` | Peninggalan V1 (operasional HR / atasan tim). `manajer` hanya melihat timnya. Boleh dihapus lewat Kelola Pengguna. |

Izin baru: `daily_reports.view/review`, `dashboard.analytics`, `leaves.approve_cuti`, `shifts.manage`, `team.all`, `performance.email`.

## Instalasi

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
# buat database kosong:  CREATE DATABASE sdm_pmkcm;  lalu isi DB_* di .env
php artisan migrate
php artisan serve
```

Buka aplikasi: bila belum ada pengguna, otomatis muncul **Setup Awal** untuk membuat akun Owner (role, shift, jenis cuti, dan
departemen awal ikut dibuat). Alternatif: isi `SDM_ADMIN_EMAIL` + `SDM_ADMIN_PASSWORD` di `.env` lalu `php artisan migrate --seed`.

### Wajib untuk produksi

1. **HTTPS.** Browser hanya mengizinkan kamera di HTTPS (atau `localhost`/`127.0.0.1`). Tanpa HTTPS tombol foto tidak jalan.
2. **Scheduler.** Alfa otomatis (06:00), pengingat laporan (16:30), pembersihan notifikasi (Minggu 03:00), dan backup harian (02:00) butuh cron: `* * * * * cd /path/app && php artisan schedule:run`.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SDM_FORCE_HTTPS=true`.
4. Foto tersimpan di `storage/app/private`; sertakan folder itu dalam backup berkas server.

## Catatan & batasan

- Watermark foto dibuat di browser dan jam di watermark berasal dari perangkat; **waktu resmi absensi selalu dari server**. Foto dari kamera
  belum bisa menjamin 100% anti-manipulasi (mis. perangkat yang dimodifikasi).
- **Mode offline** bekerja bila halaman Absen/Laporan **sudah terbuka** saat sinyal hilang (membuka aplikasi dari nol tanpa sinyal belum didukung).
  Absen offline memakai **jam perangkat**, sehingga bisa disalahgunakan dengan mengubah jam HP. Pengamannya: batas 36 jam, tanda "Offline" di data
  absensi, notifikasi ke Ka. URS/Owner, dan watermark jam pada foto. Check-out offline hanya valid bila check-in-nya maksimal kemarin.
  Merevisi laporan yang sudah ada tetap butuh koneksi.
- Email hasil penilaian memakai `mailto:` (membuka aplikasi email pengguna), seperti template; tidak ada pengiriman email dari server.
- Login memakai **email** (bukan username).
- Tanda tangan digital pada Form Penilaian belum ada (kolom tanda tangan tersedia untuk dicetak).

## Perintah berguna

```bash
php artisan sdm:mark-alpha --date=2026-11-20   # tandai Alfa manual untuk tanggal tertentu
php artisan sdm:remind-reports                  # pengingat laporan harian (jalan otomatis 16:30)
php artisan sdm:prune-notifications             # bersihkan notifikasi lama
php artisan sdm:backup-database                 # backup manual
php artisan sdm:user                            # buat/ubah role pengguna lewat CLI
```
