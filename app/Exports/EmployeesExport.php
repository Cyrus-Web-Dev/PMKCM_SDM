<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Employee::orderBy('full_name')->get();
    }

    public function headings(): array
    {
        return [
            'NIP', 'Nama Lengkap', 'Jenis Kelamin',
            'Tim', 'Tipe Kepegawaian', 'Tanggal Bergabung', 'Status', 'No. HP', 'Email Pribadi',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->nip,
            $employee->full_name,
            $employee->gender === 'L' ? 'Laki-laki' : 'Perempuan',
            $employee->team,
            $employee->employment_type_label,
            $employee->join_date?->format('d/m/Y'),
            $employee->employment_status_label,
            $employee->phone,
            $employee->personal_email,
        ];
    }
}
