<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Employee;
use App\Models\User;
use App\Support\TeamScope;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi di dalam aplikasi (ikon lonceng). Kegagalan membuat notifikasi TIDAK pernah menggagalkan
 * aksi utama (mis. laporan tetap tersimpan walau notifikasinya gagal).
 */
class Notify
{
    public static function user(User|int|null $user, string $title, ?string $body = null, ?string $url = null): void
    {
        $id = $user instanceof User ? $user->id : $user;
        if (! $id) {
            return;
        }

        try {
            AppNotification::create(['user_id' => $id, 'title' => $title, 'body' => $body, 'url' => $url]);
        } catch (Throwable $e) {
            Log::warning('[Notify] Gagal membuat notifikasi: '.$e->getMessage());
        }
    }

    /**
     * Kirim ke semua pengguna aktif pemegang izin $permission. Bila $about (karyawan) diberikan, hanya yang
     * berwenang atas karyawan itu (HR-wide / atasan tim) yang menerima. $exceptUserId = pelaku, tidak dinotifikasi.
     */
    public static function permission(string $permission, string $title, ?string $body = null, ?string $url = null, ?Employee $about = null, ?int $exceptUserId = null): void
    {
        try {
            $users = User::permission($permission)->where('status', 'active')->get();

            $rows = [];
            foreach ($users as $user) {
                if ($user->id === $exceptUserId) {
                    continue;
                }
                if ($about) {
                    if ($user->employee && $user->employee->id === $about->id) {
                        continue;
                    }
                    $allowed = TeamScope::isHrWide($user)
                        || TeamScope::constrainEmployees(Employee::query()->whereKey($about->id), $user)->exists();
                    if (! $allowed) {
                        continue;
                    }
                }
                $rows[] = ['user_id' => $user->id, 'title' => $title, 'body' => $body, 'url' => $url, 'created_at' => now(), 'updated_at' => now()];
            }

            if ($rows) {
                AppNotification::insert($rows);
            }
        } catch (Throwable $e) {
            Log::warning('[Notify] Gagal membuat notifikasi: '.$e->getMessage());
        }
    }
}
