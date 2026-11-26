<?php

namespace App\Services;

use App\Support\Sql;
use App\Models\Assessment;
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
            ->when($team, fn ($q, $t) => $q->where('team', Sql::like(), Sql::contains($t)))
            ->when($role, fn ($q, $r) => $q->whereHas('user', fn ($u) => $u->role($r)))
            ->orderBy('full_name')->get(['id', 'full_name', 'team']);
        $ids = $employees->pluck('id')->all();

        $score = DB::table('assessments')->whereIn('employee_id', $ids)->whereBetween('period', [$p['from'], $p['to']])
            ->selectRaw('avg(quality) as q, avg(discipline) as d, avg(teamwork) as t, avg(initiative) as i, avg(safety) as s, avg(total) as total')->first();

        $orders = fn () => DB::table('work_orders')->when($team, fn ($q, $t) => $q->where('team', Sql::like(), Sql::contains($t)));

        $cards = [
            'employees' => count($ids),
            'wo_active' => $orders()->where('status', 'berjalan')->count(),
            'wo_done' => $orders()->where('status', 'selesai')->whereBetween('end_date', [$p['from'], $p['to']])->count(),
            'avg_score' => $score?->total !== null ? round((float) $score->total, 1) : null,
            'reports' => DB::table('daily_reports')->whereIn('employee_id', $ids)->whereBetween('report_date', [$p['from'], $p['to']])->count(),
        ];

        // Perintah kerja menurut status (dibuat pada periode terpilih).
        $byStatus = $orders()->whereBetween('order_date', [$p['from'], $p['to']])->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $doughnut = [
            ['label' => 'Direncanakan', 'value' => (int) ($byStatus['direncanakan'] ?? 0)],
            ['label' => 'Berjalan', 'value' => (int) ($byStatus['berjalan'] ?? 0)],
            ['label' => 'Selesai', 'value' => (int) ($byStatus['selesai'] ?? 0)],
            ['label' => 'Dibatalkan', 'value' => (int) ($byStatus['dibatalkan'] ?? 0)],
        ];

        return [
            'period' => $p,
            'cards' => $cards,
            'doughnut' => $doughnut,
            'workOrderTrend' => $this->workOrderTrend($p, $team),
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

    /** Daftar kunci waktu berurutan + labelnya: per hari (rentang <= 31 hari) atau per bulan (lebih panjang). */
    private function buckets(array $p): array
    {
        $from = Carbon::parse($p['from']);
        $to = Carbon::parse($p['to']);
        $daily = $from->diffInDays($to, true) <= 30;

        $keys = [];
        $cursor = $daily ? $from->copy() : $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $key = $daily ? $cursor->toDateString() : $cursor->format('Y-m');
            $keys[$key] = $daily ? $cursor->format('d/m') : substr(Assessment::MONTHS[(int) $cursor->format('n') - 1], 0, 3).' '.$cursor->format('y');
            $daily ? $cursor->addDay() : $cursor->addMonth();
        }

        return [$daily, $keys];
    }

    private function workOrderTrend(array $p, ?string $team): array
    {
        [$daily, $keys] = $this->buckets($p);
        $key = fn ($d) => $daily ? substr((string) $d, 0, 10) : substr((string) $d, 0, 7);
        $base = fn () => DB::table('work_orders')->when($team, fn ($q, $t) => $q->where('team', Sql::like(), Sql::contains($t)));

        $created = $done = [];
        $base()->whereBetween('order_date', [$p['from'], $p['to']])->selectRaw('order_date as d, count(*) as n')->groupBy('order_date')->get()
            ->each(function ($r) use (&$created, $key) {
                $created[$key($r->d)] = ($created[$key($r->d)] ?? 0) + (int) $r->n;
            });
        $base()->where('status', 'selesai')->whereBetween('end_date', [$p['from'], $p['to']])->selectRaw('end_date as d, count(*) as n')->groupBy('end_date')->get()
            ->each(function ($r) use (&$done, $key) {
                $done[$key($r->d)] = ($done[$key($r->d)] ?? 0) + (int) $r->n;
            });

        return [
            'labels' => array_values($keys),
            'created' => array_map(fn ($k) => $created[$k] ?? 0, array_keys($keys)),
            'done' => array_map(fn ($k) => $done[$k] ?? 0, array_keys($keys)),
        ];
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

        // period selalu tanggal 1 pada bulan yang dinilai, jadi cukup dikelompokkan per tanggal lalu dipetakan ke bulan.
        $rows = [];
        DB::table('assessments')->whereIn('employee_id', $ids)
            ->whereBetween('period', [$startMonth->toDateString(), $endMonth->copy()->endOfMonth()->toDateString()])
            ->selectRaw('period, avg(total) as score')->groupBy('period')->get()
            ->each(function ($r) use (&$rows) {
                $rows[substr((string) $r->period, 0, 7)] = (float) $r->score;
            });

        $labels = $values = [];
        for ($m = $startMonth->copy(); $m->lte($endMonth); $m->addMonth()) {
            $labels[] = substr(Assessment::MONTHS[(int) $m->format('n') - 1], 0, 3).' '.$m->format('y');
            $values[] = isset($rows[$m->format('Y-m')]) ? round($rows[$m->format('Y-m')], 1) : null;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Laporan per hari / per bulan, dipisah menurut status. */
    private function reportsChart(array $ids, array $p): array
    {
        [$daily, $keys] = $this->buckets($p);

        $map = [];
        DB::table('daily_reports')->whereIn('employee_id', $ids)->whereBetween('report_date', [$p['from'], $p['to']])
            ->selectRaw('report_date, status, count(*) as n')->groupBy('report_date', 'status')->get()
            ->each(function ($r) use (&$map, $daily) {
                $key = $daily ? substr((string) $r->report_date, 0, 10) : substr((string) $r->report_date, 0, 7);
                $map[$key][$r->status] = ($map[$key][$r->status] ?? 0) + (int) $r->n;
            });

        $col = fn (string $status) => array_map(fn ($k) => $map[$k][$status] ?? 0, array_keys($keys));

        return ['labels' => array_values($keys), 'selesai' => $col('selesai'), 'proses' => $col('proses'), 'kendala' => $col('kendala')];
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
