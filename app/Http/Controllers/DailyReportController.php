<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsToSync;
use App\Models\DailyReport;
use App\Models\DailyReportPhoto;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notify;
use App\Support\Exporter;
use App\Support\ImageProcessor;
use App\Support\Period;
use App\Support\TeamScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DailyReportController extends Controller
{
    use RespondsToSync;

    private const MAX_PHOTOS = 3;

    public function __construct()
    {
        $this->middleware('permission:daily_reports.view')->only(['all', 'export']);
    }

    /** Laporan harian milik sendiri: formulir pengisian + riwayat per bulan. */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('month')) ? $request->input('month') : today()->format('Y-m');
        $first = Carbon::parse($month.'-01');

        $reports = DailyReport::where('employee_id', $employee->id)
            ->withCount(['photos', 'comments'])
            ->whereBetween('report_date', [$first->toDateString(), $first->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('report_date')->orderByDesc('id')->get()
            ->map(fn (DailyReport $r) => $r->toRow() + [
                'can_delete' => $r->report_date->greaterThanOrEqualTo(today()->subDays(7)),
                'can_edit' => $this->canEdit($r),
                'review_note' => $r->review_note,
            ]);

        return Inertia::render('DailyReports/Index', [
            'reports' => $reports,
            'month' => $month,
            'shifts' => $this->shifts(),
            'defaultTeam' => $employee->team,
            'today' => today()->toDateString(),
            'employeeName' => $employee->full_name,
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        $data = $request->validate($this->rules($request) + [
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
        ], $this->messages());

        $shift = ! empty($data['shift_id']) ? Shift::find($data['shift_id']) : null;
        $files = $request->file('photos', []);

        $report = DailyReport::create(Arr::except($data, ['photos']) + [
            'employee_id' => $employee->id,
            'shift_name' => $shift?->name ?? 'Manual',
        ]);
        $this->savePhotos($report, $files);

        ActivityLogger::log('daily_report.created', "Laporan harian {$report->report_date->format('d/m/Y')} - {$report->area}", $report, $request->user()->id);

        if ($report->status === 'kendala') {
            Notify::permission('daily_reports.review', 'Laporan berkendala', "{$employee->full_name}: {$report->area}".($report->issue ? " - {$report->issue}" : ''), route('daily-reports.all', ['period' => 'hari', 'date' => $report->report_date->toDateString()], false), $employee, $request->user()->id);
        }

        return $this->done($request, 'Laporan harian tersimpan.', 201);
    }

    /** Revisi: pemilik boleh mengubah laporan yang ditandai "Perlu Revisi" atau yang dibuat < 24 jam lalu. */
    public function update(Request $request, DailyReport $dailyReport)
    {
        abort_unless($this->canEdit($dailyReport) && $this->isOwner($request->user(), $dailyReport), 403, 'Laporan ini tidak bisa diubah lagi.');

        $data = $request->validate($this->rules($request, $dailyReport->photos()->count()), $this->messages());
        $newFiles = $request->file('photos', []);

        if ($dailyReport->photos()->count() + count($newFiles) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photos' => 'Maksimal '.self::MAX_PHOTOS.' foto per laporan.']);
        }

        $wasRevision = $dailyReport->review_status === 'perlu_revisi';
        $dailyReport->update(Arr::except($data, ['photos']) + ['review_status' => 'menunggu']);
        $this->savePhotos($dailyReport, $newFiles);

        ActivityLogger::log('daily_report.updated', "Merevisi laporan harian {$dailyReport->report_date->format('d/m/Y')} - {$dailyReport->area}", $dailyReport, $request->user()->id);

        if ($wasRevision && $dailyReport->employee) {
            Notify::permission('daily_reports.review', 'Laporan sudah direvisi', "{$dailyReport->employee->full_name} memperbarui laporan {$dailyReport->report_date->format('d/m/Y')}.", route('daily-reports.all', ['period' => 'hari', 'date' => $dailyReport->report_date->toDateString()], false), $dailyReport->employee, $request->user()->id);
        }

        return back()->with('success', 'Laporan diperbarui dan dikirim ulang untuk direview.');
    }

    /** Hapus laporan: pemilik (maks. 7 hari setelah tanggal laporan) atau admin pemegang employees.manage. */
    public function destroy(Request $request, DailyReport $dailyReport)
    {
        $user = $request->user();
        abort_unless(
            ($this->isOwner($user, $dailyReport) && $dailyReport->report_date->greaterThanOrEqualTo(today()->subDays(7))) || $user->can('employees.manage'),
            403,
            'Laporan ini tidak bisa dihapus.'
        );

        $label = "{$dailyReport->report_date->format('d/m/Y')} - {$dailyReport->area}";
        foreach ($dailyReport->photos as $photo) {
            Storage::disk('local')->delete($photo->path);
        }
        $dailyReport->delete(); // baris foto & komentar ikut terhapus (cascade)
        ActivityLogger::log('daily_report.deleted', "Menghapus laporan harian {$label}", null, $user->id);

        return back()->with('success', 'Laporan dihapus.');
    }

    /** Detail laporan (JSON untuk jendela detail): foto, komentar, dan status review. */
    public function detail(Request $request, DailyReport $dailyReport)
    {
        $user = $request->user();
        $this->authorizeView($user, $dailyReport);
        $dailyReport->load(['employee:id,full_name,nip', 'photos', 'comments.author:id,name', 'reviewer:id,name']);

        return response()->json([
            'report' => $dailyReport->toRow(true),
            'review_note' => $dailyReport->review_note,
            'reviewer' => $dailyReport->reviewer?->name,
            'reviewed_at' => $dailyReport->reviewed_at?->format('d/m/Y H:i'),
            'photos' => $dailyReport->photos->map(fn (DailyReportPhoto $p) => ['id' => $p->id, 'url' => route('daily-reports.photos.show', $p->id)])->values(),
            'comments' => $dailyReport->comments->map(fn ($c) => [
                'id' => $c->id,
                'author' => $c->author?->name ?? 'Pengguna dihapus',
                'body' => $c->body,
                'at' => $c->created_at->format('d/m/Y H:i'),
                'mine' => $c->user_id === $user->id,
            ])->values(),
            'can_review' => $this->canReview($user, $dailyReport),
        ]);
    }

    public function photo(Request $request, DailyReportPhoto $photo)
    {
        $this->authorizeView($request->user(), $photo->report);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Komentar dua arah antara pemilik laporan dan Ka. URS / atasan yang berwenang. */
    public function comment(Request $request, DailyReport $dailyReport)
    {
        $user = $request->user();
        $this->authorizeView($user, $dailyReport);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $dailyReport->comments()->create(['user_id' => $user->id, 'body' => $data['body']]);
        ActivityLogger::log('daily_report.commented', "Komentar pada laporan {$dailyReport->report_date->format('d/m/Y')}", $dailyReport, $user->id);

        $url = route('daily-reports.index', [], false);
        if ($this->isOwner($user, $dailyReport)) {
            $dailyReport->reviewed_by
                ? Notify::user($dailyReport->reviewed_by, 'Komentar baru pada laporan', "{$user->name}: {$data['body']}", route('daily-reports.all', ['period' => 'hari', 'date' => $dailyReport->report_date->toDateString()], false))
                : Notify::permission('daily_reports.review', 'Komentar baru pada laporan', "{$user->name}: {$data['body']}", route('daily-reports.all', ['period' => 'hari', 'date' => $dailyReport->report_date->toDateString()], false), $dailyReport->employee, $user->id);
        } else {
            Notify::user($dailyReport->employee?->user_id, 'Komentar dari penilai', "{$user->name}: {$data['body']}", $url);
        }

        return response()->json(['ok' => true], 201);
    }

    /** Ka. URS memberi status review: Disetujui atau Perlu Revisi (+ catatan). */
    public function review(Request $request, DailyReport $dailyReport)
    {
        $user = $request->user();
        abort_unless($this->canReview($user, $dailyReport), 403, 'Kamu tidak berwenang me-review laporan ini.');

        $data = $request->validate([
            'review_status' => ['required', Rule::in(['disetujui', 'perlu_revisi'])],
            'note' => ['required_if:review_status,perlu_revisi', 'nullable', 'string', 'max:1000'],
        ], ['note.required_if' => 'Isi catatan revisi supaya karyawan tahu apa yang perlu diperbaiki.']);

        $dailyReport->update([
            'review_status' => $data['review_status'],
            'review_note' => $data['note'] ?? null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $label = DailyReport::REVIEW_STATUSES[$data['review_status']];
        ActivityLogger::log('daily_report.reviewed', "Review laporan {$dailyReport->report_date->format('d/m/Y')}: {$label}", $dailyReport, $user->id);
        Notify::user($dailyReport->employee?->user_id, "Laporan {$label}", "Laporan {$dailyReport->report_date->format('d/m/Y')} ({$dailyReport->area}) {$label}".($data['note'] ?? false ? ": {$data['note']}" : '.'), route('daily-reports.index', [], false));

        return response()->json(['ok' => true]);
    }

    /** Semua laporan (dibatasi tim sesuai TeamScope) dengan filter periode, tim, karyawan, pencarian, dan status. */
    public function all(Request $request)
    {
        [$query, $p] = $this->filtered($request);

        $sum = (clone $query)->toBase()->selectRaw(
            "count(*) as n, coalesce(sum(case when status = 'kendala' then 1 else 0 end), 0) as k, coalesce(sum(".DailyReport::HOURS_SQL."), 0) as h"
        )->first();

        $reports = $query->withCount(['photos', 'comments'])->orderByDesc('report_date')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (DailyReport $r) => $r->toRow(true));

        return Inertia::render('DailyReports/All', [
            'reports' => $reports,
            'period' => $p,
            'periodLabels' => Period::LABELS,
            'filters' => [
                'team' => $request->input('team', ''),
                'employee_id' => $request->input('employee_id', ''),
                'q' => $request->input('q', ''),
                'status' => $request->input('status', ''),
                'review' => $request->input('review', ''),
            ],
            'summary' => ['count' => (int) $sum->n, 'kendala' => (int) $sum->k, 'hours' => round((float) $sum->h, 1)],
            'employees' => TeamScope::constrainEmployees(Employee::where('employment_status', 'active'), $request->user())
                ->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    /** Ekspor Excel / PDF; PDF bisa disertai foto bukti kerja (photos=1, maks. 40 laporan). */
    public function export(Request $request)
    {
        $request->validate(['format' => ['required', 'in:xlsx,pdf'], 'photos' => ['nullable', 'boolean']]);
        [$query, $p] = $this->filtered($request);

        if ($request->input('format') === 'pdf' && $request->boolean('photos')) {
            $reports = $query->with('photos')->orderBy('report_date')->orderBy('id')->limit(40)->get();
            $items = $reports->map(function (DailyReport $r) {
                $images = [];
                foreach ($r->photos as $photo) {
                    $path = Storage::disk('local')->path($photo->path);
                    $binary = is_file($path) ? (ImageProcessor::jpeg($path, 640, 70) ?? file_get_contents($path)) : null;
                    if ($binary) {
                        $images[] = 'data:image/jpeg;base64,'.base64_encode($binary);
                    }
                }

                return ['row' => $r->toRow(true), 'images' => $images];
            })->all();

            return Pdf::loadView('pdf.reports_photos', ['items' => $items, 'subtitle' => "{$p['type_label']}: {$p['label']}"])
                ->setPaper('a4', 'portrait')->download('laporan-foto-'.$p['from'].'-sd-'.$p['to'].'.pdf');
        }

        $rows = $query->orderBy('report_date')->orderBy('id')->limit(5000)->get()
            ->map(fn (DailyReport $r) => [
                $r->report_date->format('d/m/Y'), $r->employee?->nip, $r->employee?->full_name, $r->team,
                $r->shift_name, $r->area, substr($r->start_time, 0, 5), substr($r->end_time, 0, 5),
                $r->hours, $r->task, $r->issue, DailyReport::STATUSES[$r->status] ?? $r->status,
                DailyReport::REVIEW_STATUSES[$r->review_status] ?? $r->review_status,
            ])->all();

        return Exporter::download(
            $request->input('format'),
            'laporan-harian-'.$p['from'].'-sd-'.$p['to'],
            'Laporan Harian',
            "{$p['type_label']}: {$p['label']}",
            ['Tanggal', 'NIP', 'Nama', 'Tim', 'Shift', 'Area', 'Mulai', 'Selesai', 'Jam', 'Uraian Pekerjaan', 'Kendala', 'Status', 'Review'],
            $rows
        );
    }

    /** @return array{0: Builder, 1: array} */
    private function filtered(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'in:hari,minggu,bulan,tahun'],
            'date' => ['nullable', 'date'],
            'team' => ['nullable', 'string', 'max:30'],
            'employee_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(DailyReport::STATUSES))],
            'review' => ['nullable', Rule::in(array_keys(DailyReport::REVIEW_STATUSES))],
        ]);

        $p = Period::resolve($request->input('period'), $request->input('date'));

        $query = TeamScope::constrain(
            DailyReport::query()->with('employee:id,full_name,nip')->whereBetween('report_date', [$p['from'], $p['to']]),
            $request->user()
        );

        if ($team = $request->input('team')) {
            $query->where('team', $team);
        }
        if ($employeeId = $request->input('employee_id')) {
            $query->where('employee_id', $employeeId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($review = $request->input('review')) {
            $query->where('review_status', $review);
        }
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('area', 'ilike', $like)->orWhere('task', 'ilike', $like)->orWhere('issue', 'ilike', $like)
                    ->orWhereHas('employee', fn ($e) => $e->where('full_name', 'ilike', $like)->orWhere('nip', 'ilike', $like));
            });
        }

        return [$query, $p];
    }

    /** Aturan isian bersama untuk simpan & revisi. */
    private function rules(Request $request, int $existingPhotos = 0): array
    {
        return [
            'team' => ['nullable', Rule::in(config('hr.teams'))],
            'area' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'task' => ['required', 'string', 'max:2000'],
            'issue' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(array_keys(DailyReport::STATUSES))],
            // Foto bukti: 1-3 foto dari kamera; WAJIB bila status Selesai (kecuali laporan sudah punya foto).
            'photos' => [Rule::requiredIf(fn () => $request->input('status') === 'selesai' && $existingPhotos === 0), 'nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ];
    }

    private function messages(): array
    {
        return [
            'photos.required' => 'Foto bukti kerja wajib (minimal 1) untuk laporan berstatus Selesai.',
            'photos.max' => 'Maksimal '.self::MAX_PHOTOS.' foto per laporan.',
        ];
    }

    private function savePhotos(DailyReport $report, array $files): void
    {
        foreach ($files as $file) {
            $binary = ImageProcessor::jpeg($file->getRealPath()) ?? file_get_contents($file->getRealPath());
            $path = 'report-photos/'.Str::random(28).'.jpg';
            Storage::disk('local')->put($path, $binary);
            $report->photos()->create(['path' => $path]);
        }
    }

    private function isOwner(User $user, DailyReport $report): bool
    {
        return $user->employee && $report->employee_id === $user->employee->id;
    }

    private function canEdit(DailyReport $report): bool
    {
        return $report->review_status === 'perlu_revisi' || $report->created_at->greaterThan(now()->subDay());
    }

    private function canReview(User $user, DailyReport $report): bool
    {
        if (! $user->can('daily_reports.review') || $this->isOwner($user, $report) || ! $report->employee) {
            return false;
        }

        return TeamScope::isHrWide($user) || TeamScope::constrainEmployees(Employee::query()->whereKey($report->employee_id), $user)->exists();
    }

    /** Pemilik laporan, atau pemegang daily_reports.view yang berwenang atas karyawannya. */
    private function authorizeView(User $user, DailyReport $report): void
    {
        abort_unless($report->employee, 404);
        if ($this->isOwner($user, $report)) {
            return;
        }
        abort_unless($user->can('daily_reports.view'), 403);
        TeamScope::assertCanAct($user, $report->employee);
    }

    private function shifts()
    {
        return Shift::orderBy('id')->get()->map(fn (Shift $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'start' => substr($s->start_time, 0, 5),
            'end' => substr($s->end_time, 0, 5),
        ]);
    }
}
