<?php

namespace App\Http\Controllers;

use App\Support\Sql;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Penampil audit trail. Hanya untuk pemegang izin settings.manage (Owner). */
class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.manage');
    }

    public function index(Request $request)
    {
        return Inertia::render('Settings/ActivityLog', [
            'logs' => $this->filtered($request)->paginate(30)->withQueryString(),
            'filters' => [
                'action' => $request->input('action', ''),
                'user_id' => $request->input('user_id', ''),
                'q' => $request->input('q', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
            ],
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['format' => ['required', 'in:xlsx,pdf']]);

        $rows = $this->filtered($request)->limit(5000)->get()->map(fn (ActivityLog $l) => [
            $l->created_at?->format('d/m/Y H:i:s'),
            $l->user?->name ?? 'Sistem',
            $l->action,
            $l->description,
            $l->ip_address,
            collect($l->properties ?? [])->map(fn ($v, $k) => $k.': '.json_encode($v['dari'] ?? null).' -> '.json_encode($v['ke'] ?? null))->implode('; '),
        ])->all();

        return Exporter::download(
            $request->input('format'),
            'log-aktivitas-'.now()->format('Ymd-His'),
            'Log Aktivitas',
            'Maksimal 5.000 entri terbaru sesuai filter',
            ['Waktu', 'Pengguna', 'Aksi', 'Keterangan', 'IP', 'Perubahan'],
            $rows
        );
    }

    private function filtered(Request $request): Builder
    {
        $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = ActivityLog::query()->with('user:id,name,email');

        if ($action = $request->input('action')) {
            $query->where('action', Sql::like(), '%'.str_replace(['%', '_'], ['\\%', '\\_'], $action).'%');
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($q = trim((string) $request->input('q'))) {
            $query->where('description', Sql::like(), '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%');
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
