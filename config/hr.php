<?php

return [

    // Toleransi keterlambatan (menit) sebelum status absensi otomatis "terlambat".
    // Nilai bawaan; Owner bisa mengubahnya dari menu Pengaturan (disimpan di database).
    'late_tolerance_minutes' => (int) env('SDM_LATE_TOLERANCE_MINUTES', 10),
    // Auto-logout setelah diam sekian menit (0 = nonaktif). Cocok untuk komputer bersama di pabrik.
    'idle_timeout_minutes' => (int) env('SDM_IDLE_TIMEOUT_MINUTES', 30),
    'work_start_time' => env('SDM_WORK_START_TIME', '08:00'),
    'work_end_time' => env('SDM_WORK_END_TIME', '17:00'),

    // Tim kerja di pabrik (dipakai di data karyawan, Laporan Harian, dan Rekap). Ubah di sini bila ada tim baru.
    'teams' => ['Gudang', 'Cooler', 'Kiln', 'Coal Mill'],

    /*
    |--------------------------------------------------------------------------
    | Hari kerja & hari libur
    |--------------------------------------------------------------------------
    */
    // 5 = Senin-Jumat (Sabtu & Minggu libur), 6 = Senin-Sabtu (hanya Minggu libur).
    'work_days_per_week' => (int) env('SDM_WORK_DAYS_PER_WEEK', 5),

    'holidays' => [
        // Ambil hari libur nasional & cuti bersama dari dataset publik (bukan API resmi pemerintah).
        // Bila mati/gagal, otomatis pakai cache terakhir lalu file cadangan bawaan.
        'enabled' => (bool) env('SDM_HOLIDAY_API', true),
        'source_url' => env('SDM_HOLIDAY_SOURCE_URL', 'https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.min.json'),
        'cache_hours' => (int) env('SDM_HOLIDAY_CACHE_HOURS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geofencing absensi (opsional). Kosongkan SDM_OFFICE_LAT/LNG untuk menonaktifkan.
    |--------------------------------------------------------------------------
    */
    'office' => [
        'lat' => env('SDM_OFFICE_LAT') !== null && env('SDM_OFFICE_LAT') !== '' ? (float) env('SDM_OFFICE_LAT') : null,
        'lng' => env('SDM_OFFICE_LNG') !== null && env('SDM_OFFICE_LNG') !== '' ? (float) env('SDM_OFFICE_LNG') : null,
        'radius_m' => (int) env('SDM_OFFICE_RADIUS_M', 200),
        // true = check-in di luar radius DITOLAK; false = tetap diterima tapi ditandai di catatan.
        'enforce' => (bool) env('SDM_GEOFENCE_ENFORCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifikasi tambahan (opsional, semuanya nonaktif bila token kosong)
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'whatsapp' => [ // Fonnte - gateway WhatsApp pihak ketiga (bukan WhatsApp Cloud API resmi)
            'token' => env('FONNTE_TOKEN'),
            'admin_number' => env('SDM_WHATSAPP_ADMIN_NUMBER'),
        ],
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        ],
    ],

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
