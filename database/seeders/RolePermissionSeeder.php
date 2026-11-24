<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Peta permission per modul. Format aksi: view (lihat), manage (tambah/ubah/hapus),
     * approve (menyetujui, khusus modul yg butuh approval berjenjang), export (unduh laporan).
     */
    public static array $modules = [
        'employees' => ['view', 'manage', 'export'],
        'departments' => ['view', 'manage'],
        'attendances' => ['view', 'manage'],
        'leaves' => ['view', 'manage', 'approve', 'approve_cuti'], // approve = izin (Owner/Ka. URS); approve_cuti = cuti (hanya Owner)
        'performance' => ['view', 'manage', 'email'], // penilaian outsourcing; "email" = tombol kirim hasil lewat email
        'daily_reports' => ['view', 'review'], // view = Semua Laporan; review = beri status/komentar (mengisi laporan sendiri tidak butuh izin)
        'dashboard' => ['analytics'], // dashboard grafik (Owner & Ka. URS)
        'shifts' => ['manage'], // pengaturan shift kerja
        'team' => ['all'], // melihat data SEMUA tim (tanpa ini manajer hanya melihat timnya sendiri)
        'holidays' => ['view', 'manage'], // hari libur perusahaan (melengkapi data libur nasional dari API)
        'reports' => ['view', 'export'],
        'users' => ['view', 'manage'], // kelola akun login & role
        'settings' => ['manage'],
    ];

    public function run(): void
    {
        $permissions = [];
        foreach (self::$modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // super-admin: akses penuh ke semua permission di atas.
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        // hr-admin: operasional HR sehari-hari. TIDAK termasuk pengaturan sistem, kelola pengguna/akun, persetujuan
        // cuti (hanya Owner), dan dashboard grafik (Owner & Ka. URS).
        $hrAdmin = Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
        $hrAdmin->syncPermissions(array_filter($permissions, fn ($p) => ! str_starts_with($p, 'settings.') && ! str_starts_with($p, 'users.') && $p !== 'leaves.approve_cuti' && $p !== 'dashboard.analytics'));

        // manajer: melihat/menilai/menyetujui HANYA tim bawahannya (pembatasan tim dilakukan di controller
        // lewat App\Support\TeamScope, BUKAN dari permission ini). TIDAK boleh melihat gaji/payroll &
        // dokumen pribadi seluruh karyawan.
        $manajer = Role::firstOrCreate(['name' => 'manajer', 'guard_name' => 'web']);
        $manajer->syncPermissions([
            'employees.view', 'departments.view', 'attendances.view',
            'leaves.view', 'leaves.approve', 'performance.view', 'performance.manage',
            'holidays.view', 'reports.view', 'daily_reports.view', 'daily_reports.review', 'performance.email',
        ]);

        // ka-urs: kepala unit yang menilai outsourcing & memantau laporan SEMUA tim (izin team.all),
        // tanpa hak mengubah data karyawan/akun. Sama dengan peran "Ka. URS" pada template PMKCM V.
        $kaUrs = Role::firstOrCreate(['name' => 'ka-urs', 'guard_name' => 'web']);
        $kaUrs->syncPermissions([
            'employees.view', 'departments.view', 'attendances.view',
            'leaves.view', 'leaves.approve', 'performance.view', 'performance.manage', 'performance.email',
            'daily_reports.view', 'daily_reports.review', 'dashboard.analytics', 'reports.view', 'reports.export', 'shifts.manage', 'team.all', 'holidays.view',
        ]);

        // karyawan: SELF-SERVICE saja. Sengaja TIDAK diberi izin "*.view" modul manapun, karena izin itu
        // membuka halaman ADMIN yang berisi data seluruh karyawan. Halaman self-service (laporan harian, absen saya,
        // cuti saya, nilai saya, dst) tidak butuh izin khusus - cukup login + terhubung ke data karyawan.
        $karyawan = Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'web']);
        $karyawan->syncPermissions(['holidays.view']);
    }
}
