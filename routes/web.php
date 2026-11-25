<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\HistoryCardController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderRatingController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\RecapController;
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

    // --- Perintah Kerja Harian, Penilaian Anggota, History Card ---
    Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::get('/work-orders/ratings', [WorkOrderRatingController::class, 'index'])->name('work-orders.ratings');
    Route::get('/work-orders/ratings/export', [WorkOrderRatingController::class, 'export'])->name('work-orders.ratings.export');
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
    Route::get('/work-orders/{workOrder}/edit', [WorkOrderController::class, 'edit'])->name('work-orders.edit');
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    Route::delete('/work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');
    Route::post('/work-orders/{workOrder}/status', [WorkOrderController::class, 'status'])->name('work-orders.status');
    Route::post('/work-orders/{workOrder}/ratings', [WorkOrderController::class, 'rate'])->name('work-orders.rate');
    Route::get('/history-cards', [HistoryCardController::class, 'index'])->name('history-cards.index');
    Route::get('/history-cards/export', [HistoryCardController::class, 'export'])->name('history-cards.export');
    Route::get('/history-cards/{workOrder}', [HistoryCardController::class, 'show'])->name('history-cards.show');
    Route::get('/history-cards/{workOrder}/pdf', [HistoryCardController::class, 'pdf'])->name('history-cards.pdf');

    // --- Role & hak akses (khusus Owner) ---
    Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [\App\Http\Controllers\RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [\App\Http\Controllers\RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [\App\Http\Controllers\RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'destroy'])->name('roles.destroy');

    // --- Notifikasi dalam aplikasi ---
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [\App\Http\Controllers\NotificationController::class, 'open'])->name('notifications.open');

    // --- Backup Database (super-admin/hr-admin saja, lihat RolePermissionSeeder) ---
    Route::get('/settings/backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::post('/settings/backup', [\App\Http\Controllers\BackupController::class, 'store'])->name('backup.store');
    Route::get('/settings/backup/pdf/{type}', [\App\Http\Controllers\BackupController::class, 'pdf'])->name('backup.pdf');
    Route::get('/settings/backup/{filename}/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
    Route::delete('/settings/backup/{filename}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('backup.destroy');

    // --- Log Aktivitas (audit trail) ---
    Route::get('/settings/activity-log', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-log.index');
    Route::get('/settings/activity-log/export', [\App\Http\Controllers\ActivityLogController::class, 'export'])->name('activity-log.export');
});
