<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetRequestController;
use App\Http\Controllers\Auth\SetupController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Login email + password (tanpa verifikasi kode). Akun dibuat oleh Owner; lupa password -> ajukan permintaan reset ke Owner.
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Wizard awal: hanya aktif selama belum ada pengguna.
    Route::get('setup', [SetupController::class, 'create'])->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:5,1')->name('setup.store');

    // Lupa password tanpa email: karyawan mengirim permintaan, Owner yang mereset.
    Route::get('forgot-password', [PasswordResetRequestController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetRequestController::class, 'store'])->middleware('throttle:10,1')->name('password.request.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
