<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

/**
 * Check-in / check-out TIDAK tersedia lewat API: absensi wajib foto kamera realtime + shift dan hanya
 * bisa dilakukan dari aplikasi web (supaya aturan anti-kecurangan tidak bisa dilewati).
 */
class AttendanceController extends Controller
{
    public function history(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('month')) ? $request->input('month') : today()->format('Y-m');

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereRaw("to_char(date, 'YYYY-MM') = ?", [$month])
            ->orderBy('date')
            ->get(['id', 'date', 'check_in', 'check_out', 'status', 'late_minutes', 'shift_name', 'notes']);

        return response()->json(['data' => $attendances, 'month' => $month]);
    }
}
