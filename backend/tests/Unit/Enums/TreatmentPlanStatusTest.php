<?php

namespace Tests\Unit\Enums;

use App\Enums\TreatmentPlanStatus;
use Tests\TestCase;

class TreatmentPlanStatusTest extends TestCase
{
    public function test_draft_can_transition_to_presented_or_cancelled_only(): void
    {
        $this->assertSame(
            [TreatmentPlanStatus::Presented, TreatmentPlanStatus::Cancelled],
            TreatmentPlanStatus::transitionsFrom(TreatmentPlanStatus::Draft)
        );
        $this->assertTrue(TreatmentPlanStatus::Draft->canTransitionTo(TreatmentPlanStatus::Presented));
        $this->assertFalse(TreatmentPlanStatus::Draft->canTransitionTo(TreatmentPlanStatus::Accepted));
    }

    public function test_presented_can_transition_to_accepted_rejected_or_cancelled_only(): void
    {
        $this->assertTrue(TreatmentPlanStatus::Presented->canTransitionTo(TreatmentPlanStatus::Accepted));
        $this->assertTrue(TreatmentPlanStatus::Presented->canTransitionTo(TreatmentPlanStatus::Rejected));
        $this->assertTrue(TreatmentPlanStatus::Presented->canTransitionTo(TreatmentPlanStatus::Cancelled));
        $this->assertFalse(TreatmentPlanStatus::Presented->canTransitionTo(TreatmentPlanStatus::Draft));
        $this->assertFalse(TreatmentPlanStatus::Presented->canTransitionTo(TreatmentPlanStatus::InProgress));
    }

    public function test_accepted_can_transition_to_in_progress_or_cancelled_only(): void
    {
        $this->assertTrue(TreatmentPlanStatus::Accepted->canTransitionTo(TreatmentPlanStatus::InProgress));
        $this->assertTrue(TreatmentPlanStatus::Accepted->canTransitionTo(TreatmentPlanStatus::Cancelled));
        $this->assertFalse(TreatmentPlanStatus::Accepted->canTransitionTo(TreatmentPlanStatus::Completed));
    }

    public function test_in_progress_can_transition_to_completed_or_cancelled_only(): void
    {
        $this->assertTrue(TreatmentPlanStatus::InProgress->canTransitionTo(TreatmentPlanStatus::Completed));
        $this->assertTrue(TreatmentPlanStatus::InProgress->canTransitionTo(TreatmentPlanStatus::Cancelled));
        $this->assertFalse(TreatmentPlanStatus::InProgress->canTransitionTo(TreatmentPlanStatus::Accepted));
    }

    public function test_completed_rejected_and_cancelled_are_terminal(): void
    {
        foreach ([TreatmentPlanStatus::Completed, TreatmentPlanStatus::Rejected, TreatmentPlanStatus::Cancelled] as $terminal) {
            $this->assertSame([], TreatmentPlanStatus::transitionsFrom($terminal));
            $this->assertTrue($terminal->isTerminal());
        }
    }

    public function test_draft_presented_accepted_and_in_progress_are_non_terminal(): void
    {
        foreach ([TreatmentPlanStatus::Draft, TreatmentPlanStatus::Presented, TreatmentPlanStatus::Accepted, TreatmentPlanStatus::InProgress] as $nonTerminal) {
            $this->assertFalse($nonTerminal->isTerminal());
        }

        $this->assertSame(
            [TreatmentPlanStatus::Draft, TreatmentPlanStatus::Presented, TreatmentPlanStatus::Accepted, TreatmentPlanStatus::InProgress],
            TreatmentPlanStatus::nonTerminalStatuses()
        );
    }
}
