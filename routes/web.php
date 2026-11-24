<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
Route::redirect('/', '/login')->name('welcome');


require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Halaman internal (WAJIB login + akun aktif). Otorisasi per-permission
| sudah ditangani di masing-masing constructor controller (Spatie Permission).
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'has.role'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- Profil sendiri ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::get('/profile/{user}/photo', [ProfileController::class, 'photo'])->name('profile.photo');

    // --- Karyawan (master data) ---
    Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('/employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::resource('employees', EmployeeController::class);

    // --- Departemen & Jabatan ---
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    Route::post('/positions', [DepartmentController::class, 'storePosition'])->name('positions.store');

    // --- Kelola Pengguna & Role ---
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/reject', [UserManagementController::class, 'reject'])->name('users.reject');
    Route::put('/users/{user}/employee', [UserManagementController::class, 'linkEmployee'])->name('users.link-employee');
    Route::post('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('/users/{user}/roles', [UserManagementController::class, 'updateRoles'])->name('users.roles');
    Route::post('/users/reset-requests/{resetRequest}/dismiss', [UserManagementController::class, 'dismissResetRequest'])->name('users.reset-requests.dismiss');
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');

    // --- Absensi ---
    Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::put('/attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
    Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn'])->middleware('offline.dedupe')->name('attendances.check-in');
    Route::post('/attendances/check-out', [AttendanceController::class, 'checkOut'])->middleware('offline.dedupe')->name('attendances.check-out');
    Route::get('/attendances/{attendance}/photo/{type}', [AttendanceController::class, 'photo'])->name('attendances.photo');
    Route::get('/my/attendances', [AttendanceController::class, 'myHistory'])->name('attendances.my-history');

    // --- Cuti & Izin ---
    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
    Route::post('/leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leaves.cancel');
    Route::get('/my/leaves', [LeaveController::class, 'myLeaves'])->name('leaves.mine');


    // --- Laporan Harian (tiap karyawan mengisi sendiri; "Semua Laporan" untuk pemegang izin daily_reports.view) ---
    Route::get('/daily-reports', [DailyReportController::class, 'index'])->name('daily-reports.index');
    Route::post('/daily-reports', [DailyReportController::class, 'store'])->middleware('offline.dedupe')->name('daily-reports.store');
    Route::get('/daily-reports/all', [DailyReportController::class, 'all'])->name('daily-reports.all');
    Route::get('/daily-reports/export', [DailyReportController::class, 'export'])->name('daily-reports.export');
    Route::get('/daily-reports/photos/{photo}', [DailyReportController::class, 'photo'])->name('daily-reports.photos.show');
    Route::get('/daily-reports/{dailyReport}/detail', [DailyReportController::class, 'detail'])->name('daily-reports.detail');
    Route::post('/daily-reports/{dailyReport}/comments', [DailyReportController::class, 'comment'])->name('daily-reports.comments.store');
    Route::post('/daily-reports/{dailyReport}/review', [DailyReportController::class, 'review'])->name('daily-reports.review');
    Route::put('/daily-reports/{dailyReport}', [DailyReportController::class, 'update'])->name('daily-reports.update');
    Route::delete('/daily-reports/{dailyReport}', [DailyReportController::class, 'destroy'])->name('daily-reports.destroy');

    // --- Rekap kinerja (harian / mingguan / bulanan / tahunan) ---
    Route::get('/recap', [RecapController::class, 'index'])->name('recap.index');
    Route::get('/recap/export', [RecapController::class, 'export'])->name('recap.export');

    // --- Penilaian outsourcing & Form Penilaian ---
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::get('/assessments/export', [AssessmentController::class, 'export'])->name('assessments.export');
    Route::get('/assessments/sheet', [AssessmentController::class, 'sheet'])->name('assessments.sheet');
    Route::get('/assessments/sheet/pdf', [AssessmentController::class, 'sheetPdf'])->name('assessments.sheet.pdf');
    Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
    Route::get('/my/assessments', [AssessmentController::class, 'mine'])->name('assessments.mine');

    // --- Shift kerja ---
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');



    // --- Hari Libur (nasional dari API + libur/hari kerja pengganti perusahaan) ---
    Route::get('/holidays', [\App\Http\Controllers\HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [\App\Http\Controllers\HolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/refresh', [\App\Http\Controllers\HolidayController::class, 'refresh'])->name('holidays.refresh');
    Route::delete('/holidays/{holiday}', [\App\Http\Controllers\HolidayController::class, 'destroy'])->name('holidays.destroy');




    // --- Notifikasi dalam aplikasi ---
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [\App\Http\Controllers\NotificationController::class, 'open'])->name('notifications.open');

    // --- Pengaturan (toleransi terlambat, auto-logout) ---
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');

    // --- Backup Database (super-admin/hr-admin saja, lihat RolePermissionSeeder) ---
    Route::get('/settings/backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::post('/settings/backup', [\App\Http\Controllers\BackupController::class, 'store'])->name('backup.store');
    Route::get('/settings/backup/{filename}/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
    Route::delete('/settings/backup/{filename}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('backup.destroy');

    // --- Log Aktivitas (audit trail) ---
    Route::get('/settings/activity-log', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-log.index');
    Route::get('/settings/activity-log/export', [\App\Http\Controllers\ActivityLogController::class, 'export'])->name('activity-log.export');
});
