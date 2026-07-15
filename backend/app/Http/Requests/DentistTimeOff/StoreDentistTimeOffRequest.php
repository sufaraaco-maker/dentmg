<?php

namespace App\Http\Requests\DentistTimeOff;

use App\Models\DentistTimeOff;
use Illuminate\Foundation\Http\FormRequest;

class StoreDentistTimeOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [DentistTimeOff::class, $this->route('user')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
