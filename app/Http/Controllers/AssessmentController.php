<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notify;
use App\Support\Exporter;
use App\Support\Period;
use App\Support\TeamScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** Penilaian outsourcing bulanan: input nilai, riwayat, nilai saya, dan Form Penilaian (cetak / PDF). */
class AssessmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:performance.view')->only(['index', 'export']);
        $this->middleware('permission:performance.manage')->only(['store', 'destroy']);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Assessments/Index', [
            'employees' => TeamScope::constrainEmployees(Employee::where('employment_status', 'active'), $user)
                ->orderBy('full_name')->get(['id', 'full_name', 'nip', 'team']),
            'assessments' => $this->filtered($request)->paginate(12)->withQueryString()
                ->through(fn (Assessment $a) => $this->row($a, $user)),
            'criteria' => self::criteria(),
            'years' => self::years(),
            'filters' => [
                'year' => $request->input('year', ''),
                'month' => $request->input('month', ''),
                'team' => $request->input('team', ''),
            ],
            'canManage' => $user->can('performance.manage'),
            'canEmail' => $user->can('performance.email'),
            'now' => ['month' => (int) today()->format('n'), 'year' => (int) today()->format('Y')],
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (array_keys(Assessment::CRITERIA) as $key) {
            $rules[$key] = ['required', 'integer', 'between:0,100'];
        }
        $data = $request->validate($rules);

        $employee = Employee::findOrFail($data['employee_id']);
        TeamScope::assertCanAct($request->user(), $employee);

        if ($request->user()->employee?->id === $employee->id) {
            return back()->with('error', 'Kamu tidak dapat menilai dirimu sendiri.');
        }

        $scores = [];
        foreach (array_keys(Assessment::CRITERIA) as $key) {
            $scores[$key] = (int) $data[$key];
        }

        $assessment = Assessment::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => Carbon::create((int) $data['year'], (int) $data['month'], 1)->toDateString()],
            $scores + [
                'total' => (int) round(array_sum($scores) / count($scores)),
                'note' => $data['note'] ?? null,
                'assessed_by' => $request->user()->id,
            ]
        );

        ActivityLogger::log('assessment.saved', "Menilai {$employee->full_name} periode {$assessment->periodLabel()} (nilai {$assessment->total})", $assessment, $request->user()->id);

        Notify::user($employee->user_id, 'Penilaian baru', "Penilaian {$assessment->periodLabel()}: nilai {$assessment->total} ({$assessment->toRow()['grade']}).", route('assessments.mine', [], false));

        return back()->with('success', "Penilaian {$employee->full_name} tersimpan (nilai {$assessment->total}).");
    }

    public function destroy(Request $request, Assessment $assessment)
    {
        $employee = $assessment->employee;
        abort_unless($employee, 404);
        TeamScope::assertCanAct($request->user(), $employee);

        $label = "{$employee->full_name} periode {$assessment->periodLabel()}";
        $assessment->delete();
        ActivityLogger::log('assessment.deleted', "Menghapus penilaian {$label}", null, $request->user()->id);

        return back()->with('success', 'Penilaian dihapus.');
    }

    /** Nilai saya (karyawan melihat penilaian atas dirinya). */
    public function mine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        return Inertia::render('Assessments/Mine', [
            'assessments' => Assessment::where('employee_id', $employee->id)->orderByDesc('period')->get()
                ->map(fn (Assessment $a) => $a->toRow()),
            'criteria' => self::criteria(),
        ]);
    }

    /** Form Penilaian: lembar siap cetak untuk satu karyawan pada satu bulan. */
    public function sheet(Request $request)
    {
        return Inertia::render('Assessments/Sheet', $this->sheetPayload($request) + [
            'years' => self::years(),
            'canPickEmployee' => $request->user()->can('performance.view'),
            'canEmail' => $request->user()->can('performance.email'),
        ]);
    }

    public function sheetPdf(Request $request)
    {
        $p = $this->sheetPayload($request);
        abort_unless($p['employee'] && $p['assessment'], 404, 'Belum ada penilaian untuk periode ini.');

        return Pdf::loadView('pdf.assessment', $p)->setPaper('a4', 'portrait')->download(
            'form-penilaian-'.Str::slug($p['employee']['full_name']).'-'.$p['year'].'-'.str_pad((string) $p['month'], 2, '0', STR_PAD_LEFT).'.pdf'
        );
    }

    public function export(Request $request)
    {
        $request->validate(['format' => ['required', 'in:xlsx,pdf']]);

        $rows = $this->filtered($request)->limit(5000)->get()->map(fn (Assessment $a) => [
            $a->periodLabel(), $a->employee?->nip, $a->employee?->full_name, $a->employee?->team,
            $a->quality, $a->discipline, $a->teamwork, $a->initiative, $a->safety,
            $a->total, Assessment::grade($a->total), $a->note, $a->assessor?->name,
        ])->all();

        $parts = [];
        if ($request->input('month')) {
            $parts[] = Assessment::MONTHS[max(1, min(12, (int) $request->input('month'))) - 1];
        }
        if ($request->input('year')) {
            $parts[] = (int) $request->input('year');
        }
        if ($request->input('team')) {
            $parts[] = 'Tim '.$request->input('team');
        }

        return Exporter::download(
            $request->input('format'),
            'penilaian-'.now()->format('Ymd'),
            'Penilaian Outsourcing',
            $parts ? implode(' · ', $parts) : 'Semua periode',
            ['Periode', 'NIP', 'Nama', 'Tim', ...array_values(Assessment::CRITERIA), 'Nilai Akhir', 'Predikat', 'Catatan', 'Penilai'],
            $rows
        );
    }

    private function filtered(Request $request): Builder
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'team' => ['nullable', 'string', 'max:30'],
        ]);

        $query = TeamScope::constrain(
            Assessment::query()->with(['employee:id,full_name,nip,team,user_id,personal_email', 'employee.user:id,email', 'assessor:id,name']),
            $request->user()
        );

        if ($year = $request->input('year')) {
            $query->whereYear('period', (int) $year);
        }
        if ($month = $request->input('month')) {
            $query->whereMonth('period', (int) $month);
        }
        if ($team = $request->input('team')) {
            $query->whereHas('employee', fn ($q) => $q->where('team', $team));
        }

        return $query->orderByDesc('period')->orderByDesc('id');
    }

    private function row(Assessment $a, User $user): array
    {
        $row = $a->toRow(true) + ['assessor' => $a->assessor?->name];
        if ($user->can('performance.email')) {
            $row['email'] = $a->employee?->contact_email;
        }

        return $row;
    }

    private function sheetPayload(Request $request): array
    {
        $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $user = $request->user();
        $canAll = $user->can('performance.view');
        $own = $user->employee;
        abort_unless($canAll || $own, 403);

        $employees = $canAll
            ? TeamScope::constrainEmployees(Employee::where('employment_status', 'active'), $user)->orderBy('full_name')->get(['id', 'full_name', 'nip'])
            : collect();

        $target = null;
        if ($canAll) {
            $id = (int) $request->input('employee_id');
            $target = $id ? $employees->firstWhere('id', $id) : $employees->first();
        } else {
            $target = $own;
        }
        $employee = $target ? Employee::with(['position:id,title', 'department:id,name'])->find($target->id) : null;

        $month = (int) ($request->input('month') ?: today()->format('n'));
        $year = (int) ($request->input('year') ?: today()->format('Y'));

        $assessment = null;
        $stat = null;
        if ($employee) {
            $first = Carbon::create($year, $month, 1)->toDateString();
            $assessment = Assessment::with('assessor:id,name')->where('employee_id', $employee->id)->whereDate('period', $first)->first();
            $stat = RecapController::stats([$employee->id], Period::resolve('bulan', $first))[$employee->id];
        }

        return [
            'employees' => $employees,
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'nip' => $employee->nip,
                'position' => $employee->position?->title,
                'department' => $employee->department?->name,
                'team' => $employee->team,
                'email' => $user->can('performance.email') ? $employee->contact_email : null,
            ] : null,
            'month' => $month,
            'year' => $year,
            'period_label' => Assessment::MONTHS[$month - 1].' '.$year,
            'assessment' => $assessment?->toRow(),
            'assessor' => $assessment?->assessor?->name,
            'stat' => $stat,
            'owner' => rescue(fn () => User::role('super-admin')->orderBy('id')->value('name'), null, false),
            'criteria' => self::criteria(),
        ];
    }

    /** @return array<int,array{key:string,label:string}> */
    public static function criteria(): array
    {
        return collect(Assessment::CRITERIA)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    /** @return array<int,int> */
    private static function years(): array
    {
        $y = (int) today()->format('Y');

        return range($y + 1, $y - 3);
    }
}
