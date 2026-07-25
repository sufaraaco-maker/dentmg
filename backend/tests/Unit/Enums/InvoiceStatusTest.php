<?php

namespace Tests\Unit\Enums;

use App\Enums\InvoiceStatus;
use Tests\TestCase;

class InvoiceStatusTest extends TestCase
{
    public function test_draft_can_transition_to_issued_only(): void
    {
        $this->assertSame([InvoiceStatus::Issued], InvoiceStatus::transitionsFrom(InvoiceStatus::Draft));
        $this->assertTrue(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Issued));
        $this->assertFalse(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Void));
    }

    public function test_issued_can_transition_to_void_only(): void
    {
        $this->assertSame([InvoiceStatus::Void], InvoiceStatus::transitionsFrom(InvoiceStatus::Issued));
        $this->assertTrue(InvoiceStatus::Issued->canTransitionTo(InvoiceStatus::Void));
        $this->assertFalse(InvoiceStatus::Issued->canTransitionTo(InvoiceStatus::Draft));
    }

    public function test_void_is_terminal(): void
    {
        $this->assertSame([], InvoiceStatus::transitionsFrom(InvoiceStatus::Void));
        $this->assertTrue(InvoiceStatus::Void->isTerminal());
        $this->assertFalse(InvoiceStatus::Void->canTransitionTo(InvoiceStatus::Draft));
        $this->assertFalse(InvoiceStatus::Void->canTransitionTo(InvoiceStatus::Issued));
    }

    public function test_draft_and_issued_are_not_terminal(): void
    {
        $this->assertFalse(InvoiceStatus::Draft->isTerminal());
        $this->assertFalse(InvoiceStatus::Issued->isTerminal());
    }
}
