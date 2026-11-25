<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\WorkOrder;
use App\Services\Notify;
use Illuminate\Console\Command;

class RemindReports extends Command
{
    protected $signature = 'sdm:remind-reports';

    protected $description = 'Ingatkan kepala kerja & anggota perintah kerja yang sedang berjalan tetapi belum mengisi laporan harian hari ini';

    private const TITLE = 'Pengingat laporan harian';

    public function handle(): int
    {
        // Semua karyawan yang terlibat (kepala kerja / anggota) pada perintah kerja berstatus "berjalan".
        $active = WorkOrder::where('status', 'berjalan')->with('members:id')->get(['id', 'leader_id']);
        $ids = $active->pluck('leader_id')->merge($active->flatMap(fn (WorkOrder $o) => $o->members->pluck('id')))->unique()->values();

        $reported = DailyReport::whereDate('report_date', today())->pluck('employee_id');

        $count = 0;
        Employee::whereIn('id', $ids)->whereNotIn('id', $reported)
            ->whereNotNull('user_id')->where('employment_status', 'active')
            ->get(['id', 'user_id'])
            ->each(function (Employee $e) use (&$count) {
                // Satu pengingat per pengguna per hari.
                $already = AppNotification::where('user_id', $e->user_id)->where('title', self::TITLE)->whereDate('created_at', today())->exists();
                if (! $already) {
                    Notify::user($e->user_id, self::TITLE, 'Kamu terlibat dalam perintah kerja yang sedang berjalan tetapi belum mengisi laporan harian hari ini.', route('daily-reports.index', [], false));
                    $count++;
                }
            });

        $this->info("{$count} pengingat dikirim.");

        return self::SUCCESS;
    }
}
