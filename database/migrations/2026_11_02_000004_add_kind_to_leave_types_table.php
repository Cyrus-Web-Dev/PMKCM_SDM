<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            // izin = tidak masuk karena sakit/keperluan (disetujui Owner atau Ka. URS);
            // cuti = cuti tahunan/panjang (hanya disetujui Owner).
            $table->string('kind', 10)->default('cuti');
        });

        DB::table('leave_types')->whereRaw('lower(name) like ?', ['%sakit%'])->orWhereRaw('lower(name) like ?', ['%izin%'])->update(['kind' => 'izin']);
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
