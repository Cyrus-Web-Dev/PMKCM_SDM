# Checklist pengujian SDM V2 (Fase 1–3)

Urutan disarankan. Gunakan HTTPS atau `http://localhost:8000` agar kamera berfungsi.

## 0. Persiapan
- [ ] `composer install`, `npm install && npm run build`, salin `.env`, isi DB_*, `php artisan key:generate`, `php artisan migrate`
- [ ] Buka aplikasi -> muncul **Setup Awal** -> buat akun Owner -> login
- [ ] Menu **Pengaturan**: toleransi terlambat 10 menit; **Shift** sudah ada 3 shift

## 1. Akun & keamanan (Fase 1)
- [ ] Owner: Karyawan > Tambah > centang "Buatkan akun login" > isi email + password awal sederhana
- [ ] Login akun baru -> dipaksa ganti password
- [ ] Login salah berkali-kali -> kena batas laju
- [ ] "Lupa password" -> kirim permintaan -> muncul di Kelola Pengguna (Owner) -> Reset Password -> password sementara tampil SEKALI
- [ ] Kirim permintaan ke-2 langsung -> ditolak (pending / jeda 15 menit)
- [ ] hr-admin TIDAK bisa membuat akun / reset password

## 2. Operasional (Fase 2)
- [ ] Absen Saya: pilih shift -> Absen Datang -> kamera -> foto + watermark -> status Tepat Waktu / Terlambat sesuai jam shift
- [ ] Absen Pulang (dengan / tanpa foto); check-in kedua di hari sama ditolak
- [ ] Laporan Harian: status Selesai tanpa foto ditolak; dengan 1–3 foto berhasil
- [ ] Ka. URS: Semua Laporan > Detail > Perlu Revisi + catatan; karyawan merevisi; komentar dua arah
- [ ] Ajukan Izin -> Ka. URS bisa setujui; ajukan Cuti -> Ka. URS TIDAK bisa, Owner bisa
- [ ] Setelah disetujui: data absensi hari kerja pada rentang itu berstatus Izin/Sakit/Cuti
- [ ] `php artisan sdm:mark-alpha --date=YYYY-MM-DD` menandai Alfa

## 3. Dashboard & analisis (Fase 3)
- [ ] Login Owner / Ka. URS: Dashboard menampilkan kartu + 7 grafik
- [ ] Filter periode (harian/mingguan/bulanan/tahunan/kustom), tim, role
- [ ] Karyawan biasa: dashboard personal, TANPA grafik
- [ ] Semua Laporan: pencarian, filter status & review; ekspor Excel, PDF, **PDF + Foto**
- [ ] Rekap, Penilaian: ekspor Excel/PDF; Form Penilaian: Cetak + Unduh PDF

---
# Tambahan Fase 4

## 4. Notifikasi internal
- [ ] Lonceng di header menampilkan angka; klik notifikasi -> menuju halaman terkait dan angka berkurang
- [ ] Karyawan check-in terlambat -> Ka. URS/Owner dapat notifikasi
- [ ] Ubah jam shift -> semua karyawan berakun dapat notifikasi
- [ ] `php artisan sdm:remind-reports` -> karyawan yang sudah check-in tapi belum lapor dapat pengingat

## 5. Mode offline (uji di HP; buka halaman dulu saat online)
- [ ] Buka **Laporan Harian**, matikan data/wifi, isi laporan + foto -> muncul "disimpan di perangkat"
- [ ] Bar oranye: "1 data menunggu sinkron"; nyalakan sinyal -> terkirim otomatis, laporan muncul di riwayat
- [ ] Buka **Absen Saya**, matikan sinyal, Absen Datang -> tersimpan; nyalakan sinyal -> tercatat berstatus **Offline**
- [ ] Owner/Ka. URS menerima notifikasi "Absen offline disinkronkan"; di halaman Absensi tampil badge **Offline**
- [ ] Matikan sinyal tepat saat kirim lalu nyalakan lagi: tidak ada data ganda
- [ ] Data yang ditolak server (mis. sudah check-in) muncul "gagal" di bar, bisa Hapus / Coba lagi

## 6. Audit log (Owner)
- [ ] Ubah data karyawan -> Log Aktivitas > Detail menampilkan perubahan dari -> ke, IP, perangkat
- [ ] Filter tanggal / pengguna / aksi / kata kunci; ekspor Excel dan PDF

## 7. Mobile & PWA (uji di HP, HTTPS)
- [ ] Muncul "Pasang Aplikasi" (Android Chrome) / Add to Home Screen (iOS); ikon & mode layar penuh tampil
- [ ] Karyawan: tab bawah Beranda / Absen / Laporan / Cuti / Notif berfungsi
- [ ] Tombol & isian mudah disentuh; tabel bisa digeser; kamera terbuka
- [ ] Tanpa sinyal membuka aplikasi dari ikon -> halaman "Sedang Offline" (bukan error browser)
