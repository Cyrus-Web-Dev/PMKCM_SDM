<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsToSync;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Setting;
use App\Models\Shift;
use App\Services\ActivityLogger;
use App\Services\Notify;
use App\Support\Geofence;
use App\Support\ImageProcessor;
use App\Support\ShiftClock;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    use RespondsToSync;

    public function __construct()
    {
        $this->middleware('permission:attendances.view')->only(['index']);
        $this->middleware('permission:attendances.manage')->only(['update']);
    }

    public function index(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date'], 'department_id' => ['nullable', 'integer']]);
        $date = $request->input('date', today()->toDateString());

        $query = TeamScope::constrain(
            Attendance::query()->with('employee:id,full_name,nip,department_id')->has('employee')->whereDate('date', $date),
            $request->user()
        );

        if ($departmentId = $request->input('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        return Inertia::render('Attendances/Index', [
            'attendances' => $query->orderBy('check_in')->get()->map(fn (Attendance $a) => $this->row($a)),
            'date' => $date,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('department_id'),
        ]);
    }

    /**
     * Check-in: WAJIB foto dari kamera realtime + pilih shift. Terlambat = lewat jam mulai shift + toleransi
     * (toleransi diatur Owner di menu Pengaturan). Tanggal absensi mengikuti tanggal MULAI shift (shift malam
     * yang check-in lewat tengah malam tetap tercatat di tanggal shift dimulai).
     */
    public function checkIn(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'captured_at' => ['nullable', 'date'],
        ], ['photo.required' => 'Foto check-in wajib diambil langsung dari kamera.']);

        [$now, $offline] = $this->resolveTime($request);
        if (! $now) {
            return $this->failed($request, 'Waktu perangkat tidak valid (terlalu lama atau di masa depan). Periksa jam di perangkat lalu coba lagi.');
        }
        $shift = Shift::findOrFail($data['shift_id']);
        [$date, $start] = ShiftClock::window($shift, $now);

        $existing = Attendance::where('employee_id', $employee->id)->whereDate('date', $date->toDateString())->first();
        if ($existing && $existing->check_in) {
            return $this->failed($request, 'Kamu sudah check-in untuk shift tanggal '.$date->format('d/m/Y').'.');
        }
        // Ditandai izin/sakit/cuti/libur -> tidak perlu check-in. (Status "alpha" otomatis boleh ditimpa check-in.)
        if ($existing && in_array($existing->status, ['izin', 'sakit', 'cuti', 'libur'], true)) {
            return $this->failed($request, "Tanggal {$date->format('d/m/Y')} tercatat sebagai \"{$existing->status}\", tidak perlu check-in.");
        }

        $geo = Geofence::evaluate(isset($data['lat']) ? (float) $data['lat'] : null, isset($data['lng']) ? (float) $data['lng'] : null);
        if (! $geo['ok']) {
            return $this->failed($request, $geo['message']);
        }

        $late = ShiftClock::lateMinutes($start, $now);
        $status = $late > Setting::lateTolerance() ? 'terlambat' : 'hadir';

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date->toDateString()],
            [
                'check_in' => $now->format('H:i:s'),
                'check_in_photo' => $this->storePhoto($request->file('photo')),
                'check_in_lat' => $data['lat'] ?? null,
                'check_in_lng' => $data['lng'] ?? null,
                'shift_id' => $shift->id,
                'shift_name' => $shift->name,
                'status' => $status,
                'late_minutes' => $late,
                'is_offline' => $offline,
                'device_time' => $offline ? $now : null,
                'notes' => trim(($geo['note'] ?? '').($offline ? ' Offline: dicatat di perangkat '.$now->format('H:i').', disinkron '.now()->format('H:i').'.' : '')) ?: null,
            ]
        );

        ActivityLogger::log('attendance.check_in', "{$employee->full_name} check-in {$now->format('H:i')} ({$shift->name}, {$status})", $attendance, $request->user()->id);

        if ($offline) {
            Notify::permission('attendances.view', 'Absen offline disinkronkan', "{$employee->full_name}: check-in dicatat offline pukul {$now->format('H:i')} (waktu perangkat), disinkron pukul ".now()->format('H:i').'. Mohon periksa.', route('attendances.index', ['date' => $date->toDateString()], false), $employee, $request->user()->id);
        }

        if ($status === 'terlambat') {
            Notify::permission('attendances.view', 'Karyawan terlambat', "{$employee->full_name} check-in terlambat {$late} menit ({$shift->name}).", route('attendances.index', ['date' => $date->toDateString()], false), $employee, $request->user()->id);
        }

        return $this->done($request, 'Check-in tercatat pukul '.$now->format('H:i').' - '.($status === 'terlambat' ? "Terlambat {$late} menit." : 'Tepat waktu.').($offline ? ' (dicatat offline, waktu dari perangkat)' : ''));
    }

    /** Check-out: foto opsional. Mengisi check-in terbuka terakhir (hari ini atau kemarin untuk shift malam). */
    public function checkOut(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        $data = $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'captured_at' => ['nullable', 'date'],
        ]);

        [$now, $offline] = $this->resolveTime($request);
        if (! $now) {
            return $this->failed($request, 'Waktu perangkat tidak valid (terlalu lama atau di masa depan). Periksa jam di perangkat lalu coba lagi.');
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereNotNull('check_in')->whereNull('check_out')
            ->whereDate('date', '>=', today()->subDay()->toDateString())
            ->orderByDesc('date')->first();

        if (! $attendance) {
            return $this->failed($request, 'Tidak ada check-in yang menunggu check-out.');
        }

        $in = Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
        if ($now->lt($in)) {
            return $this->failed($request, 'Waktu check-out lebih awal dari check-in. Periksa jam di perangkat.');
        }

        $attendance->update([
            'is_offline' => $attendance->is_offline || $offline,
            'check_out' => $now->format('H:i:s'),
            'check_out_photo' => $request->hasFile('photo') ? $this->storePhoto($request->file('photo')) : null,
            'check_out_lat' => $data['lat'] ?? null,
            'check_out_lng' => $data['lng'] ?? null,
        ]);

        $minutes = (int) floor($in->diffInMinutes($now, true));
        ActivityLogger::log('attendance.check_out', "{$employee->full_name} check-out {$now->format('H:i')}", $attendance, $request->user()->id);

        return $this->done($request, 'Check-out tercatat pukul '.$now->format('H:i').sprintf('. Total kerja %d jam %d menit.', intdiv($minutes, 60), $minutes % 60).($offline ? ' (dicatat offline, waktu dari perangkat)' : ''));
    }

    /** Admin mengoreksi manual (mis. lupa absen, izin lisan). */
    public function update(Request $request, Attendance $attendance)
    {
        abort_unless($attendance->employee, 404);
        TeamScope::assertCanAct($request->user(), $attendance->employee);

        $data = $request->validate([
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:hadir,terlambat,izin,sakit,alpha,cuti,wfh,libur',
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance->update($data);
        ActivityLogger::log('attendance.corrected', "Mengoreksi absensi {$attendance->employee->full_name} tgl {$attendance->date->format('d/m/Y')}", $attendance, auth()->id(), ActivityLogger::changes($attendance) ?: null);

        return back()->with('success', 'Absensi berhasil diperbarui.');
    }

    public function myHistory(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('month')) ? $request->input('month') : today()->format('Y-m');
        $shifts = Shift::orderBy('id')->get();

        $open = Attendance::where('employee_id', $employee->id)
            ->whereNotNull('check_in')->whereNull('check_out')
            ->whereDate('date', '>=', today()->subDay()->toDateString())
            ->orderByDesc('date')->first();
        $todayRecord = Attendance::where('employee_id', $employee->id)->whereDate('date', today())->first();

        return Inertia::render('Attendances/MyHistory', [
            'attendances' => Attendance::where('employee_id', $employee->id)
                ->whereRaw("to_char(date, 'YYYY-MM') = ?", [$month])
                ->orderBy('date')->get()->map(fn (Attendance $a) => $this->row($a)),
            'month' => $month,
            'shifts' => $shifts->map(fn (Shift $s) => ['id' => $s->id, 'name' => $s->name, 'start' => substr($s->start_time, 0, 5), 'end' => substr($s->end_time, 0, 5)])->values(),
            'suggestedShift' => ShiftClock::nearest($shifts, now())?->id,
            'open' => $open ? $this->row($open) : null,
            'todayRecord' => $todayRecord ? $this->row($todayRecord) : null,
            'tolerance' => Setting::lateTolerance(),
            'employeeName' => $employee->full_name,
        ]);
    }

    /** Foto absen: hanya pemilik, atau yang berwenang atas karyawan tsb (attendances.view + TeamScope). */
    public function photo(Request $request, Attendance $attendance, string $type)
    {
        abort_unless(in_array($type, ['in', 'out'], true), 404);
        abort_unless($attendance->employee, 404);

        $user = $request->user();
        $own = $user->employee && $attendance->employee_id === $user->employee->id;
        if (! $own) {
            abort_unless($user->can('attendances.view'), 403);
            TeamScope::assertCanAct($user, $attendance->employee);
        }

        $path = $type === 'in' ? $attendance->check_in_photo : $attendance->check_out_photo;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function row(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'date' => $a->date->toDateString(),
            'check_in' => $a->check_in,
            'check_out' => $a->check_out,
            'status' => $a->status,
            'late_minutes' => $a->late_minutes,
            'notes' => $a->notes,
            'shift_name' => $a->shift_name,
            'has_in_photo' => (bool) $a->check_in_photo,
            'has_out_photo' => (bool) $a->check_out_photo,
            'is_offline' => (bool) $a->is_offline,
            'device_time' => $a->device_time?->format('H:i'),
            'employee' => $a->relationLoaded('employee') && $a->employee
                ? ['id' => $a->employee->id, 'full_name' => $a->employee->full_name, 'nip' => $a->employee->nip]
                : null,
        ];
    }

    /**
     * Waktu yang dipakai untuk absen. Normalnya waktu SERVER. Untuk kiriman dari antrean offline
     * (header X-Offline-Id + captured_at) dipakai waktu perangkat saat absen dilakukan, dengan batas:
     * tidak boleh di masa depan (>5 menit) dan tidak lebih tua dari 36 jam; selisih < 2 menit dianggap
     * kiriman biasa. Absen semacam ini ditandai "Offline" dan Ka. URS/Owner diberi notifikasi.
     *
     * @return array{0: ?Carbon, 1: bool} [waktu, offline?]; waktu null = tidak valid
     */
    private function resolveTime(Request $request): array
    {
        $server = now();
        if (! $request->filled('captured_at') || ! $request->header('X-Offline-Id')) {
            return [$server, false];
        }

        try {
            $captured = Carbon::parse($request->input('captured_at'))->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return [null, false];
        }

        if ($captured->gt($server->copy()->addMinutes(5)) || $captured->lt($server->copy()->subHours(36))) {
            return [null, false];
        }
        if ($captured->diffInMinutes($server, true) < 2) {
            return [$server, false];
        }

        return [$captured, true];
    }

    /** Simpan ulang sebagai JPEG (metadata dibuang, maks. 1280 px) di disk privat. */
    private function storePhoto(UploadedFile $file): string
    {
        $binary = ImageProcessor::jpeg($file->getRealPath()) ?? file_get_contents($file->getRealPath());
        $path = 'attendance-photos/'.Str::random(28).'.jpg';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
