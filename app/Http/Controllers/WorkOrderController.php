<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRating;
use App\Models\Assessment;
use App\Services\ActivityLogger;
use App\Services\Notify;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Perintah Kerja Harian: Ka. URS/Owner (izin work_orders.manage) membuat perintah berisi kepala kerja, anggota,
 * uraian, dan status. Kepala kerja mengubah status dan menilai anggota setelah selesai.
 */
class WorkOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:work_orders.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys(WorkOrder::STATUSES))],
            'team' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $query = WorkOrder::query()->visibleTo($user)->with(['leader:id,full_name', 'members:id,full_name']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($team = $request->input('team')) {
            $query->where('team', $team);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('order_date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('order_date', '<=', $to);
        }
        if ($request->boolean('mine') && $user->employee) {
            $query->involving($user->employee->id);
        }
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('description', 'ilike', $like)->orWhere('number', 'ilike', $like)->orWhere('area', 'ilike', $like)
                    ->orWhereHas('leader', fn ($e) => $e->where('full_name', 'ilike', $like))
                    ->orWhereHas('members', fn ($e) => $e->where('full_name', 'ilike', $like));
            });
        }

        return Inertia::render('WorkOrders/Index', [
            'orders' => $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn (WorkOrder $o) => $o->toRow()),
            'filters' => [
                'status' => $request->input('status', ''),
                'team' => $request->input('team', ''),
                'q' => $request->input('q', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'mine' => $request->boolean('mine'),
            ],
            'statuses' => WorkOrder::STATUSES,
            'canManage' => $user->can('work_orders.manage'),
            'hasEmployee' => (bool) $user->employee,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('WorkOrders/Form', [
            'order' => null,
            'employees' => $this->employeesFor($request->user()),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $user = $request->user();
        $this->assertInScope($user, $data);

        $order = null;
        for ($try = 1; $try <= 3 && ! $order; $try++) {
            try {
                $order = DB::transaction(function () use ($data, $user) {
                    $o = WorkOrder::create([
                        'number' => WorkOrder::nextNumber(Carbon::parse($data['order_date'])),
                        'order_date' => $data['order_date'],
                        'start_date' => $data['start_date'] ?? $data['order_date'],
                        'leader_id' => $data['leader_id'],
                        'description' => $data['description'],
                        'status' => $data['status'] ?? 'direncanakan',
                        'team' => $data['team'] ?? null,
                        'area' => $data['area'] ?? null,
                        'created_by' => $user->id,
                    ]);
                    $o->members()->sync($data['member_ids']);

                    return $o;
                });
            } catch (QueryException $e) {
                if ($try === 3 || ! str_contains($e->getMessage(), 'work_orders_number_unique')) {
                    throw $e;
                }
            }
        }

        ActivityLogger::log('work_order.created', "Membuat perintah kerja {$order->number}", $order, $user->id);
        $this->notifyInvolved($order, 'Perintah kerja baru', "{$order->number}: ".mb_strimwidth($order->description, 0, 120, '…'), $user->id);

        return redirect()->route('work-orders.show', $order)->with('success', "Perintah kerja {$order->number} dibuat.");
    }

    public function show(Request $request, WorkOrder $workOrder)
    {
        $user = $request->user();
        $this->authorizeView($user, $workOrder);

        $workOrder->load(['leader:id,full_name,nip', 'members:id,full_name,nip,team', 'creator:id,name', 'ratings']);
        $own = $user->employee?->id;
        $isLeader = $own && (int) $workOrder->leader_id === $own;
        $canManage = $user->can('work_orders.manage');
        $seeAll = $isLeader || $user->can('work_orders.view');
        $ratings = $workOrder->ratings->keyBy('employee_id');

        $allowed = WorkOrder::TRANSITIONS[$workOrder->status] ?? [];
        if ($canManage) {
            $allowed = array_merge($allowed, WorkOrder::REOPEN[$workOrder->status] ?? []);
        }

        return Inertia::render('WorkOrders/Show', [
            'order' => $workOrder->toRow() + ['creator' => $workOrder->creator?->name, 'created_at' => $workOrder->created_at->format('d/m/Y H:i')],
            // Anggota hanya melihat nilainya sendiri; kepala kerja & pemegang izin melihat semua.
            'members' => $workOrder->members->map(fn (Employee $m) => [
                'id' => $m->id,
                'name' => $m->full_name,
                'nip' => $m->nip,
                'team' => $m->team,
                'rating' => ($seeAll || $m->id === $own) ? $ratings->get($m->id)?->toRow() : null,
            ])->values(),
            'reports' => DailyReport::with('employee:id,full_name')->where('work_order_id', $workOrder->id)
                ->orderByDesc('report_date')->limit(30)->get()
                ->map(fn (DailyReport $r) => $r->toRow(true))->values(),
            'transitions' => $allowed,
            'statuses' => WorkOrder::STATUSES,
            'canManage' => $canManage,
            'canChangeStatus' => ($isLeader || $canManage) && $allowed !== [],
            'canRate' => $workOrder->status === 'selesai' && $this->canRate($user, $workOrder),
            'criteria' => AssessmentController::criteria(),
            'today' => today()->toDateString(),
        ]);
    }

    public function edit(Request $request, WorkOrder $workOrder)
    {
        $workOrder->load('members:id');

        return Inertia::render('WorkOrders/Form', [
            'order' => $workOrder->toRow() + ['member_ids' => $workOrder->members->pluck('id')->all()],
            'employees' => $this->employeesFor($request->user()),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $data = $this->validated($request, false);
        $user = $request->user();
        $this->assertInScope($user, $data);

        $before = $workOrder->members()->pluck('employees.full_name')->sort()->values()->all();

        DB::transaction(function () use ($workOrder, $data) {
            $workOrder->update([
                'order_date' => $data['order_date'],
                'start_date' => $data['start_date'] ?? $workOrder->start_date,
                'leader_id' => $data['leader_id'],
                'description' => $data['description'],
                'team' => $data['team'] ?? null,
                'area' => $data['area'] ?? null,
            ]);
            $workOrder->members()->sync($data['member_ids']);
            // Nilai milik anggota yang dikeluarkan dari perintah ikut dihapus (tidak ada nilai yatim).
            WorkOrderRating::where('work_order_id', $workOrder->id)->whereNotIn('employee_id', $data['member_ids'])->delete();
        });

        $changes = ActivityLogger::changes($workOrder);
        $after = $workOrder->members()->pluck('employees.full_name')->sort()->values()->all();
        if ($before !== $after) {
            $changes['anggota'] = ['dari' => implode(', ', $before), 'ke' => implode(', ', $after)];
        }
        ActivityLogger::log('work_order.updated', "Mengubah perintah kerja {$workOrder->number}", $workOrder, $user->id, $changes ?: null);

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Perintah kerja diperbarui.');
    }

    public function destroy(Request $request, WorkOrder $workOrder)
    {
        abort_if($workOrder->status === 'selesai', 422, 'Perintah kerja yang sudah selesai tidak bisa dihapus (menjadi History Card). Ubah statusnya bila memang keliru.');

        $number = $workOrder->number;
        $workOrder->delete();
        ActivityLogger::log('work_order.deleted', "Menghapus perintah kerja {$number}", null, $request->user()->id);

        return redirect()->route('work-orders.index')->with('success', "Perintah kerja {$number} dihapus.");
    }

    /** Ubah status: kepala kerja (alur maju) atau pemegang work_orders.manage (juga boleh membuka kembali). */
    public function status(Request $request, WorkOrder $workOrder)
    {
        $user = $request->user();
        $this->authorizeView($user, $workOrder);
        $own = $user->employee?->id;
        $isLeader = $own && (int) $workOrder->leader_id === $own;
        $canManage = $user->can('work_orders.manage');
        abort_unless($isLeader || $canManage, 403, 'Hanya kepala kerja atau pengelola yang boleh mengubah status.');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WorkOrder::STATUSES))],
            'end_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $from = $workOrder->status;
        $to = $data['status'];
        $allowed = WorkOrder::TRANSITIONS[$from] ?? [];
        if ($canManage) {
            $allowed = array_merge($allowed, WorkOrder::REOPEN[$from] ?? []);
        }
        abort_unless(in_array($to, $allowed, true), 422, 'Perubahan status ini tidak diizinkan.');

        $update = ['status' => $to];
        if ($to === 'berjalan') {
            $update['start_date'] = $workOrder->start_date ?? today();
            $update['end_date'] = null;
        } elseif ($to === 'selesai') {
            $start = $workOrder->start_date ?? $workOrder->order_date;
            $end = Carbon::parse($data['end_date'] ?? today());
            if ($end->lt($start)) {
                throw ValidationException::withMessages(['end_date' => 'Tanggal selesai tidak boleh sebelum tanggal mulai ('.$start->format('d/m/Y').').']);
            }
            $update['start_date'] = $start;
            $update['end_date'] = $end;
        }

        $workOrder->update($update);

        $label = WorkOrder::STATUSES[$to];
        ActivityLogger::log('work_order.status', "Perintah kerja {$workOrder->number}: {$from} -> {$to}", $workOrder, $user->id, ActivityLogger::changes($workOrder) ?: null);
        $this->notifyInvolved($workOrder, "Perintah kerja {$label}", "{$workOrder->number} kini berstatus {$label}.", $user->id);

        return back()->with('success', "Status diubah menjadi {$label}.");
    }

    /** Penilaian Anggota: kepala kerja (atau pengelola) menilai satu anggota setelah perintah selesai. */
    public function rate(Request $request, WorkOrder $workOrder)
    {
        $user = $request->user();
        $this->authorizeView($user, $workOrder);
        abort_unless($workOrder->status === 'selesai', 422, 'Penilaian hanya bisa dilakukan setelah perintah kerja selesai.');
        abort_unless($this->canRate($user, $workOrder), 403, 'Hanya kepala kerja yang boleh menilai anggota.');

        $rules = ['employee_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:1000']];
        foreach (array_keys(Assessment::CRITERIA) as $key) {
            $rules[$key] = ['required', 'integer', 'between:0,100'];
        }
        $data = $request->validate($rules);

        $member = $workOrder->members()->where('employees.id', $data['employee_id'])->first(['employees.id', 'employees.full_name', 'employees.user_id']);
        abort_unless($member, 422, 'Karyawan ini bukan anggota perintah kerja tersebut.');

        $scores = [];
        foreach (array_keys(Assessment::CRITERIA) as $key) {
            $scores[$key] = (int) $data[$key];
        }

        $rating = WorkOrderRating::updateOrCreate(
            ['work_order_id' => $workOrder->id, 'employee_id' => $member->id],
            $scores + ['total' => (int) round(array_sum($scores) / count($scores)), 'note' => $data['note'] ?? null, 'rated_by' => $user->id]
        );

        ActivityLogger::log('work_order.rated', "Menilai {$member->full_name} pada {$workOrder->number} (nilai {$rating->total})", $rating, $user->id);
        Notify::user($member->user_id, 'Penilaian anggota baru', "{$workOrder->number}: nilai {$rating->total} ({$rating->toRow()['grade']}).", route('work-orders.show', $workOrder, false));

        return back()->with('success', "Penilaian {$member->full_name} tersimpan (nilai {$rating->total}).");
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'order_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'leader_id' => ['required', 'integer', 'exists:employees,id'],
            'member_ids' => ['required', 'array', 'min:1', 'max:50'],
            'member_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'description' => ['required', 'string', 'max:2000'],
            'team' => ['nullable', Rule::in(config('hr.teams'))],
            'area' => ['nullable', 'string', 'max:100'],
        ];
        if ($creating) {
            $rules['status'] = ['nullable', Rule::in(['direncanakan', 'berjalan'])];
        }

        $data = $request->validate($rules, [
            'member_ids.required' => 'Pilih minimal 1 anggota kerja.',
            'member_ids.min' => 'Pilih minimal 1 anggota kerja.',
        ]);
        $data['leader_id'] = (int) $data['leader_id'];
        $data['member_ids'] = array_map('intval', $data['member_ids']);

        if (in_array($data['leader_id'], $data['member_ids'], true)) {
            throw ValidationException::withMessages(['member_ids' => 'Kepala kerja tidak perlu dipilih lagi sebagai anggota.']);
        }

        return $data;
    }

    /** Kepala kerja & semua anggota harus karyawan aktif dalam wewenang pembuatnya. */
    private function assertInScope(User $user, array $data): void
    {
        $allowed = TeamScope::constrainEmployees(Employee::where('employment_status', 'active'), $user)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $wanted = array_merge([$data['leader_id']], $data['member_ids']);

        abort_if(array_diff($wanted, $allowed) !== [], 403, 'Ada karyawan yang tidak aktif atau di luar wewenangmu.');
    }

    private function employeesFor(User $user)
    {
        return TeamScope::constrainEmployees(Employee::where('employment_status', 'active'), $user)
            ->orderBy('full_name')->get(['id', 'full_name', 'nip', 'team']);
    }

    private function authorizeView(User $user, WorkOrder $order): void
    {
        abort_unless(WorkOrder::visibleTo($user)->whereKey($order->id)->exists(), 403, 'Kamu tidak terlibat dalam perintah kerja ini.');
    }

    private function canRate(User $user, WorkOrder $order): bool
    {
        $own = $user->employee?->id;

        return ($own && (int) $order->leader_id === $own) || $user->can('work_orders.manage');
    }

    /** Beri tahu kepala kerja + semua anggota (kecuali pelaku). */
    private function notifyInvolved(WorkOrder $order, string $title, string $body, int $exceptUserId): void
    {
        $ids = $order->members()->pluck('employees.id')->push($order->leader_id)->unique()->all();
        Employee::whereIn('id', $ids)->whereNotNull('user_id')->pluck('user_id')->each(function ($uid) use ($title, $body, $order, $exceptUserId) {
            if ((int) $uid !== $exceptUserId) {
                Notify::user($uid, $title, $body, route('work-orders.show', $order, false));
            }
        });
    }
}
