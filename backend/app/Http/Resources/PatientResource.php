<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Patient */
class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_code' => $this->patient_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'gender' => $this->gender->value,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'national_id' => $this->national_id,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'blood_type' => $this->blood_type?->value,
            'allergies' => $this->allergies,
            'medical_history' => $this->medical_history,
            'insurance_provider' => $this->insurance_provider,
            'insurance_number' => $this->insurance_number,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
