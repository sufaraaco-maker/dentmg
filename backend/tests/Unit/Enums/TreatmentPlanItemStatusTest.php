<?php

namespace Tests\Unit\Enums;

use App\Enums\TreatmentPlanItemStatus;
use Tests\TestCase;

class TreatmentPlanItemStatusTest extends TestCase
{
    public function test_planned_can_transition_to_completed_or_cancelled_only(): void
    {
        $this->assertSame(
            [TreatmentPlanItemStatus::Completed, TreatmentPlanItemStatus::Cancelled],
            TreatmentPlanItemStatus::transitionsFrom(TreatmentPlanItemStatus::Planned)
        );
        $this->assertTrue(TreatmentPlanItemStatus::Planned->canTransitionTo(TreatmentPlanItemStatus::Completed));
        $this->assertTrue(TreatmentPlanItemStatus::Planned->canTransitionTo(TreatmentPlanItemStatus::Cancelled));
    }

    public function test_completed_and_cancelled_are_terminal(): void
    {
        foreach ([TreatmentPlanItemStatus::Completed, TreatmentPlanItemStatus::Cancelled] as $terminal) {
            $this->assertSame([], TreatmentPlanItemStatus::transitionsFrom($terminal));
            $this->assertTrue($terminal->isTerminal());
        }
    }

    public function test_planned_is_not_terminal(): void
    {
        $this->assertFalse(TreatmentPlanItemStatus::Planned->isTerminal());
    }
}
