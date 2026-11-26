<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permintaan klien: modul Absensi, Cuti/Izin, Hari Libur, Shift Kerja, dan halaman Pengaturan dihapus.
 * Aman dijalankan di database baru maupun database yang sudah berisi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Laporan harian tidak lagi memakai shift.
        if (Schema::hasTable('daily_reports')) {
            if (Schema::hasColumn('daily_reports', 'shift_id')) {
                Schema::table('daily_reports', fn (Blueprint $t) => $t->dropConstrainedForeignId('shift_id'));
            }
            if (Schema::hasColumn('daily_reports', 'shift_name')) {
                Schema::table('daily_reports', fn (Blueprint $t) => $t->dropColumn('shift_name'));
            }
        }

        foreach (['attendances', 'leaves', 'leave_balances', 'leave_types', 'company_holidays', 'shifts', 'settings'] as $table) {
            $this->dropTable($table);
        }

        // Hak akses milik modul yang dihapus (role_has_permissions ikut terhapus lewat cascade).
        DB::table('permissions')->where(function ($q) {
            $q->where('name', 'like', 'attendances.%')->orWhere('name', 'like', 'leaves.%')
                ->orWhere('name', 'like', 'holidays.%')->orWhere('name', 'like', 'shifts.%');
        })->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** PostgreSQL: DROP ... CASCADE. MySQL/MariaDB: matikan pemeriksaan foreign key sementara lalu drop. */
    private function dropTable(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP TABLE IF EXISTS \"{$table}\" CASCADE");

            return;
        }

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists($table);
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Modul yang dihapus tidak dipulihkan.
    }
};
