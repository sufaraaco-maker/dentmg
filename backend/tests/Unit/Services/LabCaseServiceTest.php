<?php

namespace Tests\Unit\Services;

use App\Enums\LabCaseStatus;
use App\Exceptions\Laboratory\InvalidLabCaseOperationException;
use App\Models\Lab;
use App\Models\LabCase;
use App\Models\Patient;
use App\Services\LabCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laboratory design doc §5/§8: written and CI-verified as part of this module's own
 * implementation sequence, not deferred — same discipline every prior module established.
 */
class LabCaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private LabCaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LabCaseService;
    }

    // --- create / numbering --------------------------------------------------------------------

    public function test_create_generates_a_sequential_case_number(): void
    {
        $patient = Patient::factory()->create();
        $lab = Lab::factory()->create();

        $first = $this->service->create(['patient_id' => $patient->id, 'lab_id' => $lab->id]);
        $second = $this->service->create(['patient_id' => $patient->id, 'lab_id' => $lab->id]);

        $this->assertSame($first->sequence_number + 1, $second->sequence_number);
        $this->assertNotSame($first->case_number, $second->case_number);
        $this->assertSame(LabCaseStatus::Draft, $first->status);
        $this->assertNull($first->sent_at);
    }

    // --- update ----------------------------------------------------------------------------------

    public function test_update_rejected_once_case_is_no_longer_draft(): void
    {
        $case = LabCase::factory()->sent()->create();

        $this->expectException(InvalidLabCaseOperationException::class);

        $this->service->update($case, ['shade' => 'B1']);
    }

    public function test_update_never_touches_patient_id(): void
    {
        $case = LabCase::factory()->create();
        $originalPatientId = $case->patient_id;
        $otherPatient = Patient::factory()->create();

        $updated = $this->service->update($case, ['patient_id' => $otherPatient->id, 'shade' => 'C1']);

        $this->assertSame($originalPatientId, $updated->patient_id);
        $this->assertSame('C1', $updated->shade);
    }

    // --- send ------------------------------------------------------------------------------------

    public function test_send_auto_calculates_due_date_from_lab_turnaround(): void
    {
        $lab = Lab::factory()->create(['default_turnaround_days' => 10]);
        $case = LabCase::factory()->create(['lab_id' => $lab->id, 'due_at' => null]);

        $sent = $this->service->send($case);

        $this->assertSame(LabCaseStatus::Sent, $sent->status);
        $this->assertNotNull($sent->sent_at);
        $this->assertNotNull($sent->due_at);
        $this->assertEqualsWithDelta(
            now()->addDays(10)->timestamp,
            $sent->due_at->timestamp,
            5
        );
    }

    public function test_send_preserves_a_manually_set_due_date(): void
    {
        $lab = Lab::factory()->create(['default_turnaround_days' => 10]);
        $manualDueDate = now()->addDays(2);
        $case = LabCase::factory()->create(['lab_id' => $lab->id, 'due_at' => $manualDueDate]);

        $sent = $this->service->send($case);

        $this->assertSame($manualDueDate->timestamp, $sent->due_at->timestamp);
    }

    public function test_send_rejected_when_case_is_not_draft(): void
    {
        $case = LabCase::factory()->sent()->create();

        $this->expectException(InvalidLabCaseOperationException::class);

        $this->service->send($case);
    }

    // --- receive / quality-check ------------------------------------------------------------------

    public function test_receive_rejected_when_case_is_still_draft(): void
    {
        $case = LabCase::factory()->create();

        $this->expectException(InvalidLabCaseOperationException::class);

        $this->service->receive($case);
    }

    public function test_quality_check_rejected_when_case_is_not_yet_received(): void
    {
        $case = LabCase::factory()->sent()->create();

        $this->expectException(InvalidLabCaseOperationException::class);

        $this->service->qualityCheck($case);
    }

    // --- cancel ----------------------------------------------------------------------------------

    public function test_cancel_from_draft_succeeds(): void
    {
        $case = LabCase::factory()->create();

        $cancelled = $this->service->cancel($case);

        $this->assertSame(LabCaseStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cancel_from_sent_succeeds(): void
    {
        $case = LabCase::factory()->sent()->create();

        $cancelled = $this->service->cancel($case);

        $this->assertSame(LabCaseStatus::Cancelled, $cancelled->status);
    }

    public function test_cancel_rejected_once_received(): void
    {
        $case = LabCase::factory()->received()->create();

        $this->expectException(InvalidLabCaseOperationException::class);

        $this->service->cancel($case);
    }

    // --- delete ----------------------------------------------------------------------------------

    public function test_delete_rejected_once_case_is_no_longer_draft(): void
    {
        $case = LabCase::factory()->sent()->create();

        $this->expectException(InvalidLabCaseOperationException::class);

        $this->service->delete($case);
    }

    public function test_delete_soft_deletes_a_draft_case(): void
    {
        $case = LabCase::factory()->create();

        $this->service->delete($case);

        $this->assertSoftDeleted('lab_cases', ['id' => $case->id]);
    }
}
