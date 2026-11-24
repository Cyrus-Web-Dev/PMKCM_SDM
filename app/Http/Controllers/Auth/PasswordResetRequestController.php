<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * "Lupa password" TANPA email: pengguna mengirim permintaan, muncul di halaman Kelola Pengguna milik Owner,
 * dan Owner yang mereset lalu memberitahu orangnya secara langsung. Pembatasan laju:
 *  - tidak boleh kirim lagi selama ada permintaan pending untuk email yang sama,
 *  - jeda 15 menit antar permintaan untuk email yang sama,
 *  - maksimal 3 permintaan per email per 24 jam,
 *  - maksimal 5 permintaan per perangkat (IP + browser) per 1 jam.
 * Jawaban selalu sama baik email terdaftar maupun tidak (tidak membocorkan daftar akun).
 */
class PasswordResetRequestController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/ForgotPassword', ['status' => session('status')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'requester_name' => ['nullable', 'string', 'max:150'],
        ]);

        $email = Str::lower(trim($data['email']));
        $ip = (string) $request->ip();
        $device = sha1($ip.'|'.$request->userAgent());

        if (PasswordResetRequest::where('email', $email)->where('status', 'pending')->exists()) {
            $this->fail('Permintaan reset untuk email ini masih menunggu diproses Owner. Hubungi Owner bila mendesak.');
        }

        $last = PasswordResetRequest::where('email', $email)->latest('created_at')->first();
        if ($last && $last->created_at->gt(now()->subMinutes(15))) {
            $wait = max(1, 15 - (int) floor($last->created_at->diffInMinutes(now(), true)));
            $this->fail("Tunggu sekitar {$wait} menit sebelum mengirim permintaan lagi.");
        }

        if (PasswordResetRequest::where('email', $email)->where('created_at', '>=', now()->subDay())->count() >= 3) {
            $this->fail('Batas 3 permintaan per 24 jam untuk email ini sudah tercapai. Hubungi Owner langsung.');
        }

        if (PasswordResetRequest::where('device', $device)->where('created_at', '>=', now()->subHour())->count() >= 5) {
            $this->fail('Terlalu banyak permintaan dari perangkat ini. Coba lagi dalam 1 jam.');
        }

        $user = User::where('email', $email)->first();

        $req = PasswordResetRequest::create([
            'user_id' => $user?->id,
            'email' => $email,
            'requester_name' => $data['requester_name'] ?? null,
            'ip_address' => $ip,
            'device' => $device,
        ]);

        ActivityLogger::log('password_reset.requested', "Permintaan reset password untuk {$email}".($user ? '' : ' (email tidak dikenal)'), $req, $user?->id);
        Notify::permission('users.manage', 'Permintaan reset password', "{$email} meminta reset password.", route('users.index', [], false));

        return back()->with('status', 'Permintaan terkirim. Hubungi Owner untuk mendapatkan password sementara.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['email' => $message]);
    }
}
