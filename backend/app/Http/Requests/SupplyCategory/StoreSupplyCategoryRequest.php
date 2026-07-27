<?php

namespace App\Http\Requests\SupplyCategory;

use App\Models\SupplyCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplyCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SupplyCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:supply_categories,name'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
