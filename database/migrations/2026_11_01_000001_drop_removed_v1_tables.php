<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDM V2: membuang tabel modul V1 yang tidak dipakai lagi (payroll, integrasi, rekrutmen, pelatihan,
 * dokumen, pengumuman, KPI lama). Aman dijalankan di database baru (tabel tidak ada) maupun di
 * database V1 yang sudah berisi data (DROP ... CASCADE membuang foreign key yang menempel).
 */
return new class extends Migration
{
    private array $tables = [
        'payroll_items', 'payroll_periods', 'payslip_mirrors',
        'integration_inbox', 'integration_deliveries', 'integration_clients',
        'job_applicants', 'job_vacancies',
        'training_participants', 'trainings',
        'employee_documents', 'announcements',
        'performance_reviews', 'performance_review_cycles',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $this->dropTable($table);
        }
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
        // Tabel yang dibuang tidak dipulihkan.
    }
};
