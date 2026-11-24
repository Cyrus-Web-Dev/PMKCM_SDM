<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Pengaturan yang bisa diubah Owner (disimpan di database, berlaku langsung). */
class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.manage');
    }

    public function index()
    {
        return Inertia::render('Settings/Index', [
            'values' => [
                'late_tolerance_minutes' => Setting::lateTolerance(),
                'idle_timeout_minutes' => Setting::idleTimeout(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,120'],
            'idle_timeout_minutes' => ['required', 'integer', function ($attribute, $value, $fail) {
                if ($value !== 0 && ($value < 5 || $value > 480)) {
                    $fail('Isi 0 untuk menonaktifkan, atau antara 5 sampai 480 menit.');
                }
            }],
        ]);

        $old = ['late_tolerance_minutes' => Setting::lateTolerance(), 'idle_timeout_minutes' => Setting::idleTimeout()];
        foreach ($data as $key => $value) {
            Setting::write($key, $value);
        }
        $changes = [];
        foreach ($data as $key => $value) {
            if ((int) $old[$key] !== (int) $value) {
                $changes[$key] = ['dari' => $old[$key], 'ke' => $value];
            }
        }

        ActivityLogger::log('settings.updated', 'Mengubah pengaturan: toleransi terlambat '.$data['late_tolerance_minutes'].' menit, batas idle '.$data['idle_timeout_minutes'].' menit', null, $request->user()->id, $changes ?: null);

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
