<?php

return [

    // Auto-logout setelah diam sekian menit (0 = nonaktif). Cocok untuk komputer bersama di pabrik.
    'idle_timeout_minutes' => (int) env('SDM_IDLE_TIMEOUT_MINUTES', 30),

    'security' => [
        // off | report-only | enforce  (Content-Security-Policy). Mulai dari report-only.
        'csp' => env('SDM_CSP', 'report-only'),
        'force_https' => (bool) env('SDM_FORCE_HTTPS', false),
    ],

    // Akun super-admin awal, dibuat oleh `php artisan migrate --seed` bila kedua variabel terisi.
    'admin' => [
        'name' => env('SDM_ADMIN_NAME', 'Administrator SDM'),
        'email' => env('SDM_ADMIN_EMAIL'),
        'password' => env('SDM_ADMIN_PASSWORD'),
    ],

    'backup' => [
        'keep' => (int) env('SDM_BACKUP_KEEP', 10),
        'pg_dump_path' => env('SDM_PG_DUMP_PATH'),
        'mysqldump_path' => env('SDM_MYSQLDUMP_PATH'),
    ],

];
