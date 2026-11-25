<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Backup database PostgreSQL setiap hari jam 02:00 WIB (lihat app/Console/Commands/BackupDatabase.php).
Schedule::command('sdm:backup-database')->dailyAt('02:00');

// Pengingat laporan harian (16:30) & pembersihan notifikasi lama (Minggu 03:00).
Schedule::command('sdm:remind-reports')->dailyAt('16:30');
Schedule::command('sdm:prune-notifications')->weeklyOn(0, '03:00');
