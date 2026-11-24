<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penilaian outsourcing bulanan (5 kriteria, nilai 0-100). */
class Assessment extends Model
{
    public const CRITERIA = [
        'quality' => 'Kualitas Kerja',
        'discipline' => 'Kedisiplinan',
        'teamwork' => 'Kerja Sama',
        'initiative' => 'Inisiatif',
        'safety' => 'Kepatuhan K3',
    ];

    public const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    protected $fillable = [
        'employee_id', 'period', 'quality', 'discipline', 'teamwork', 'initiative', 'safety',
        'total', 'note', 'assessed_by',
    ];

    protected function casts(): array
    {
        return ['period' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public static function grade(int|float|null $n): string
    {
        if ($n === null) {
            return '-';
        }

        return match (true) {
            $n >= 90 => 'A · Sangat Baik',
            $n >= 80 => 'B · Baik',
            $n >= 70 => 'C · Cukup',
            default => 'D · Perlu Pembinaan',
        };
    }

    public function periodLabel(): string
    {
        return self::MONTHS[(int) $this->period->format('n') - 1].' '.$this->period->format('Y');
    }

    /** Bentuk array siap kirim ke halaman Vue. */
    public function toRow(bool $withEmployee = false): array
    {
        $row = [
            'id' => $this->id,
            'period' => $this->period->toDateString(),
            'period_label' => $this->periodLabel(),
            'scores' => collect(self::CRITERIA)->mapWithKeys(fn ($label, $key) => [$key => (int) $this->{$key}])->all(),
            'total' => (int) $this->total,
            'grade' => self::grade($this->total),
            'note' => $this->note,
        ];

        if ($withEmployee) {
            $row['employee_id'] = $this->employee_id;
            $row['employee'] = $this->employee?->full_name;
            $row['team'] = $this->employee?->team;
        }

        return $row;
    }
}
