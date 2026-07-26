<?php

namespace Tests\Unit\Enums;

use App\Enums\ClinicalNoteStatus;
use Tests\TestCase;

class ClinicalNoteStatusTest extends TestCase
{
    public function test_draft_can_transition_to_signed_only(): void
    {
        $this->assertSame(
            [ClinicalNoteStatus::Signed],
            ClinicalNoteStatus::transitionsFrom(ClinicalNoteStatus::Draft)
        );
        $this->assertTrue(ClinicalNoteStatus::Draft->canTransitionTo(ClinicalNoteStatus::Signed));
    }

    public function test_signed_is_terminal(): void
    {
        $this->assertSame([], ClinicalNoteStatus::transitionsFrom(ClinicalNoteStatus::Signed));
        $this->assertFalse(ClinicalNoteStatus::Signed->canTransitionTo(ClinicalNoteStatus::Draft));
    }
}
