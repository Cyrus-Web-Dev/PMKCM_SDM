<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            // menunggu -> disetujui / perlu_revisi (diputuskan Ka. URS)
            $table->string('review_status', 20)->default('menunggu')->index();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::create('daily_report_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('daily_report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('daily_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_comments');
        Schema::dropIfExists('daily_report_photos');
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'review_note', 'reviewed_at']);
        });
    }
};
