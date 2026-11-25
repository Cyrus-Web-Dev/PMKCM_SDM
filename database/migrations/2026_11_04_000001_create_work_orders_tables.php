<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perintah Kerja: satu kepala kerja + beberapa anggota + uraian + status. Yang berstatus "selesai" menjadi History Card.
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();           // PK-20261124-001
            $table->date('order_date');                        // tanggal perintah dibuat/dikeluarkan
            $table->date('start_date')->nullable();            // tanggal mulai pekerjaan
            $table->date('end_date')->nullable();              // tanggal selesai pekerjaan (diisi saat status selesai)
            $table->foreignId('leader_id')->constrained('employees')->restrictOnDelete(); // kepala kerja
            $table->text('description');                       // uraian pekerjaan
            $table->string('status', 20)->default('direncanakan'); // direncanakan, berjalan, selesai, dibatalkan
            $table->string('team', 30)->nullable();
            $table->string('area', 100)->nullable();           // area / peralatan (opsional)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('order_date');
            $table->index('end_date');
            $table->index('leader_id');
        });

        Schema::create('work_order_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unique(['work_order_id', 'employee_id']);
            $table->index('employee_id');
        });

        // Penilaian Anggota: kepala kerja menilai tiap anggota per perintah kerja (kriteria sama dengan penilaian bulanan).
        Schema::create('work_order_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('quality');
            $table->unsignedTinyInteger('discipline');
            $table->unsignedTinyInteger('teamwork');
            $table->unsignedTinyInteger('initiative');
            $table->unsignedTinyInteger('safety');
            $table->unsignedTinyInteger('total');
            $table->text('note')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['work_order_id', 'employee_id']);
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
        Schema::dropIfExists('work_order_ratings');
        Schema::dropIfExists('work_order_members');
        Schema::dropIfExists('work_orders');
    }
};
