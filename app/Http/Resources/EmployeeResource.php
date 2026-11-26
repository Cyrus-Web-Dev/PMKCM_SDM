<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,
            'team' => $this->team,
            'employment_type' => $this->employment_type,
            'employment_type_label' => $this->employment_type_label,
            'employment_status' => $this->employment_status,
            'employment_status_label' => $this->employment_status_label,
            'join_date' => $this->join_date?->toDateString(),
            'photo_url' => $this->photo_url,
        ];
    }
}
