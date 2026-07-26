<?php

namespace App\Http\Requests\StockMovement;

use App\Enums\StockMovementReason;
use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockMovement::class);
    }

    /**
     * `reason` excludes `received` (design doc §2/§8) — that reason is exclusively produced by
     * PurchaseOrderService::receive(), never a manual entry. `quantity_delta` is a signed integer:
     * negative for used/wasted/expired, positive for initial_stock, either sign for correction
     * (design doc §4/§8) — the frontend negates before sending where the direction is implied by
     * the chosen reason.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(array_map(fn ($r) => $r->value, StockMovementReason::manuallySelectable()))],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'expiration_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
