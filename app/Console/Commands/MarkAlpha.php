<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkAlpha extends Command
{
    protected $signature = 'sdm:mark-alpha {--date= : Tanggal (Y-m-d). Bawaan: kemarin}';

    protected $description = 'Tandai Alfa untuk karyawan aktif yang tidak absen dan tidak punya izin/cuti pada suatu hari kerja';

    public function handle(HolidayService $holidays): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : today()->subDay();

        if ($date->gte(today())) {
            $this->error('Tanggal harus sebelum hari ini.');

            return self::FAILURE;
        }

        if ($holidays->isWeekend($date) || isset($holidays->between($date, $date)[$date->toDateString()])) {
            $this->info("{$date->toDateString()} bukan hari kerja, dilewati.");

            return self::SUCCESS;
        }

        $recorded = Attendance::whereDate('date', $date->toDateString())->pluck('employee_id')->all();
        $count = 0;

        Employee::where('employment_status', 'active')
            ->whereDate('join_date', '<=', $date->toDateString())
            ->whereNotIn('id', $recorded)
            ->chunkById(200, function ($employees) use ($date, &$count) {
                foreach ($employees as $employee) {
                    Attendance::firstOrCreate(
                        ['employee_id' => $employee->id, 'date' => $date->toDateString()],
                        ['status' => 'alpha', 'late_minutes' => 0, 'notes' => 'Otomatis: tidak ada absensi, izin, maupun cuti.']
                    );
                    $count++;
                }
            });

        $this->info("{$count} karyawan ditandai Alfa untuk {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
