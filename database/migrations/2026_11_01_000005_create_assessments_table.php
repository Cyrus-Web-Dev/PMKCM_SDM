<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('period'); // selalu tanggal 1 pada bulan yang dinilai
            $table->unsignedTinyInteger('quality');    // Kualitas Kerja   (0-100)
            $table->unsignedTinyInteger('discipline'); // Kedisiplinan     (0-100)
            $table->unsignedTinyInteger('teamwork');   // Kerja Sama       (0-100)
            $table->unsignedTinyInteger('initiative'); // Inisiatif        (0-100)
            $table->unsignedTinyInteger('safety');     // Kepatuhan K3     (0-100)
            $table->unsignedTinyInteger('total');      // rata-rata kelima kriteria, dibulatkan
            $table->text('note')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'period']); // satu nilai per karyawan per bulan
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
