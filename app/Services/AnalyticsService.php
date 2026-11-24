<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\User;
use App\Support\Period;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data dashboard grafik untuk Owner & Ka. URS. Semua agregat memakai DB::table() (bukan model Eloquent) supaya
 * $appends milik Employee tidak ikut terpanggil pada baris agregat.
 */
class AnalyticsService
{
    /**
     * @param  array{period?:?string,date?:?string,from?:?string,to?:?string,team?:?string,role?:?string}  $in
     */
    public function build(User $user, array $in): array
    {
        $p = Period::resolve($in['period'] ?? null, $in['date'] ?? null, $in['from'] ?? null, $in['to'] ?? null);
        $team = $in['team'] ?? null;
        $role = $in['role'] ?? null;
        $today = today();

        $employees = TeamScope::constrainEmployees(Employee::query()->where('employment_status', 'active'), $user)
            ->when($team, fn ($q, $t) => $q->where('team', $t))
            ->when($role, fn ($q, $r) => $q->whereHas('user', fn ($u) => $u->role($r)))
            ->orderBy('full_name')->get(['id', 'full_name', 'team']);
        $ids = $employees->pluck('id')->all();

        // ---------- kartu ----------
        $attToday = DB::table('attendances')->whereIn('employee_id', $ids)->whereDate('date', $today->toDateString())
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $recorded = (int) $attToday->sum();

        $score = DB::table('assessments')->whereIn('employee_id', $ids)->whereBetween('period', [$p['from'], $p['to']])
            ->selectRaw('avg(quality) as q, avg(discipline) as d, avg(teamwork) as t, avg(initiative) as i, avg(safety) as s, avg(total) as total')->first();

        $cards = [
            'employees' => count($ids),
            'present_today' => (int) (($attToday['hadir'] ?? 0) + ($attToday['terlambat'] ?? 0) + ($attToday['wfh'] ?? 0)),
            'late_today' => (int) ($attToday['terlambat'] ?? 0),
            'not_yet_today' => max(0, count($ids) - $recorded),
            'avg_score' => $score?->total !== null ? round((float) $score->total, 1) : null,
            'reports' => DB::table('daily_reports')->whereIn('employee_id', $ids)->whereBetween('report_date', [$p['from'], $p['to']])->count(),
            'pending_leaves' => DB::table('leaves')->whereIn('employee_id', $ids)->where('status', 'pending')->count(),
        ];

        // ---------- absensi hari ini (doughnut) ----------
        $doughnut = [
            ['label' => 'Tepat Waktu', 'value' => (int) ($attToday['hadir'] ?? 0) + (int) ($attToday['wfh'] ?? 0)],
            ['label' => 'Terlambat', 'value' => (int) ($attToday['terlambat'] ?? 0)],
            ['label' => 'Izin / Sakit', 'value' => (int) ($attToday['izin'] ?? 0) + (int) ($attToday['sakit'] ?? 0)],
            ['label' => 'Cuti', 'value' => (int) ($attToday['cuti'] ?? 0)],
            ['label' => 'Alfa', 'value' => (int) ($attToday['alpha'] ?? 0)],
            ['label' => 'Belum Absen', 'value' => $cards['not_yet_today']],
        ];

        return [
            'period' => $p,
            'cards' => $cards,
            'doughnut' => $doughnut,
            'attendanceTrend' => $this->attendanceTrend($ids, $p, $today),
            'radar' => [
                'labels' => array_values(Assessment::CRITERIA),
                'values' => array_map(fn ($v) => $v !== null ? round((float) $v, 1) : null, [$score?->q, $score?->d, $score?->t, $score?->i, $score?->s]),
            ],
            'teamScores' => $this->teamScores($ids, $p),
            'scoreTrend' => $this->scoreTrend($ids, $p),
            'reportsChart' => $this->reportsChart($ids, $p),
            'topIssues' => DB::table('daily_reports')->whereIn('employee_id', $ids)->where('status', 'kendala')
                ->whereBetween('report_date', [$p['from'], $p['to']])
                ->selectRaw('area, count(*) as n')->groupBy('area')->orderByDesc('n')->limit(5)->get()
                ->map(fn ($r) => ['area' => $r->area, 'n' => (int) $r->n])->all(),
            'ranking' => $this->ranking($ids, $p),
            'alerts' => $this->alerts($employees, $ids, $p, $today),
        ];
    }

    private function attendanceTrend(array $ids, array $p, Carbon $today): array
    {
        $end = Carbon::parse($p['to'])->gt($today) ? $today->copy() : Carbon::parse($p['to']);
        $start = Carbon::parse($p['from']);
        if ($start->diffInDays($end, true) > 29) {
            $start = $end->copy()->subDays(29);
        }

        $map = [];
        DB::table('attendances')->whereIn('employee_id', $ids)->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('date, status, count(*) as n')->groupBy('date', 'status')->get()
            ->each(function ($r) use (&$map) {
                $map[substr((string) $r->date, 0, 10)][$r->status] = (int) $r->n;
            });

        $labels = $present = $late = $absent = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $row = $map[$d->toDateString()] ?? [];
            $labels[] = $d->format('d/m');
            $present[] = ($row['hadir'] ?? 0) + ($row['wfh'] ?? 0);
            $late[] = $row['terlambat'] ?? 0;
            $absent[] = ($row['izin'] ?? 0) + ($row['sakit'] ?? 0) + ($row['cuti'] ?? 0) + ($row['alpha'] ?? 0);
        }

        return ['labels' => $labels, 'present' => $present, 'late' => $late, 'absent' => $absent];
    }

    private function teamScores(array $ids, array $p): array
    {
        return DB::table('assessments')->join('employees', 'employees.id', '=', 'assessments.employee_id')
            ->whereIn('assessments.employee_id', $ids)->whereBetween('assessments.period', [$p['from'], $p['to']])
            ->selectRaw("coalesce(employees.team, 'Tanpa tim') as label, avg(assessments.total) as score")
            ->groupByRaw("coalesce(employees.team, 'Tanpa tim')")->orderByRaw("coalesce(employees.team, 'Tanpa tim')")->get()
            ->map(fn ($r) => ['label' => $r->label, 'score' => round((float) $r->score, 1)])->all();
    }

    /** Rata-rata nilai 6 bulan terakhir (berakhir di bulan akhir periode). */
    private function scoreTrend(array $ids, array $p): array
    {
        $endMonth = Carbon::parse($p['to'])->startOfMonth();
        $startMonth = $endMonth->copy()->subMonths(5);

        $rows = DB::table('assessments')->whereIn('employee_id', $ids)
            ->whereBetween('period', [$startMonth->toDateString(), $endMonth->copy()->endOfMonth()->toDateString()])
            ->selectRaw("to_char(period, 'YYYY-MM') as m, avg(total) as score")->groupBy('m')->pluck('score', 'm');

        $labels = $values = [];
        for ($m = $startMonth->copy(); $m->lte($endMonth); $m->addMonth()) {
            $labels[] = substr(Assessment::MONTHS[(int) $m->format('n') - 1], 0, 3).' '.$m->format('y');
            $values[] = isset($rows[$m->format('Y-m')]) ? round((float) $rows[$m->format('Y-m')], 1) : null;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Laporan per hari (rentang <= 31 hari) atau per bulan (rentang lebih panjang), dipisah menurut status. */
    private function reportsChart(array $ids, array $p): array
    {
        $from = Carbon::parse($p['from']);
        $to = Carbon::parse($p['to']);
        $daily = $from->diffInDays($to, true) <= 30;

        $map = [];
        DB::table('daily_reports')->whereIn('employee_id', $ids)->whereBetween('report_date', [$p['from'], $p['to']])
            ->selectRaw('report_date, status, count(*) as n')->groupBy('report_date', 'status')->get()
            ->each(function ($r) use (&$map, $daily) {
                $key = $daily ? substr((string) $r->report_date, 0, 10) : substr((string) $r->report_date, 0, 7);
                $map[$key][$r->status] = ($map[$key][$r->status] ?? 0) + (int) $r->n;
            });

        $labels = $selesai = $proses = $kendala = [];
        $cursor = $daily ? $from->copy() : $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $key = $daily ? $cursor->toDateString() : $cursor->format('Y-m');
            $row = $map[$key] ?? [];
            $labels[] = $daily ? $cursor->format('d/m') : substr(Assessment::MONTHS[(int) $cursor->format('n') - 1], 0, 3).' '.$cursor->format('y');
            $selesai[] = $row['selesai'] ?? 0;
            $proses[] = $row['proses'] ?? 0;
            $kendala[] = $row['kendala'] ?? 0;
            $daily ? $cursor->addDay() : $cursor->addMonth();
        }

        return ['labels' => $labels, 'selesai' => $selesai, 'proses' => $proses, 'kendala' => $kendala];
    }

    private function ranking(array $ids, array $p): array
    {
        return DB::table('assessments')->join('employees', 'employees.id', '=', 'assessments.employee_id')
            ->whereIn('assessments.employee_id', $ids)->whereBetween('assessments.period', [$p['from'], $p['to']])
            ->selectRaw('employees.full_name as name, employees.team as team, avg(assessments.total) as score')
            ->groupBy('employees.id', 'employees.full_name', 'employees.team')->orderByDesc('score')->limit(5)->get()
            ->map(fn ($r) => ['name' => $r->name, 'team' => $r->team, 'score' => round((float) $r->score, 1)])->all();
    }

    private function alerts($employees, array $ids, array $p, Carbon $today): array
    {
        $reported = DB::table('daily_reports')->whereIn('employee_id', $ids)->whereDate('report_date', $today->toDateString())
            ->pluck('employee_id')->unique()->all();
        $missing = $employees->reject(fn ($e) => in_array($e->id, $reported, true))->values();

        $low = DB::table('assessments')->join('employees', 'employees.id', '=', 'assessments.employee_id')
            ->whereIn('assessments.employee_id', $ids)->whereBetween('assessments.period', [$p['from'], $p['to']])
            ->where('assessments.total', '<', 70)
            ->select('employees.full_name as name', 'employees.team as team', 'assessments.total', 'assessments.period')
            ->orderBy('assessments.total')->limit(10)->get()
            ->map(fn ($r) => [
                'name' => $r->name, 'team' => $r->team, 'score' => (int) $r->total,
                'period' => Assessment::MONTHS[(int) substr((string) $r->period, 5, 2) - 1].' '.substr((string) $r->period, 0, 4),
            ])->all();

        return [
            'missing_count' => $missing->count(),
            'missing' => $missing->take(10)->map(fn ($e) => ['name' => $e->full_name, 'team' => $e->team])->all(),
            'low_scores' => $low,
        ];
    }
}
