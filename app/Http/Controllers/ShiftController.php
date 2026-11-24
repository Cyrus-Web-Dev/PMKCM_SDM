<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notify;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Pengaturan shift kerja (dipakai di formulir Laporan Harian). */
class ShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:shifts.manage');
    }

    public function index()
    {
        return Inertia::render('Shifts/Index', [
            'shifts' => Shift::orderBy('id')->get()->map(fn (Shift $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $shift = Shift::create($this->validated($request));
        ActivityLogger::log('shift.created', "Menambah shift: {$shift->name}", $shift, $request->user()->id);

        return back()->with('success', 'Shift ditambahkan.');
    }

    public function update(Request $request, Shift $shift)
    {
        $data = $this->validated($request);
        $changed = substr($shift->start_time, 0, 5) !== $data['start_time'] || substr($shift->end_time, 0, 5) !== $data['end_time'];
        $shift->update($data);
        ActivityLogger::log('shift.updated', "Mengubah shift: {$shift->name}", $shift, $request->user()->id, ActivityLogger::changes($shift) ?: null);

        if ($changed) {
            // Jam shift berubah: beri tahu semua karyawan yang punya akun aktif.
            User::where('status', 'active')->whereHas('employee')->pluck('id')->each(fn ($id) => Notify::user($id, 'Jam shift berubah', "{$shift->name} kini {$data['start_time']} - {$data['end_time']}.", route('attendances.my-history', [], false)));
        }

        return back()->with('success', 'Shift diperbarui.');
    }

    public function destroy(Request $request, Shift $shift)
    {
        $name = $shift->name;
        $shift->delete(); // laporan lama tetap utuh: nama shift sudah disalin ke daily_reports.shift_name
        ActivityLogger::log('shift.deleted', "Menghapus shift: {$name}", null, $request->user()->id);

        return back()->with('success', 'Shift dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ]);
    }
}
