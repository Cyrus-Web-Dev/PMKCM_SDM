<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Aman dijalankan berulang: izin & role bawaan hanya DIBUAT bila belum ada. Pengaturan hak akses yang sudah
     * diubah Owner lewat menu "Role & Hak Akses" TIDAK ditimpa. Pengecualian: super-admin selalu memegang semua izin.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = PermissionCatalog::names();
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        // Buang izin lama yang sudah tidak ada di katalog (mis. modul absensi/cuti yang dihapus).
        Permission::whereNotIn('name', $names)->delete();

        // Owner: akses penuh, tidak bisa diubah dari antarmuka.
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web'])->syncPermissions($names);

        foreach (PermissionCatalog::DEFAULT_ROLES as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        }
    }
}
