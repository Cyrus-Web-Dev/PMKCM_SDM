<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pencegah data ganda: tiap kiriman dari antrean offline membawa ID unik; ID yang sudah diproses tidak diproses lagi.
        Schema::create('offline_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_offline')->default(false);       // dicatat saat offline lalu disinkron
            $table->timestamp('device_time')->nullable();         // waktu di perangkat saat absen
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['is_offline', 'device_time']);
        });
        Schema::dropIfExists('offline_syncs');
    }
};
