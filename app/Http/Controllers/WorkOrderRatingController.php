<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\WorkOrder;
use App\Models\WorkOrderRating;
use App\Support\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Daftar Penilaian Anggota. Anggota hanya melihat nilainya sendiri; kepala kerja melihat nilai yang ia berikan;
 * pemegang work_orders.view melihat semua (sesuai wewenang tim).
 */
class WorkOrderRatingController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('WorkOrders/Ratings', [
            'ratings' => $this->query($request)->paginate(15)->withQueryString()->through(fn (WorkOrderRating $r) => $this->row($r)),
            'filters' => [
                'q' => $request->input('q', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
            ],
            'criteria' => AssessmentController::criteria(),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['format' => ['required', 'in:xlsx,pdf']]);

        $rows = $this->query($request)->limit(5000)->get()->map(function (WorkOrderRating $r) {
            $x = $this->row($r);

            return [
                $x['number'], $x['end_date'], $x['leader'], $x['employee'],
                ...array_values($x['scores']), $x['total'], $x['grade'], $x['note'],
            ];
        })->all();

        return Exporter::download(
            $request->input('format'),
            'penilaian-anggota-'.now()->format('Ymd'),
            'Penilaian Anggota',
            'Per perintah kerja',
            ['No. Perintah', 'Tgl Selesai', 'Kepala Kerja', 'Anggota', ...array_values(Assessment::CRITERIA), 'Nilai Akhir', 'Predikat', 'Catatan'],
            $rows
        );
    }

    private function query(Request $request): Builder
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $user = $request->user();
        $own = $user->employee?->id ?? 0;

        $query = WorkOrderRating::query()
            ->with(['employee:id,full_name', 'workOrder:id,number,end_date,leader_id,description', 'workOrder.leader:id,full_name'])
            ->whereHas('workOrder', fn ($w) => $w->visibleTo($user));

        if (! $user->can('work_orders.view')) {
            $query->where(fn ($w) => $w->where('employee_id', $own)->orWhereHas('workOrder', fn ($o) => $o->where('leader_id', $own)));
        }
        if ($from = $request->input('from')) {
            $query->whereHas('workOrder', fn ($o) => $o->whereDate('end_date', '>=', $from));
        }
        if ($to = $request->input('to')) {
            $query->whereHas('workOrder', fn ($o) => $o->whereDate('end_date', '<=', $to));
        }
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->whereHas('employee', fn ($e) => $e->where('full_name', 'ilike', $like))
                    ->orWhereHas('workOrder', fn ($o) => $o->where('number', 'ilike', $like)->orWhere('description', 'ilike', $like));
            });
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    private function row(WorkOrderRating $r): array
    {
        return $r->toRow() + [
            'order_id' => $r->work_order_id,
            'number' => $r->workOrder?->number,
            'description' => $r->workOrder?->description,
            'end_date' => $r->workOrder?->end_date?->toDateString(),
            'leader' => $r->workOrder?->leader?->full_name,
            'employee' => $r->employee?->full_name,
        ];
    }
}
