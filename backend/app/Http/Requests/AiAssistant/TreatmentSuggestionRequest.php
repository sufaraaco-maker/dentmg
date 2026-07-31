<?php

namespace App\Http\Requests\AiAssistant;

use App\Models\TreatmentPlanItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors `TreatmentPlanItemPolicy::create()` exactly (design doc §7) — receptionist cannot add
 * treatment plan items today, unchanged here. Whether the plan is actually still a draft is
 * checked in `AiAssistantService::suggestTreatmentItems()`.
 */
class TreatmentSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TreatmentPlanItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
