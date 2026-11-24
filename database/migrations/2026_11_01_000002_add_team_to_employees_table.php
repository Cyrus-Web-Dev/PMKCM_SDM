<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Tim kerja di pabrik: Gudang, Cooler, Kiln, Coal Mill (daftar di config/hr.php -> teams).
            $table->string('team', 30)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('team');
        });
    }
};
