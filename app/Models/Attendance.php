<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out',
        'check_in_lat', 'check_in_lng', 'check_out_lat', 'check_out_lng',
        'status', 'late_minutes', 'notes',
        'check_in_photo', 'check_out_photo', 'shift_id', 'shift_name', 'is_offline', 'device_time',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_offline' => 'boolean',
            'device_time' => 'datetime',
        ];
    }

    public const STATUS_LABELS = [
        'hadir' => 'Hadir',
        'terlambat' => 'Terlambat',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'alpha' => 'Alfa',
        'cuti' => 'Cuti',
        'wfh' => 'WFH',
        'libur' => 'Libur',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
