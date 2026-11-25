<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API (v1) — token-based lewat Laravel Sanctum, TERPISAH dari sesi web.
| Ditujukan untuk aplikasi mobile atau integrasi pihak ketiga.
| Base URL: /api/v1/...   Semua endpoint (kecuali /login) butuh header:
|   Authorization: Bearer <token>
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.')->group(function () {

    // Login: 5 percobaan / menit per IP, supaya tidak bisa dipakai brute-force password.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        // Karyawan (izin employees.view/manage dicek di dalam controller, sama seperti web).
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');

    });
});

