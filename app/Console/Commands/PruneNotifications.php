<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'sdm:prune-notifications';

    protected $description = 'Hapus notifikasi lama (sudah dibaca > 60 hari, atau apa pun > 180 hari)';

    public function handle(): int
    {
        $read = AppNotification::whereNotNull('read_at')->where('read_at', '<', now()->subDays(60))->delete();
        $old = AppNotification::where('created_at', '<', now()->subDays(180))->delete();
        $this->info('Dihapus: '.($read + $old).' notifikasi.');

        return self::SUCCESS;
    }
}
