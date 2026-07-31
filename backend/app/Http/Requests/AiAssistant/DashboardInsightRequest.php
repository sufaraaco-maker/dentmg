<?php

namespace App\Http\Requests\AiAssistant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Every role may ask a Dashboard Insight question (design doc §7) — which report tools are
 * actually exposed to the model is filtered per-role inside `AiAssistantService::reportTools()`,
 * re-checking the existing `view-financial-reports`/`view-operational-reports` Gates, so a
 * receptionist's question literally cannot surface Production/Collections/A-R Aging as an
 * available tool.
 */
class DashboardInsightRequest extends FormRequest
{
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
            'question' => ['required', 'string', 'max:1000'],
        ];
    }
}
