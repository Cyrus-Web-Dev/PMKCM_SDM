<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Backup DATA dalam bentuk dokumen PDF yang bisa dibaca & diarsipkan (bukan untuk restore - untuk restore pakai
 * Backup Database). Data sensitif (gaji, rekening, NPWP, BPJS, password) TIDAK dimasukkan.
 *
 * Tipe: owner (milik super-admin), roles (role tambahan buatan Owner), employees (data karyawan), all (gabungan).
 */
class DataBackupService
{
    public const TYPES = [
        'owner' => 'Data Owner (Super Admin)',
        'roles' => 'Data Role Tambahan',
        'employees' => 'Data Karyawan',
        'all' => 'Semua Data (Owner + Role Tambahan + Karyawan)',
    ];

    private const LOG_LIMIT = 500;
    private const LIST_LIMIT = 500;

    /** Role yang BUKAN "role tambahan": Owner dan role karyawan bawaan. */
    private const BASE_ROLES = ['super-admin', 'karyawan'];

    public function build(string $type, User $by): array
    {
        $sections = [];
        if (in_array($type, ['owner', 'all'], true)) {
            $sections[] = $this->owner();
        }
        if (in_array($type, ['roles', 'all'], true)) {
            $sections[] = $this->roles();
        }
        if (in_array($type, ['employees', 'all'], true)) {
            $sections[] = $this->employees();
        }

        return [
            'title' => 'Backup Data — '.self::TYPES[$type],
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'generated_by' => $by->name,
            'sections' => $sections,
        ];
    }

    public function counts(): array
    {
        return [
            'owner' => User::role('super-admin')->count(),
            'roles' => Role::whereNotIn('name', self::BASE_ROLES)->count(),
            'employees' => Employee::withTrashed()->count(),
        ];
    }

    // ---------------------------------------------------------------- Owner

    private function owner(): array
    {
        $owners = User::role('super-admin')->orderBy('id')->get();
        $ids = $owners->pluck('id')->all();
        $blocks = [];

        $blocks[] = $this->table('Akun Owner', ['Nama', 'Email', 'Status', 'Dibuat', 'Login Terakhir', 'IP Terakhir'],
            $owners->map(fn (User $u) => [$u->name, $u->email, $u->status, $this->dt($u->created_at), $this->dt($u->last_login_at), $u->last_login_ip])->all());

        $blocks[] = $this->employeeTable('Data Karyawan milik Owner', Employee::withTrashed()->whereIn('user_id', $ids)->pluck('id')->all());

        $orders = DB::table('work_orders')->leftJoin('employees as l', 'l.id', '=', 'work_orders.leader_id')
            ->whereIn('work_orders.created_by', $ids)->orderByDesc('work_orders.order_date');
        $blocks[] = $this->table('Perintah Kerja yang dibuat Owner'.$this->shown($this->total($orders)), ['No.', 'Tgl Perintah', 'Kepala Kerja', 'Status', 'Uraian'],
            $orders->limit(self::LIST_LIMIT)->get(['work_orders.number', 'work_orders.order_date', 'l.full_name as leader', 'work_orders.status', 'work_orders.description'])
                ->map(fn ($r) => [$r->number, $this->d($r->order_date), $r->leader, $r->status, $r->description])->all());

        $ass = DB::table('assessments')->join('employees as e', 'e.id', '=', 'assessments.employee_id')->whereIn('assessments.assessed_by', $ids)->orderByDesc('assessments.period');
        $blocks[] = $this->table('Penilaian Bulanan yang diberikan Owner'.$this->shown($this->total($ass)), ['Periode', 'Karyawan', 'Nilai Akhir', 'Predikat', 'Catatan'],
            $ass->limit(self::LIST_LIMIT)->get(['assessments.period', 'e.full_name', 'assessments.total', 'assessments.note'])
                ->map(fn ($r) => [substr((string) $r->period, 0, 7), $r->full_name, $r->total, \App\Models\Assessment::grade((int) $r->total), $r->note])->all());

        $rat = DB::table('work_order_ratings as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->join('work_orders as w', 'w.id', '=', 'r.work_order_id')
            ->whereIn('r.rated_by', $ids)->orderByDesc('r.updated_at');
        $blocks[] = $this->table('Penilaian Anggota yang diberikan Owner'.$this->shown($this->total($rat)), ['No. Perintah', 'Anggota', 'Nilai Akhir', 'Predikat', 'Catatan'],
            $rat->limit(self::LIST_LIMIT)->get(['w.number', 'e.full_name', 'r.total', 'r.note'])
                ->map(fn ($r) => [$r->number, $r->full_name, $r->total, \App\Models\Assessment::grade((int) $r->total), $r->note])->all());

        $rev = DB::table('daily_reports as d')->join('employees as e', 'e.id', '=', 'd.employee_id')->whereIn('d.reviewed_by', $ids)->orderByDesc('d.reviewed_at');
        $blocks[] = $this->table('Review Laporan Harian oleh Owner'.$this->shown($this->total($rev)), ['Tanggal Laporan', 'Karyawan', 'Area', 'Hasil Review', 'Catatan'],
            $rev->limit(self::LIST_LIMIT)->get(['d.report_date', 'e.full_name', 'd.area', 'd.review_status', 'd.review_note'])
                ->map(fn ($r) => [$this->d($r->report_date), $r->full_name, $r->area, $r->review_status, $r->review_note])->all());

        $blocks[] = $this->activityTable('Log Aktivitas Owner', $ids);

        return ['title' => '1. DATA OWNER (SUPER ADMIN)', 'blocks' => $blocks];
    }

    // ---------------------------------------------------------------- Role tambahan

    private function roles(): array
    {
        $roles = Role::whereNotIn('name', self::BASE_ROLES)->with('permissions:id,name')->orderBy('name')->get();
        $blocks = [];

        if ($roles->isEmpty()) {
            $blocks[] = ['type' => 'text', 'text' => 'Belum ada role tambahan. Role dibuat Owner di menu Role & Hak Akses.'];
        }

        foreach ($roles as $role) {
            $users = User::role($role->name)->with(['employee.position', 'employee.department'])->orderBy('name')->get();
            $access = PermissionCatalog::summarize($role->permissions->pluck('name')->all());

            $blocks[] = ['type' => 'heading', 'text' => 'Role: '.$role->name];
            $blocks[] = $this->table('Hak Akses', ['Modul', 'Izin'], array_map(fn ($a) => [$a['group'], $a['levels']], $access) ?: [['(tidak ada hak akses khusus)', '']]);
            $blocks[] = $this->table("Pengguna dengan role {$role->name} (".$users->count().')', ['Nama', 'Email', 'Status', 'NIP', 'Jabatan', 'Departemen', 'Tim', 'Dibuat'],
                $users->map(fn (User $u) => [
                    $u->name, $u->email, $u->status, $u->employee?->nip, $u->employee?->position?->title,
                    $u->employee?->department?->name, $u->employee?->team, $this->dt($u->created_at),
                ])->all());
            $blocks[] = $this->activityTable("Log Aktivitas pengguna role {$role->name}", $users->pluck('id')->all(), 200);
        }

        return ['title' => '2. DATA ROLE TAMBAHAN (HAK AKSES DARI OWNER)', 'blocks' => $blocks];
    }

    // ---------------------------------------------------------------- Karyawan

    private function employees(): array
    {
        $blocks = [];

        $byStatus = Employee::withTrashed()->selectRaw('employment_status as s, count(*) as n')->groupBy('employment_status')->pluck('n', 's');
        $byTeam = Employee::withTrashed()->where('employment_status', 'active')->selectRaw("coalesce(team, 'Tanpa tim') as t, count(*) as n")->groupByRaw("coalesce(team, 'Tanpa tim')")->pluck('n', 't');
        $blocks[] = $this->table('Ringkasan', ['Keterangan', 'Jumlah'], array_merge(
            [['Total karyawan (termasuk diarsipkan)', Employee::withTrashed()->count()]],
            collect(Employee::EMPLOYMENT_STATUS_LABELS)->map(fn ($label, $k) => ["Status {$label}", (int) ($byStatus[$k] ?? 0)])->values()->all(),
            $byTeam->map(fn ($n, $t) => ["Aktif - Tim {$t}", (int) $n])->values()->all(),
        ));

        $blocks[] = $this->employeeTable('Daftar Seluruh Karyawan', null);

        return ['title' => '3. DATA KARYAWAN', 'blocks' => $blocks];
    }

    /** Tabel data karyawan lengkap (tanpa data sensitif). $ids null = semua. */
    private function employeeTable(string $title, ?array $ids): array
    {
        $rolesByUser = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)->get(['model_has_roles.model_id as uid', 'roles.name'])
            ->groupBy('uid')->map(fn ($g) => $g->pluck('name')->implode(', '));

        $q = DB::table('employees as e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('positions as p', 'p.id', '=', 'e.position_id')
            ->leftJoin('employees as s', 's.id', '=', 'e.supervisor_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->when($ids !== null, fn ($q) => $q->whereIn('e.id', $ids ?: [0]))
            ->orderBy('e.full_name');

        $rows = $q->get([
            'e.nip', 'e.full_name', 'e.gender', 'e.place_of_birth', 'e.date_of_birth', 'p.title as position', 'd.name as department', 'e.team',
            'e.employment_type', 'e.employment_status', 'e.join_date', 'e.contract_end_date', 'e.phone', 'e.personal_email',
            's.full_name as supervisor', 'u.email as login_email', 'u.id as uid', 'e.deleted_at',
        ])->map(fn ($r) => [
            $r->nip, $r->full_name.($r->deleted_at ? ' (diarsipkan)' : ''), $r->gender, trim(($r->place_of_birth ?? '').', '.$this->d($r->date_of_birth), ', '),
            $r->position, $r->department, $r->team, Employee::EMPLOYMENT_TYPE_LABELS[$r->employment_type] ?? $r->employment_type,
            Employee::EMPLOYMENT_STATUS_LABELS[$r->employment_status] ?? $r->employment_status, $this->d($r->join_date), $this->d($r->contract_end_date),
            $r->phone, $r->personal_email, $r->supervisor, $r->login_email, $r->uid ? ($rolesByUser[$r->uid] ?? '') : '',
        ])->all();

        return $this->table($title.' ('.count($rows).')', [
            'NIP', 'Nama', 'JK', 'Tempat, Tgl Lahir', 'Jabatan', 'Departemen', 'Tim', 'Tipe', 'Status', 'Tgl Masuk', 'Akhir Kontrak',
            'Telepon', 'Email Pribadi', 'Atasan', 'Email Login', 'Role',
        ], $rows);
    }

    // ---------------------------------------------------------------- util

    private function activityTable(string $title, array $userIds, int $limit = self::LOG_LIMIT): array
    {
        $q = ActivityLog::whereIn('user_id', $userIds ?: [0])->orderByDesc('created_at')->orderByDesc('id');
        $total = $this->total($q);

        return $this->table($title.$this->shown($total, $limit), ['Waktu', 'Aksi', 'Keterangan', 'IP'],
            $q->limit($limit)->get()->map(fn (ActivityLog $l) => [$this->dt($l->created_at), $l->action, $l->description, $l->ip_address])->all());
    }

    /** Hitung baris tanpa ORDER BY (PostgreSQL menolak ORDER BY pada count(*) tanpa GROUP BY). */
    private function total($query): int
    {
        return (clone $query)->reorder()->count();
    }

    private function table(string $title, array $headings, array $rows): array
    {
        return ['type' => 'table', 'title' => $title, 'headings' => $headings, 'rows' => $rows];
    }

    private function shown(int $total, int $limit = self::LIST_LIMIT): string
    {
        return $total > $limit ? " (menampilkan {$limit} terbaru dari {$total})" : " ({$total})";
    }

    private function d($v): ?string
    {
        return $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : null;
    }

    private function dt($v): ?string
    {
        return $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : null;
    }
}
