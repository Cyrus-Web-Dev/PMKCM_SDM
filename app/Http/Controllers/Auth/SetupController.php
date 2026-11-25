<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** Wizard pertama kali: membuat akun Owner (super-admin). Otomatis tertutup begitu ada pengguna. */
class SetupController extends Controller
{
    public function create()
    {
        abort_if(User::exists(), 404);

        return Inertia::render('Auth/Setup');
    }

    public function store(Request $request)
    {
        abort_if(User::exists(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Pastikan role, izin, dan departemen awal tersedia (idempoten).
        Artisan::call('db:seed', ['--force' => true]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'],
                'status' => 'active',
                'must_change_password' => false,
            ]);
            $user->syncRoles(['super-admin']);

            Employee::create([
                'user_id' => $user->id,
                'nip' => 'OWN-0001',
                'full_name' => $data['name'],
                'gender' => 'L',
                'employment_type' => 'tetap',
                'join_date' => today()->toDateString(),
                'employment_status' => 'active',
            ]);

            return $user;
        });

        ActivityLogger::log('setup.completed', "Akun Owner dibuat lewat wizard awal: {$user->email}", $user, $user->id);

        return redirect()->route('login')->with('status', 'Akun Owner berhasil dibuat. Silakan login.');
    }
}
