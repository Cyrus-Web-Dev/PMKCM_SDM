<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permintaan klien: fitur Departemen & Jabatan dihapus dari seluruh aplikasi.
 * Aman dijalankan di PostgreSQL maupun MySQL/MariaDB, di database baru maupun yang sudah berisi data.
 */
return new class extends Migration
{
    // Tanpa transaksi pembungkus: di PostgreSQL satu perintah yang gagal (dalam try/catch) tidak boleh menggagalkan sisanya.
    public bool $withinTransaction = false;

    public function up(): void
    {
        // Indeks gabungan lama (employment_status, department_id) harus dilepas dulu sebelum kolomnya dibuang.
        try {
            Schema::table('employees', fn (Blueprint $t) => $t->dropIndex(['employment_status', 'department_id']));
        } catch (\Throwable) {
            // sudah tidak ada
        }

        foreach (['department_id', 'position_id'] as $column) {
            if (! Schema::hasColumn('employees', $column)) {
                continue;
            }
            try {
                Schema::table('employees', fn (Blueprint $t) => $t->dropConstrainedForeignId($column));
            } catch (\Throwable) {
                Schema::table('employees', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        foreach (['positions', 'departments'] as $table) {
            $this->dropTable($table);
        }

        DB::table('permissions')->where('name', 'like', 'departments.%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

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
        // Departemen & Jabatan yang dihapus tidak dipulihkan.
    }
};
