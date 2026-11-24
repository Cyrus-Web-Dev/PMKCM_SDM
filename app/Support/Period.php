<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/** Menerjemahkan pilihan periode (harian / mingguan / bulanan / tahunan) + tanggal acuan menjadi rentang tanggal. */
class Period
{
    public const LABELS = ['hari' => 'Harian', 'minggu' => 'Mingguan', 'bulan' => 'Bulanan', 'tahun' => 'Tahunan'];

    /**
     * @return array{type: string, type_label: string, date: string, from: string, to: string, label: string}
     */
    public static function resolve(?string $type, ?string $date, ?string $from = null, ?string $to = null): array
    {
        // Rentang kustom (dipakai dashboard): dari-sampai, maksimal 366 hari.
        if ($type === 'custom') {
            try {
                $f = $from ? Carbon::parse($from) : null;
                $t = $to ? Carbon::parse($to) : null;
            } catch (Throwable) {
                $f = $t = null;
            }

            if ($f && $t && $f->lte($t) && $f->diffInDays($t, true) <= 366) {
                return [
                    'type' => 'custom',
                    'type_label' => 'Kustom',
                    'date' => $f->toDateString(),
                    'from' => $f->toDateString(),
                    'to' => $t->toDateString(),
                    'label' => $f->format('d/m/Y').($f->isSameDay($t) ? '' : ' – '.$t->format('d/m/Y')),
                ];
            }
            $type = 'bulan';
        }

        $type = array_key_exists((string) $type, self::LABELS) ? (string) $type : 'bulan';

        try {
            $d = $date ? Carbon::parse($date) : Carbon::today();
        } catch (Throwable) {
            $d = Carbon::today();
        }

        [$from, $to] = match ($type) {
            'hari' => [$d->copy(), $d->copy()],
            'minggu' => [$d->copy()->startOfWeek(Carbon::MONDAY), $d->copy()->endOfWeek(Carbon::SUNDAY)],
            'tahun' => [$d->copy()->startOfYear(), $d->copy()->endOfYear()],
            default => [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()],
        };

        return [
            'type' => $type,
            'type_label' => self::LABELS[$type],
            'date' => $d->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'label' => $from->format('d/m/Y').($from->isSameDay($to) ? '' : ' – '.$to->format('d/m/Y')),
        ];
    }
}
