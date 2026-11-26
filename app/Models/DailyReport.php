<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    public const STATUSES = ['selesai' => 'Selesai', 'proses' => 'Proses', 'kendala' => 'Kendala'];

    public const REVIEW_STATUSES = ['menunggu' => 'Menunggu Review', 'disetujui' => 'Disetujui', 'perlu_revisi' => 'Perlu Revisi'];

    protected $fillable = [
        'employee_id', 'report_date', 'team', 'area',
        'start_time', 'end_time', 'task', 'issue', 'status',
        'review_status', 'review_note', 'reviewed_by', 'reviewed_at', 'work_order_id',
    ];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DailyReportPhoto::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DailyReportComment::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }


    /** Durasi kerja dalam jam (1 desimal). */
    public function getHoursAttribute(): float
    {
        [$sh, $sm] = array_map('intval', explode(':', $this->start_time));
        [$eh, $em] = array_map('intval', explode(':', $this->end_time));
        $minutes = ($eh * 60 + $em) - ($sh * 60 + $sm);
        if ($minutes <= 0) {
            $minutes += 1440;
        }

        return round($minutes / 60, 1);
    }

    /** Bentuk array siap kirim ke halaman Vue. */
    public function toRow(bool $withEmployee = false): array
    {
        $row = [
            'id' => $this->id,
            'date' => $this->report_date->toDateString(),
            'team' => $this->team,
            'area' => $this->area,
            'start' => substr($this->start_time, 0, 5),
            'end' => substr($this->end_time, 0, 5),
            'hours' => $this->hours,
            'task' => $this->task,
            'issue' => $this->issue,
            'status' => $this->status,
            'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'review_status' => $this->review_status,
            'review_label' => self::REVIEW_STATUSES[$this->review_status] ?? $this->review_status,
            'work_order' => $this->relationLoaded('workOrder') ? $this->workOrder?->number : null,
            'photos_count' => $this->photos_count ?? null,
            'comments_count' => $this->comments_count ?? null,
        ];

        if ($withEmployee) {
            $row['employee'] = $this->employee?->full_name;
            $row['nip'] = $this->employee?->nip;
        }

        return $row;
    }
}
