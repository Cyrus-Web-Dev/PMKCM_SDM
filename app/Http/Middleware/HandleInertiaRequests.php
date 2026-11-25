<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang dikirim ke SETIAP halaman Vue ($page.props.*).
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                    'employee' => $user->employee ? [
                        'id' => $user->employee->id,
                        'nip' => $user->employee->nip,
                        'full_name' => $user->employee->full_name,
                        'photo_url' => $user->employee->photo_url,
                        'position_title' => $user->employee->position?->title,
                        'department_name' => $user->employee->department?->name,
                    ] : null,
                ] : null,
            ],
            // Jumlah user menunggu persetujuan (lencana menu "Kelola Pengguna", hanya utk yg punya izin users.manage)
            'pending_users' => fn () => $user && $user->can('users.manage') ? User::where('status', 'pending')->count() : 0,
            'pending_resets' => fn () => $user && $user->can('users.manage') ? PasswordResetRequest::where('status', 'pending')->count() : 0,
            'notifications_unread' => fn () => $user ? AppNotification::where('user_id', $user->id)->whereNull('read_at')->count() : 0,
            // Batas diam (menit) sebelum auto-logout di browser; 0 = nonaktif.
            'idle_timeout' => fn () => $user ? (int) config('hr.idle_timeout_minutes') : 0,
            // Daftar tim kerja (Gudang, Cooler, Kiln, Coal Mill) untuk dropdown di berbagai halaman.
            'teams' => config('hr.teams'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Password sementara (ditampilkan SEKALI ke admin setelah akun dibuat / direset).
                'temp_password' => fn () => $request->session()->get('temp_password'),
            ],
        ];
    }
}
