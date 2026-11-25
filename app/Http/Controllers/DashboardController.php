<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\DailyReport;
use App\Models\WorkOrder;
use App\Services\AnalyticsService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics)
    {
        $user = $request->user();

        // Dashboard grafik HANYA untuk pemegang izin dashboard.analytics (Owner & Ka. URS secara bawaan).
        // Lainnya mendapat dashboard personal.
        if (! $user->can('dashboard.analytics')) {
            return $this->employeeDashboard($user);
        }

        $request->validate([
            'period' => ['nullable', 'in:hari,minggu,bulan,tahun,custom'],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'team' => ['nullable', 'string', 'max:30'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        return Inertia::render('Dashboard/Index', $analytics->build($user, $request->only(['period', 'date', 'from', 'to', 'team', 'role'])) + [
            'periodLabels' => Period::LABELS + ['custom' => 'Kustom'],
            'filters' => ['team' => $request->input('team', ''), 'role' => $request->input('role', '')],
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    private function employeeDashboard($user)
    {
        $employee = $user->employee;

        return Inertia::render('Dashboard/Employee', [
            'employee' => $employee ? [
                'full_name' => $employee->full_name,
                'position' => $employee->position?->title,
                'department' => $employee->department?->name,
                'join_date' => $employee->join_date?->toDateString(),
            ] : null,
            'today_reports' => $employee ? DailyReport::where('employee_id', $employee->id)->whereDate('report_date', today())->count() : 0,
            'work_orders_active' => $employee ? WorkOrder::involving($employee->id)->whereIn('status', ['direncanakan', 'berjalan'])->count() : 0,
            'latest_assessment' => $employee ? Assessment::where('employee_id', $employee->id)->orderByDesc('period')->first()?->toRow() : null,
        ]);
    }
}
