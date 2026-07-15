<?php

namespace App\Http\Requests\Appointment;

use App\Enums\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailableSlotsRequest extends FormRequest
{
    /**
     * Open to any authenticated role — reveals dentist schedule gaps only, not sensitive
     * (design doc §18).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dentist_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ];
    }
}
