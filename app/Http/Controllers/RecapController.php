<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Support\Exporter;
use App\Support\Period;
use App\Support\TeamScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Rekap kinerja per karyawan: jumlah laporan, pekerjaan selesai, total jam, dan nilai penilaian rata-rata. */
class RecapController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reports.view')->only(['index']);
        $this->middleware('permission:reports.export')->only(['export']);
    }

    public function index(Request $request)
    {
        [$employees, $p] = $this->scope($request);
        $rows = $this->rows($employees, $p, $request->user()->can('performance.email'));

        $detail = DailyReport::with('employee:id,full_name')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('report_date', [$p['from'], $p['to']])
            ->orderByDesc('report_date')->orderByDesc('id')
            ->paginate(15)->withQueryString()
            ->through(fn (DailyReport $r) => $r->toRow(true));

        return Inertia::render('Recap/Index', [
            'rows' => $rows,
            'detail' => $detail,
            'period' => $p,
            'periodLabels' => Period::LABELS,
            'filters' => ['team' => $request->input('team', '')],
            'canEmail' => $request->user()->can('performance.email'),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['format' => ['required', 'in:xlsx,pdf']]);
        [$employees, $p] = $this->scope($request);

        $rows = $this->rows($employees, $p, false)->map(fn (array $r) => [
            $r['name'], $r['nip'], $r['team'], $r['position'], $r['reports'], $r['done'], $r['hours'],
            $r['score'] ?? '-', $r['grade'],
        ])->all();

        return Exporter::download(
            $request->input('format'),
            'rekap-'.$p['type'].'-'.$p['date'],
            'Rekap Kinerja',
            "{$p['type_label']}: {$p['label']}".($request->input('team') ? ' · Tim '.$request->input('team') : ''),
            ['Nama', 'NIP', 'Tim', 'Jabatan', 'Jumlah Laporan', 'Selesai', 'Total Jam', 'Nilai Rata-rata', 'Predikat'],
            $rows
        );
    }

    /**
     * Statistik per karyawan pada rentang $p (hasil Period::resolve).
     *
     * @param  array<int,int>  $ids
     * @return array<int,array{reports:int,done:int,hours:float,score:?int}>
     */
    public static function stats(array $ids, array $p): array
    {
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['reports' => 0, 'done' => 0, 'hours' => 0.0, 'score' => null];
        }
        if (! $ids) {
            return $out;
        }

        DailyReport::whereIn('employee_id', $ids)
            ->whereBetween('report_date', [$p['from'], $p['to']])
            ->selectRaw("employee_id, count(*) as n, sum(case when status = 'selesai' then 1 else 0 end) as done, sum(".DailyReport::HOURS_SQL.') as hours')
            ->groupBy('employee_id')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->employee_id]['reports'] = (int) $r->n;
                $out[$r->employee_id]['done'] = (int) $r->done;
                $out[$r->employee_id]['hours'] = round((float) $r->hours, 1);
            });

        Assessment::whereIn('employee_id', $ids)
            ->whereBetween('period', [$p['from'], $p['to']])
            ->selectRaw('employee_id, avg(total) as score')
            ->groupBy('employee_id')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->employee_id]['score'] = (int) round((float) $r->score);
            });

        return $out;
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: array} */
    private function scope(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'in:hari,minggu,bulan,tahun'],
            'date' => ['nullable', 'date'],
            'team' => ['nullable', 'string', 'max:30'],
        ]);

        $p = Period::resolve($request->input('period'), $request->input('date'));

        $employees = TeamScope::constrainEmployees(
            Employee::query()->with(['position:id,title', 'user:id,email'])->where('employment_status', 'active'),
            $request->user()
        )
            ->when($request->input('team'), fn ($q, $team) => $q->where('team', $team))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nip', 'team', 'position_id', 'user_id', 'personal_email']);

        return [$employees, $p];
    }

    private function rows($employees, array $p, bool $withEmail)
    {
        $stats = self::stats($employees->pluck('id')->all(), $p);

        return $employees->map(function (Employee $e) use ($stats, $withEmail) {
            $s = $stats[$e->id];

            return [
                'id' => $e->id,
                'name' => $e->full_name,
                'nip' => $e->nip,
                'team' => $e->team,
                'position' => $e->position?->title,
                'reports' => $s['reports'],
                'done' => $s['done'],
                'hours' => $s['hours'],
                'score' => $s['score'],
                'grade' => Assessment::grade($s['score']),
                'email' => $withEmail ? $e->contact_email : null,
            ];
        })->values();
    }
}
