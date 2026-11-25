<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penilaian Anggota: nilai kepala kerja untuk satu anggota pada satu perintah kerja (kriteria = Assessment::CRITERIA). */
class WorkOrderRating extends Model
{
    protected $fillable = ['work_order_id', 'employee_id', 'quality', 'discipline', 'teamwork', 'initiative', 'safety', 'total', 'note', 'rated_by'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => (int) $this->employee_id,
            'scores' => collect(Assessment::CRITERIA)->mapWithKeys(fn ($label, $key) => [$key => (int) $this->{$key}])->all(),
            'total' => (int) $this->total,
            'grade' => Assessment::grade($this->total),
            'note' => $this->note,
        ];
    }
}
