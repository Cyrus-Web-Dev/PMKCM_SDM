<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pembeda sintaks SQL agar aplikasi jalan di PostgreSQL (lokal) maupun MySQL/MariaDB (hosting cPanel).
 */
class Sql
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /** Operator LIKE yang tidak membedakan huruf besar/kecil. (MySQL/MariaDB sudah begitu secara bawaan.) */
    public static function like(): string
    {
        return self::driver() === 'pgsql' ? 'ilike' : 'like';
    }

    /** Bungkus kata pencarian jadi pola "%kata%" dengan karakter khusus LIKE di-escape. */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';
    }

    /**
     * Ekspresi SQL total jam kerja dari kolom time start_time & end_time: selisih end-start, ditambah 24 jam bila
     * melewati tengah malam atau sama persis (aturan yang sama dengan template PMKCM V).
     */
    public static function hours(): string
    {
        $diff = self::driver() === 'pgsql'
            ? 'extract(epoch from (end_time - start_time))'
            : 'time_to_sec(timediff(end_time, start_time))';

        return "case when end_time > start_time then {$diff} else {$diff} + 86400 end / 3600.0";
    }
}
