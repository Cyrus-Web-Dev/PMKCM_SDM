# SDM PMKCM V · Indarung 5 (V2)

Aplikasi manajemen SDM pabrik: **Perintah Kerja Harian**, **Laporan Harian** (foto bukti), **Penilaian Anggota**, **History Card**,
penilaian bulanan, rekap, dashboard grafik, **Role & Hak Akses fleksibel**, serta backup data.
**Laravel 11 · Inertia + Vue 3 · Tailwind · PostgreSQL _atau_ MySQL/MariaDB** (hosting cPanel). Tampilan mengikuti template PMKCM V.

## Fitur

| Modul | Ringkasan |
|---|---|
| **Perintah Kerja Harian** | Dibuat pemegang izin *Kelola Perintah Kerja* (Owner/Ka. URS): tanggal, **kepala kerja**, **anggota kerja** (beberapa), uraian, tim/area, **status** (Direncanakan → Berjalan → Selesai / Dibatalkan). Kepala kerja & anggota selalu melihat perintahnya sendiri; kepala kerja mengubah status. Nomor otomatis `PK-YYYYMMDD-001`. Laporan harian bisa ditautkan ke perintah kerja. |
| **Penilaian Anggota** | Setelah perintah **Selesai**, **kepala kerja menilai tiap anggota** (5 kriteria 0–100: Kualitas Kerja, Kedisiplinan, Kerja Sama, Inisiatif, Kepatuhan K3; predikat A–D). Anggota hanya melihat nilainya sendiri. Daftar + ekspor Excel/PDF. |
| **History Card** | Arsip otomatis perintah kerja berstatus Selesai: uraian, tanggal mulai, tanggal selesai, kepala kerja, anggota kerja. Cari/filter, ekspor Excel/PDF, kartu cetak/PDF per pekerjaan. |
| **Laporan Harian** | Isi laporan (tim, area, jam, uraian, kendala, status). **Foto bukti 1–3 dari kamera**, wajib bila status Selesai. Review Ka. URS (Disetujui / Perlu Revisi), komentar dua arah, revisi oleh karyawan, pencarian & filter, ekspor Excel/PDF (+PDF berisi foto). |
| **Penilaian Bulanan** | Penilaian per karyawan per bulan (5 kriteria), riwayat, ekspor Excel/PDF, kirim hasil lewat `mailto:`. |
| **Rekap & Dashboard** | Rekap harian/mingguan/bulanan/tahunan. Dashboard grafik (Chart.js) untuk pemegang izin *Dashboard Grafik*. |
| **Role & Hak Akses** | **Owner bebas membuat role baru** (mis. KA Unit, KA Gudang) dan menentukan hak aksesnya dengan **checklist** per modul: lihat saja, kelola penuh (CRUD), menilai, review, ekspor, dst. Tombol cepat *Hanya lihat / Penuh / Kosongkan*. Role bisa diubah/dihapus kapan saja. |
| **Backup Data** | 4 jenis: **(1)** database keseluruhan, **(2)** data Owner (PDF), **(3)** data role tambahan (PDF), **(4)** data karyawan (PDF) + tombol **1 PDF gabungan**. |
| **Lainnya** | Menu samping (garis tiga di kiri atas), notifikasi dalam aplikasi, mode offline untuk laporan harian, PWA, audit log (dari → ke), lupa password lewat permintaan ke Owner, wizard setup awal, auto-logout idle. |

**Dihapus atas permintaan klien:** Absensi & Absen Saya, Cuti/Izin, Hari Libur, Shift Kerja, halaman Pengaturan, **Departemen & Jabatan** (halaman dan semua kolomnya), dan **Form Penilaian**.
**Tim** kini berupa isian teks bebas (bukan dropdown).
(Dari V1: Payroll, Rekrutmen, Pelatihan, Dokumen, Pengumuman, 2FA OTP, pendaftaran mandiri, Gmail API.)

## Role & hak akses

- `super-admin` = **Owner**: semua akses, tidak bisa diubah/dihapus. Hanya Owner yang membuat akun login, mereset password, dan mengelola role.
- `karyawan`: role bawaan self-service (laporan harian, perintah kerja saya, nilai saya). Hak aksesnya bisa ditambah Owner; tidak bisa dihapus.
- `ka-urs`: contoh bawaan (menilai, review, grafik, perintah kerja, semua tim). **Bebas diubah/dihapus** Owner.
- Role lain dibuat Owner di menu **Role & Hak Akses**. Menjalankan seeder ulang **tidak menimpa** pengaturan role yang sudah diubah.

> **Cakupan data:** tanpa izin *Melihat data SEMUA tim*, pemilik role hanya melihat karyawan yang atasannya adalah dirinya / departemen yang ia pimpin.
> Untuk role setingkat Ka. Unit yang harus melihat semua tim, centang izin tersebut.

## Database: PostgreSQL atau MySQL/MariaDB
Aplikasi berjalan di kedua jenis database (query dipilih otomatis lewat `App\Support\Sql`). Isi `DB_CONNECTION=pgsql` atau `mysql` di `.env`.

## Instalasi

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
# buat database kosong:  CREATE DATABASE sdm_pmkcm;  lalu isi DB_* di .env
php artisan migrate
php artisan serve
```

Saat belum ada pengguna, muncul **Setup Awal** untuk membuat akun Owner (role, izin, dan departemen awal ikut dibuat).
Alternatif: isi `SDM_ADMIN_EMAIL` + `SDM_ADMIN_PASSWORD` di `.env` lalu `php artisan migrate --seed`.

**Hosting tanpa terminal (cPanel):** jalankan perintah artisan lewat **Cron Jobs** cPanel — buat cron sekali jalan (mis. tiap menit), tunggu ±1 menit, lalu **hapus cron-nya**:
```
cd /home/USER/folder-aplikasi && php artisan migrate --force && php artisan db:seed --class=RolePermissionSeeder --force && php artisan optimize:clear
```
(ganti `USER` dan `folder-aplikasi`; tambahkan `>> storage/logs/cron.log 2>&1` bila ingin melihat hasilnya). Log kesalahan aplikasi ada di `storage/logs/laravel.log` (File Manager).

**Upgrade dari versi sebelumnya:** jalankan `php artisan migrate` (migrasi baru menghapus tabel Absensi/Cuti/Libur/Shift/Pengaturan, dan izin lamanya),
(migrasi baru juga menghapus Departemen & Jabatan), lalu `php artisan db:seed --class=RolePermissionSeeder` untuk memutakhirkan katalog izin (tidak menimpa role yang sudah Anda ubah).

### Wajib untuk produksi
1. **HTTPS** — kamera hanya diizinkan browser di HTTPS (atau `localhost`).
2. **Scheduler** (cron): `* * * * * cd /path/app && php artisan schedule:run` — backup harian 02:00, pengingat laporan 16:30, pembersihan notifikasi.
3. `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SDM_FORCE_HTTPS=true`.
4. Sertakan folder `storage/app/private` (foto bukti kerja) dalam backup berkas server.

## Catatan & batasan
- PDF backup (Owner / Role Tambahan / Karyawan) adalah **arsip baca-saja**: tanpa data sensitif (gaji, rekening, NPWP, BPJS, password) dan **tidak bisa untuk memulihkan sistem** — untuk itu pakai Backup Database. Entri log di PDF dibatasi 500 terbaru.
- Mode offline hanya untuk **laporan harian baru**, dan halaman Laporan Harian harus sudah terbuka saat sinyal hilang. Merevisi laporan butuh koneksi.
- Watermark foto dibuat di browser (jam perangkat), jadi belum anti-manipulasi 100%.
- Login memakai **email**. Email hasil penilaian memakai `mailto:` (tanpa pengiriman dari server).
- Anggota perintah kerja yang dikeluarkan saat perintah diubah ikut kehilangan nilainya pada perintah itu.

## Perintah berguna
```bash
php artisan sdm:remind-reports        # pengingat laporan (otomatis 16:30)
php artisan sdm:prune-notifications   # bersihkan notifikasi lama
php artisan sdm:backup-database       # backup database manual
php artisan sdm:user <email> --role=<nama role>   # buat/ubah role pengguna lewat CLI
```
