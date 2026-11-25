<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\DataBackupService;
use App\Services\DatabaseBackupService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

/** Backup database (khusus pemegang izin settings.manage; dijaga middleware permission di routes/web.php). */
class BackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.manage');
    }

    public function index(DatabaseBackupService $backup, DataBackupService $data)
    {
        return Inertia::render('Settings/Backup', [
            'files' => $backup->list(),
            'keep' => (int) config('hr.backup.keep', 10),
            'driver' => DB::connection()->getDriverName(),
            'nativeTool' => $backup->nativeToolPath(),
            'pdfCounts' => $data->counts(),
        ]);
    }

    public function store(DatabaseBackupService $backup)
    {
        set_time_limit(300);

        try {
            $result = $backup->create();
        } catch (Throwable $e) {
            Log::error('[Backup] '.$e->getMessage());
            throw ValidationException::withMessages(['general' => 'Backup gagal: '.$e->getMessage()]);
        }

        ActivityLogger::log('backup.created', "Membuat backup database: {$result['file']}", null, auth()->id());

        return back()->with('success', "Backup dibuat: {$result['file']}".($result['note'] ? " — {$result['note']}" : ''));
    }

    /** Backup DATA sebagai PDF: owner | roles | employees | all (gabungan dalam 1 file). */
    public function pdf(Request $request, DataBackupService $data, string $type)
    {
        abort_unless(array_key_exists($type, DataBackupService::TYPES), 404);

        set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $document = $data->build($type, $request->user());
        ActivityLogger::log('backup.pdf', 'Mengunduh backup data (PDF): '.DataBackupService::TYPES[$type], null, $request->user()->id);

        return Pdf::loadView('pdf.data_backup', $document)->setPaper('a4', 'landscape')
            ->download('backup-data-'.$type.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function download(string $filename)
    {
        $path = $this->pathFor($filename);
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'File backup tidak ditemukan.');

        ActivityLogger::log('backup.downloaded', "Mengunduh backup: {$filename}", null, auth()->id());

        return Storage::disk('local')->download($path);
    }

    public function destroy(string $filename)
    {
        $path = $this->pathFor($filename);
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'File backup tidak ditemukan.');

        Storage::disk('local')->delete($path);
        ActivityLogger::log('backup.deleted', "Menghapus backup: {$filename}", null, auth()->id());

        return back()->with('success', 'File backup dihapus.');
    }

    /** Hanya nama file berpola baku yang diterima (mencegah path traversal). */
    private function pathFor(string $filename): ?string
    {
        return preg_match(DatabaseBackupService::FILENAME_PATTERN, $filename) ? DatabaseBackupService::DIR.'/'.$filename : null;
    }
}
