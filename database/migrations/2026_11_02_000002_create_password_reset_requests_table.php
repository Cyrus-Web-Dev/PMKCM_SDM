<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Lupa password" tanpa email: karyawan mengajukan permintaan, Owner yang mereset secara manual.
        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = email tidak dikenal
            $table->string('email', 255);
            $table->string('requester_name', 150)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device', 64)->nullable(); // sidik jari ringan: sha1(ip + user-agent)
            $table->string('status', 20)->default('pending'); // pending, done, dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['email', 'created_at']);
            $table->index(['device', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
