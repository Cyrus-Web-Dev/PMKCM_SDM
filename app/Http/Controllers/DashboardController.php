<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\Leave;
use App\Services\AnalyticsService;
use App\Services\HolidayService;
use App\Services\LeaveService;
use App\Support\Period;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, HolidayService $holidays, LeaveService $leaveService, AnalyticsService $analytics)
    {
        $user = $request->user();

        // Dashboard grafik HANYA untuk Owner & Ka. URS (izin dashboard.analytics). Lainnya: dashboard personal.
        if (! $user->can('dashboard.analytics')) {
            return $this->employeeDashboard($user, $holidays, $leaveService);
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
            'upcoming_holidays' => $holidays->upcoming(5),
        ]);
    }

    private function employeeDashboard($user, HolidayService $holidays, LeaveService $leaveService)
    {
        $employee = $user->employee;

        $todayRecord = null;
        $balances = [];
        $pendingLeaves = 0;

        if ($employee) {
            // Check-in yang belum check-out (termasuk shift malam yang dimulai kemarin) diprioritaskan.
            $todayRecord = Attendance::where('employee_id', $employee->id)->whereNotNull('check_in')->whereNull('check_out')
                ->whereDate('date', '>=', today()->subDay()->toDateString())->orderByDesc('date')->first(['check_in', 'check_out', 'status', 'late_minutes'])
                ?? Attendance::where('employee_id', $employee->id)->whereDate('date', today())->first(['check_in', 'check_out', 'status', 'late_minutes']);
            $balances = $leaveService->balances($employee);
            $pendingLeaves = Leave::where('employee_id', $employee->id)->where('status', 'pending')->count();
        }

        return Inertia::render('Dashboard/Employee', [
            'employee' => $employee ? [
                'full_name' => $employee->full_name,
                'position' => $employee->position?->title,
                'department' => $employee->department?->name,
                'join_date' => $employee->join_date?->toDateString(),
            ] : null,
            'today' => $todayRecord,
            'balances' => $balances,
            'pending_leaves' => $pendingLeaves,
            'today_reports' => $employee ? DailyReport::where('employee_id', $employee->id)->whereDate('report_date', today())->count() : 0,
            'latest_assessment' => $employee ? Assessment::where('employee_id', $employee->id)->orderByDesc('period')->first()?->toRow() : null,
            'upcoming_holidays' => $holidays->upcoming(5),
        ]);
    }
}
