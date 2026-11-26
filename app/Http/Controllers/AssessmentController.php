<?php

namespace App\Http\Controllers;

use App\Support\Sql;
use App\Models\Assessment;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notify;
use App\Support\Exporter;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Penilaian outsourcing bulanan: input nilai, riwayat, dan nilai saya. */
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
            $query->whereHas('employee', fn ($q) => $q->where('team', Sql::like(), Sql::contains($team)));
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
