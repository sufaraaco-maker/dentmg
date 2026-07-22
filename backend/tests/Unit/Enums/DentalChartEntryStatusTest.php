<?php

namespace Tests\Unit\Enums;

use App\Enums\DentalChartEntryStatus;
use Tests\TestCase;

class DentalChartEntryStatusTest extends TestCase
{
    public function test_active_can_only_transition_to_planned(): void
    {
        $this->assertSame([DentalChartEntryStatus::Planned], DentalChartEntryStatus::transitionsFrom(DentalChartEntryStatus::Active));
        $this->assertTrue(DentalChartEntryStatus::Active->canTransitionTo(DentalChartEntryStatus::Planned));
        $this->assertFalse(DentalChartEntryStatus::Active->canTransitionTo(DentalChartEntryStatus::Completed));
    }

    public function test_planned_can_transition_to_completed_or_cancelled_only(): void
    {
        $this->assertTrue(DentalChartEntryStatus::Planned->canTransitionTo(DentalChartEntryStatus::Completed));
        $this->assertTrue(DentalChartEntryStatus::Planned->canTransitionTo(DentalChartEntryStatus::Cancelled));
        $this->assertFalse(DentalChartEntryStatus::Planned->canTransitionTo(DentalChartEntryStatus::Active));
        $this->assertFalse(DentalChartEntryStatus::Planned->canTransitionTo(DentalChartEntryStatus::Existing));
    }

    public function test_existing_completed_and_cancelled_are_terminal(): void
    {
        // No automatic supersession for V1 (design draft resolved decision #7) — an active finding
        // and the completed procedure that later addresses it both remain independent, permanent
        // records, so none of these three statuses has any outbound transition.
        foreach ([DentalChartEntryStatus::Existing, DentalChartEntryStatus::Completed, DentalChartEntryStatus::Cancelled] as $terminal) {
            $this->assertSame([], DentalChartEntryStatus::transitionsFrom($terminal));
        }
    }
}
