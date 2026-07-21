<?php

namespace App\Http\Requests\DentalCondition;

use App\Enums\DentalConditionCategory;
use App\Models\DentalCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDentalConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DentalCondition::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::enum(DentalConditionCategory::class)],
            'default_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'applies_to_surface' => ['required', 'boolean'],
            'icon_key' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
