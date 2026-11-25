<?php

namespace App\Models;

use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Perintah Kerja Harian. Status "selesai" = tampil sebagai History Card. */
class WorkOrder extends Model
{
    public const STATUSES = ['direncanakan' => 'Direncanakan', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    /** Perpindahan status yang boleh dilakukan kepala kerja. */
    public const TRANSITIONS = ['direncanakan' => ['berjalan', 'dibatalkan'], 'berjalan' => ['selesai', 'dibatalkan']];

    /** Membuka kembali (hanya pemegang work_orders.manage). */
    public const REOPEN = ['selesai' => ['berjalan'], 'dibatalkan' => ['direncanakan']];

    protected $fillable = ['number', 'order_date', 'start_date', 'end_date', 'leader_id', 'description', 'status', 'team', 'area', 'created_by'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'start_date' => 'date', 'end_date' => 'date'];
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'work_order_members');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(WorkOrderRating::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function involves(int $employeeId): bool
    {
        return (int) $this->leader_id === $employeeId || $this->members()->where('employees.id', $employeeId)->exists();
    }

    /** Perintah kerja yang melibatkan karyawan tsb (sebagai kepala kerja atau anggota). */
    public function scopeInvolving(Builder $query, int $employeeId): Builder
    {
        return $query->where(function (Builder $w) use ($employeeId) {
            $w->where('leader_id', $employeeId)->orWhereHas('members', fn ($m) => $m->where('employees.id', $employeeId));
        });
    }

    /**
     * Yang boleh dilihat pengguna: (1) perintah yang melibatkan dirinya; (2) bila punya izin work_orders.view,
     * semua perintah (HR-wide / Ka. URS) atau yang kepala kerja/anggotanya ada di timnya (manajer).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $canView = $user->can('work_orders.view');
        if ($canView && TeamScope::isHrWide($user)) {
            return $query;
        }

        $own = $user->employee?->id;
        $scoped = $canView ? TeamScope::constrainEmployees(Employee::query(), $user)->pluck('id')->all() : [];

        return $query->where(function (Builder $w) use ($own, $scoped) {
            $w->whereRaw('1 = 0');
            if ($own) {
                $w->orWhere('leader_id', $own)->orWhereHas('members', fn ($m) => $m->where('employees.id', $own));
            }
            if ($scoped) {
                $w->orWhereIn('leader_id', $scoped)->orWhereHas('members', fn ($m) => $m->whereIn('employees.id', $scoped));
            }
        });
    }

    /** Nomor berikutnya untuk tanggal perintah: PK-YYYYMMDD-001, 002, ... */
    public static function nextNumber(Carbon $date): string
    {
        $prefix = 'PK-'.$date->format('Ymd').'-';
        $last = static::where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /** Bentuk array siap kirim ke Vue (butuh relasi leader & members sudah dimuat). */
    public function toRow(): array
    {
        $days = $this->start_date && $this->end_date ? (int) $this->start_date->diffInDays($this->end_date, true) + 1 : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'order_date' => $this->order_date->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'duration_days' => $days,
            'leader_id' => (int) $this->leader_id,
            'leader' => $this->leader?->full_name,
            'members' => $this->members->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name])->values()->all(),
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'team' => $this->team,
            'area' => $this->area,
        ];
    }
}
