<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('check_in_photo')->nullable();  // wajib saat check-in (kamera realtime)
            $table->string('check_out_photo')->nullable(); // opsional saat check-out
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('shift_name', 60)->nullable();  // salinan nama shift saat check-in
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['check_in_photo', 'check_out_photo', 'shift_name']);
        });
    }
};
