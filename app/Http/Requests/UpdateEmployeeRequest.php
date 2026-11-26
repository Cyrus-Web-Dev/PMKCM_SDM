<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employees.manage');
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')->id;

        return [
            'nip' => ['required', 'string', 'max:30', Rule::unique('employees', 'nip')->ignore($employeeId)],
            'full_name' => ['required', 'string', 'max:150'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'marital_status' => ['required', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'dependents_count' => ['nullable', 'integer', 'min:0', 'max:20'],

            'supervisor_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employeeId])],
            'team' => ['nullable', 'string', 'max:30'],

            'employment_type' => ['required', Rule::in(['tetap', 'kontrak', 'magang', 'harian'])],
            'join_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'resign_date' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(['active', 'resigned', 'terminated', 'retired'])],

            'photo' => ['nullable', 'image', 'max:2048'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Akun login (HANYA Owner): password awal diketik Owner, wajib diganti saat login pertama.
            'create_account' => ['nullable', 'boolean'],
            'account_email' => ['required_if:create_account,true', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'account_password' => ['required_if:create_account,true', 'nullable', 'string', 'min:6', 'max:100'],
            'account_role' => ['nullable', Rule::exists('roles', 'name')],
        ];
    }

    public function attributes(): array
    {
        return [
            'nip' => 'NIP',
            'full_name' => 'nama lengkap',
        ];
    }
}
