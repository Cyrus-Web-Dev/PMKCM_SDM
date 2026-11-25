# Checklist pengujian SDM V2

Gunakan HTTPS atau `http://localhost:8000` agar kamera berfungsi. Uji juga di HP.

## 0. Persiapan
- [ ] `composer install`, `npm install && npm run build`, salin `.env`, isi DB_*, `php artisan key:generate`, `php artisan migrate`
- [ ] Buka aplikasi -> **Setup Awal** -> buat akun Owner -> login

## 1. Menu samping
- [ ] Tombol garis tiga di kiri atas membuka menu; klik di luar / tombol X / Esc menutupnya
- [ ] Memilih halaman menutup menu otomatis; halaman aktif ditandai garis oranye
- [ ] Di HP: header rapi (tidak berantakan), tab bawah Beranda / Laporan / Perintah / Nilai muncul untuk karyawan

## 2. Role & hak akses (Owner)
- [ ] Menu **Role & Hak Akses** > Role Baru: buat **KA Unit** dengan *Kelola penuh* pada Data Karyawan + Perintah Kerja
- [ ] Buat **KA Gudang** dengan *Hanya lihat* pada Data Karyawan + Perintah Kerja
- [ ] Mencentang "Kelola" otomatis mencentang "Lihat"; "Kosongkan" membersihkan modul
- [ ] Kelola Pengguna: pasang role KA Unit & KA Gudang ke dua akun; login masing-masing
- [ ] KA Unit bisa tambah/ubah/hapus; KA Gudang hanya melihat (tombol kelola tidak muncul / ditolak server)
- [ ] Centang "Melihat data SEMUA tim" mengubah cakupan data yang terlihat
- [ ] Role yang masih dipakai tidak bisa dihapus; `super-admin` & `karyawan` tidak bisa dihapus
- [ ] Menjalankan `php artisan db:seed` TIDAK mengembalikan hak akses role yang sudah diubah

## 3. Perintah Kerja Harian
- [ ] Buat perintah: kepala kerja + beberapa anggota + uraian; nomor `PK-...-001` terbentuk; kepala kerja tidak bisa jadi anggota
- [ ] Kepala kerja & anggota (akun karyawan biasa) melihat perintah itu; orang lain tidak
- [ ] Kepala kerja: Direncanakan -> Berjalan -> Selesai (isi tanggal selesai)
- [ ] Laporan Harian: dropdown "Perintah Kerja" menampilkan perintah milik sendiri; laporan tampil di detail perintah

## 4. Penilaian Anggota
- [ ] Sebelum Selesai: tombol nilai tidak ada. Setelah Selesai: kepala kerja menilai tiap anggota (5 kriteria + catatan)
- [ ] Anggota hanya melihat nilainya sendiri; kepala kerja melihat semua; menu **Penilaian Anggota** + ekspor Excel/PDF

## 5. History Card
- [ ] Perintah Selesai muncul di **History Card** (uraian, tgl mulai, tgl selesai, kepala kerja, anggota)
- [ ] Filter/pencarian; ekspor Excel & PDF; **Kartu** -> Cetak & Unduh PDF

## 6. Laporan harian, dashboard, rekap
- [ ] Laporan Selesai tanpa foto ditolak; dengan 1–3 foto dari kamera berhasil (kamera terbuka di HP)
- [ ] Review Ka. URS, komentar, revisi karyawan, ekspor PDF + Foto
- [ ] Dashboard grafik (Owner/role dengan izin Grafik): kartu perintah kerja, doughnut status, tren, radar, dll.

## 7. Backup Data
- [ ] 4 kartu backup tampil; **Backup Database** membuat file dan bisa diunduh
- [ ] PDF **Data Owner**, **Data Role Tambahan**, **Data Karyawan**, dan **PDF Gabungan** terunduh dan terbaca
- [ ] PDF tidak memuat gaji/rekening/NPWP/BPJS/password

## 8. Lainnya
- [ ] Lupa password -> permintaan muncul di Kelola Pengguna (Owner) -> Reset -> password sementara tampil sekali
- [ ] Mode offline: matikan sinyal, isi Laporan Harian + foto -> tersimpan di perangkat -> kirim otomatis saat online, tanpa data ganda
- [ ] Log Aktivitas: ubah role/karyawan -> detail menampilkan perubahan dari -> ke; ekspor
- [ ] Notifikasi lonceng bekerja; "Pasang Aplikasi" muncul di HP
