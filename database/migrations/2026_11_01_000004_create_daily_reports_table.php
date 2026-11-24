<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('report_date');
            $table->string('team', 30)->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('shift_name', 60)->nullable(); // salinan nama shift saat laporan dibuat (tetap utuh walau shift diubah/dihapus)
            $table->string('area', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->text('task');
            $table->text('issue')->nullable();
            $table->string('status', 20)->default('selesai'); // selesai, proses, kendala
            $table->timestamps();

            $table->index('report_date');
            $table->index(['employee_id', 'report_date']);
            $table->index('team');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
