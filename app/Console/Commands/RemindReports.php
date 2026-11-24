<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Services\Notify;
use Illuminate\Console\Command;

class RemindReports extends Command
{
    protected $signature = 'sdm:remind-reports';

    protected $description = 'Ingatkan karyawan yang sudah check-in hari ini tetapi belum mengisi laporan harian';

    private const TITLE = 'Pengingat laporan harian';

    public function handle(): int
    {
        $checkedIn = Attendance::whereDate('date', today())->whereNotNull('check_in')->pluck('employee_id');
        $reported = DailyReport::whereDate('report_date', today())->pluck('employee_id');

        $count = 0;
        Employee::whereIn('id', $checkedIn)->whereNotIn('id', $reported)
            ->whereNotNull('user_id')->where('employment_status', 'active')
            ->get(['id', 'user_id'])
            ->each(function (Employee $e) use (&$count) {
                // Satu pengingat per pengguna per hari.
                $already = AppNotification::where('user_id', $e->user_id)->where('title', self::TITLE)->whereDate('created_at', today())->exists();
                if (! $already) {
                    Notify::user($e->user_id, self::TITLE, 'Kamu sudah check-in hari ini tetapi belum mengisi laporan harian.', route('daily-reports.index', [], false));
                    $count++;
                }
            });

        $this->info("{$count} pengingat dikirim.");

        return self::SUCCESS;
    }
}
