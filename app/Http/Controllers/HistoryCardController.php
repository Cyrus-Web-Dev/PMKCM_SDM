<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WorkOrder;
use App\Support\Exporter;
use App\Support\TeamScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** History Card: arsip perintah kerja yang sudah SELESAI (uraian, tanggal mulai/selesai, kepala kerja, anggota). */
class HistoryCardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('HistoryCards/Index', [
            'cards' => $this->query($request)->paginate(15)->withQueryString()->through(fn (WorkOrder $o) => $o->toRow()),
            'filters' => [
                'q' => $request->input('q', ''),
                'team' => $request->input('team', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'employee_id' => $request->input('employee_id', ''),
            ],
            'employees' => $user->can('work_orders.view')
                ? TeamScope::constrainEmployees(Employee::where('employment_status', 'active'), $user)->orderBy('full_name')->get(['id', 'full_name'])
                : [],
        ]);
    }

    public function show(Request $request, WorkOrder $workOrder)
    {
        return Inertia::render('HistoryCards/Card', ['card' => $this->card($request, $workOrder)]);
    }

    public function pdf(Request $request, WorkOrder $workOrder)
    {
        return Pdf::loadView('pdf.history_card', ['card' => $this->card($request, $workOrder)])
            ->setPaper('a4', 'portrait')->download('history-card-'.Str::slug($workOrder->number).'.pdf');
    }

    public function export(Request $request)
    {
        $request->validate(['format' => ['required', 'in:xlsx,pdf']]);

        $rows = $this->query($request)->limit(5000)->get()->map(function (WorkOrder $o) {
            $r = $o->toRow();

            return [
                $r['number'], $r['description'], $r['start_date'], $r['end_date'], $r['leader'],
                collect($r['members'])->pluck('name')->implode(', '), $r['team'], $r['area'],
            ];
        })->all();

        return Exporter::download(
            $request->input('format'),
            'history-card-'.now()->format('Ymd'),
            'History Card',
            'Perintah kerja yang sudah selesai',
            ['No. Perintah', 'Uraian Pekerjaan', 'Tanggal Mulai', 'Tanggal Selesai', 'Kepala Kerja', 'Anggota Kerja', 'Tim', 'Area'],
            $rows
        );
    }

    private function card(Request $request, WorkOrder $workOrder): array
    {
        abort_unless($workOrder->status === 'selesai', 404);
        abort_unless(WorkOrder::visibleTo($request->user())->whereKey($workOrder->id)->exists(), 403, 'Kamu tidak terlibat dalam pekerjaan ini.');
        $workOrder->load(['leader:id,full_name,nip', 'members:id,full_name,nip']);

        return $workOrder->toRow();
    }

    private function query(Request $request): Builder
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'team' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $query = WorkOrder::query()->visibleTo($request->user())->where('status', 'selesai')->with(['leader:id,full_name', 'members:id,full_name']);

        if ($team = $request->input('team')) {
            $query->where('team', $team);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('end_date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('end_date', '<=', $to);
        }
        if ($employeeId = $request->input('employee_id')) {
            $query->involving((int) $employeeId);
        }
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('description', 'ilike', $like)->orWhere('number', 'ilike', $like)->orWhere('area', 'ilike', $like)
                    ->orWhereHas('leader', fn ($e) => $e->where('full_name', 'ilike', $like))
                    ->orWhereHas('members', fn ($e) => $e->where('full_name', 'ilike', $like));
            });
        }

        return $query->orderByDesc('end_date')->orderByDesc('id');
    }
}
