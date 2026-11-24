<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Pengaturan key-value yang bisa diubah Owner. Dibaca dari cache; menulis otomatis mengosongkan cache. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'settings.all';

    /** @return array<string,?string> */
    public static function values(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            return []; // tabel belum ada (migrasi belum dijalankan): pakai nilai bawaan
        }
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        $value = self::values()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function readInt(string $key, int $default): int
    {
        return (int) self::read($key, $default);
    }

    public static function write(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** Toleransi keterlambatan (menit) yang berlaku sekarang. */
    public static function lateTolerance(): int
    {
        return self::readInt('late_tolerance_minutes', (int) config('hr.late_tolerance_minutes'));
    }

    /** Batas diam (menit) sebelum otomatis logout; 0 = nonaktif. */
    public static function idleTimeout(): int
    {
        return self::readInt('idle_timeout_minutes', (int) config('hr.idle_timeout_minutes'));
    }
}
