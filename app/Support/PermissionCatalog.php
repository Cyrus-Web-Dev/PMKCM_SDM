<?php

namespace App\Support;

/**
 * Daftar SEMUA hak akses yang bisa dicentang Owner saat membuat/mengubah role.
 * Format per izin: 'nama.izin' => [label lengkap, label singkat]. Hanya izin di katalog ini yang ada di sistem.
 */
class PermissionCatalog
{
    public const GROUPS = [
        'employees' => [
            'label' => 'Data Karyawan',
            'permissions' => [
                'employees.view' => ['Melihat data karyawan', 'Lihat'],
                'employees.manage' => ['Tambah, ubah, dan hapus (arsipkan) karyawan', 'Kelola'],
                'employees.export' => ['Ekspor data karyawan ke Excel', 'Ekspor'],
            ],
        ],
        'work_orders' => [
            'label' => 'Perintah Kerja, Penilaian Anggota & History Card',
            'hint' => 'Kepala kerja & anggota selalu melihat perintah kerjanya sendiri. Kepala kerja otomatis boleh mengubah status dan menilai anggotanya.',
            'permissions' => [
                'work_orders.view' => ['Melihat semua perintah kerja, penilaian anggota & history card', 'Lihat'],
                'work_orders.manage' => ['Membuat, mengubah, dan menghapus perintah kerja', 'Kelola'],
            ],
        ],
        'daily_reports' => [
            'label' => 'Laporan Harian',
            'hint' => 'Mengisi laporan harian sendiri tidak butuh izin (cukup punya data karyawan).',
            'permissions' => [
                'daily_reports.view' => ['Melihat Semua Laporan Harian', 'Lihat'],
                'daily_reports.review' => ['Me-review laporan (Disetujui / Perlu Revisi) dan berkomentar', 'Review'],
            ],
        ],
        'performance' => [
            'label' => 'Penilaian Bulanan (Outsourcing)',
            'permissions' => [
                'performance.view' => ['Melihat penilaian bulanan & Form Penilaian', 'Lihat'],
                'performance.manage' => ['Memberi dan menghapus penilaian bulanan', 'Beri nilai'],
                'performance.email' => ['Menampilkan tombol kirim hasil penilaian lewat email', 'Email'],
            ],
        ],
        'reports' => [
            'label' => 'Rekap Kinerja',
            'permissions' => [
                'reports.view' => ['Melihat Rekap (harian/mingguan/bulanan/tahunan)', 'Lihat'],
                'reports.export' => ['Ekspor rekap', 'Ekspor'],
            ],
        ],
        'dashboard' => [
            'label' => 'Dashboard Grafik',
            'permissions' => [
                'dashboard.analytics' => ['Melihat dashboard statistik & grafik', 'Grafik'],
            ],
        ],
        'users' => [
            'label' => 'Pengguna & Akun',
            'hint' => 'Pembuatan role sendiri hanya bisa dilakukan Owner.',
            'permissions' => [
                'users.view' => ['Melihat daftar pengguna', 'Lihat'],
                'users.manage' => ['Membuat akun login, reset password, mengubah role & status pengguna', 'Kelola'],
            ],
        ],
        'settings' => [
            'label' => 'Log Aktivitas & Backup',
            'permissions' => [
                'settings.manage' => ['Melihat log aktivitas serta membuat/mengunduh backup data', 'Akses'],
            ],
        ],
        'team' => [
            'label' => 'Cakupan Data',
            'hint' => 'Tanpa izin ini, pemilik role hanya melihat karyawan yang atasan langsungnya adalah dirinya. Centang untuk role setingkat Ka. Unit yang harus melihat semua tim.',
            'permissions' => [
                'team.all' => ['Melihat data SEMUA tim', 'Semua tim'],
            ],
        ],
    ];

    /** Role bawaan (hanya dibuat bila belum ada; Owner bebas mengubahnya lewat menu Role & Hak Akses). */
    public const DEFAULT_ROLES = [
        'karyawan' => [],
        'ka-urs' => [
            'employees.view', 'performance.view', 'performance.manage', 'performance.email',
            'daily_reports.view', 'daily_reports.review', 'dashboard.analytics', 'work_orders.view', 'work_orders.manage',
            'reports.view', 'reports.export', 'team.all',
        ],
    ];

    /** @return array<int,string> */
    public static function names(): array
    {
        $out = [];
        foreach (self::GROUPS as $group) {
            foreach (array_keys($group['permissions']) as $name) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /** nama izin => label singkat */
    public static function shortLabels(): array
    {
        $out = [];
        foreach (self::GROUPS as $group) {
            foreach ($group['permissions'] as $name => [$label, $short]) {
                $out[$name] = $short;
            }
        }

        return $out;
    }

    /** Struktur siap pakai untuk halaman Vue (checklist). */
    public static function forUi(): array
    {
        $out = [];
        foreach (self::GROUPS as $key => $group) {
            $out[] = [
                'key' => $key,
                'label' => $group['label'],
                'hint' => $group['hint'] ?? null,
                'permissions' => collect($group['permissions'])->map(fn ($v, $name) => ['name' => $name, 'label' => $v[0], 'short' => $v[1]])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Bersihkan pilihan: buang nama yang tidak dikenal, dan pastikan izin "Lihat" ikut tercentang
     * bila ada izin lain pada modul yang sama (mis. Kelola tanpa Lihat tidak masuk akal).
     *
     * @param  array<int,string>  $selected
     * @return array<int,string>
     */
    public static function normalize(array $selected): array
    {
        $known = array_flip(self::names());
        $set = array_flip(array_values(array_filter($selected, fn ($n) => isset($known[$n]))));

        foreach (self::GROUPS as $key => $group) {
            $view = $key.'.view';
            if (! isset($group['permissions'][$view])) {
                continue;
            }
            foreach (array_keys($group['permissions']) as $name) {
                if ($name !== $view && isset($set[$name])) {
                    $set[$view] = true;
                }
            }
        }

        return array_keys($set);
    }

    /** Ringkasan per modul untuk daftar role: [['group'=>'Data Karyawan','levels'=>'Lihat, Kelola'], ...] */
    public static function summarize(array $granted): array
    {
        $have = array_flip($granted);
        $out = [];
        foreach (self::GROUPS as $group) {
            $levels = [];
            foreach ($group['permissions'] as $name => [$label, $short]) {
                if (isset($have[$name])) {
                    $levels[] = $short;
                }
            }
            if ($levels) {
                $out[] = ['group' => $group['label'], 'levels' => implode(', ', $levels)];
            }
        }

        return $out;
    }
}
