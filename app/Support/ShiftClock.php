<?php

namespace App\Support;

use App\Models\Shift;
use Carbon\Carbon;

/** Perhitungan waktu berbasis shift, termasuk shift malam yang melewati tengah malam. */
class ShiftClock
{
    /** @return array{0:int,1:int} [menit-dari-tengah-malam mulai, selesai] */
    private static function minutes(Shift $shift): array
    {
        [$sh, $sm] = array_map('intval', explode(':', $shift->start_time));
        [$eh, $em] = array_map('intval', explode(':', $shift->end_time));

        return [$sh * 60 + $sm, $eh * 60 + $em];
    }

    /**
     * Jendela shift untuk waktu $now.
     * Shift malam (selesai <= mulai): bila $now masih sebelum jam selesai, shift tsb dimulai KEMARIN,
     * sehingga tanggal absensinya = tanggal kemarin.
     *
     * @return array{0: Carbon, 1: Carbon} [tanggal shift (00:00), waktu mulai shift]
     */
    public static function window(Shift $shift, Carbon $now): array
    {
        [$startMin, $endMin] = self::minutes($shift);
        $start = $now->copy()->setTime(intdiv($startMin, 60), $startMin % 60, 0);

        $overnight = $endMin <= $startMin;
        if ($overnight && ($now->hour * 60 + $now->minute) < $endMin) {
            $start->subDay();
        }

        return [$start->copy()->startOfDay(), $start];
    }

    /** Menit keterlambatan (0 bila belum melewati jam mulai). */
    public static function lateMinutes(Carbon $start, Carbon $now): int
    {
        return $now->greaterThan($start) ? (int) floor($start->diffInMinutes($now, true)) : 0;
    }

    /** Shift yang jam mulainya paling dekat dengan $now (untuk pilihan bawaan di formulir absen). */
    public static function nearest(iterable $shifts, Carbon $now): ?Shift
    {
        $best = null;
        $bestDiff = PHP_INT_MAX;

        foreach ($shifts as $shift) {
            [, $start] = self::window($shift, $now);
            $diff = abs((int) $now->diffInMinutes($start, false));
            if ($diff < $bestDiff) {
                $best = $shift;
                $bestDiff = $diff;
            }
        }

        return $best;
    }
}
