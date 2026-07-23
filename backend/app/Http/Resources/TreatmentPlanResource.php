<?php

namespace App\Http\Resources;

use App\Enums\TreatmentPlanItemStatus;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin TreatmentPlan */
class TreatmentPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'dentist_id' => $this->dentist_id,
            'created_by_id' => $this->created_by_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'presented_at' => $this->presented_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'superseded_by_plan_id' => $this->superseded_by_plan_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // Plan-level roll-up (design doc §6/§13): SUM over non-cancelled items, computed here
            // from the already-eager-loaded `items` relation rather than an extra query — never
            // stored/cached. Only present when items are loaded (index/show always load them).
            'estimated_cost' => $this->whenLoaded('items', fn () => $this->sumEstimatedCost($this->items)),
            'dentist' => $this->whenLoaded('dentist', fn () => [
                'id' => $this->dentist->id,
                'name' => $this->dentist->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'superseded_by_plan' => $this->whenLoaded('supersededByPlan', fn () => $this->supersededByPlan ? [
                'id' => $this->supersededByPlan->id,
                'title' => $this->supersededByPlan->title,
                'status' => $this->supersededByPlan->status->value,
            ] : null),
            'items' => TreatmentPlanItemResource::collection($this->whenLoaded('items')),
        ];
    }

    /**
     * @param  Collection<int, TreatmentPlanItem>  $items
     */
    private function sumEstimatedCost(Collection $items): string
    {
        return $items
            ->filter(fn (TreatmentPlanItem $item) => $item->status !== TreatmentPlanItemStatus::Cancelled)
            ->reduce(fn (string $carry, TreatmentPlanItem $item) => bcadd($carry, $item->estimated_cost, 2), '0.00');
    }
}
