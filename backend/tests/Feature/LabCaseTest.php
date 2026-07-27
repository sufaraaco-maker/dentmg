<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabCaseTest extends TestCase
{
    use RefreshDatabase;

    // ---- index / store --------------------------------------------------------------------------

    public function test_guest_cannot_list_lab_cases(): void
    {
        $response = $this->getJson('/api/lab-cases');

        $response->assertUnauthorized();
    }

    public function test_dentist_can_create_a_lab_case(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $lab = Lab::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/lab-cases', [
            'patient_id' => $patient->id,
            'lab_id' => $lab->id,
            'tooth_numbers' => ['16', '17'],
            'shade' => 'A2',
            'case_type' => 'Bridge',
        ]);

        $response->assertCreated();
        $this->assertSame('draft', $response->json('status'));
        $this->assertNotNull($response->json('case_number'));
        $this->assertSame(['16', '17'], $response->json('tooth_numbers'));
    }

    public function test_admin_can_create_a_lab_case(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $lab = Lab::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/lab-cases', [
            'patient_id' => $patient->id,
            'lab_id' => $lab->id,
        ]);

        $response->assertCreated();
    }

    public function test_receptionist_cannot_create_a_lab_case(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();
        $lab = Lab::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/lab-cases', [
            'patient_id' => $patient->id,
            'lab_id' => $lab->id,
        ]);

        $response->assertForbidden();
    }

    public function test_creating_a_lab_case_rejects_an_invalid_tooth_code(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $lab = Lab::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/lab-cases', [
            'patient_id' => $patient->id,
            'lab_id' => $lab->id,
            'tooth_numbers' => ['99'],
        ]);

        $response->assertStatus(422);
    }

    public function test_receptionist_can_view_a_lab_case(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/lab-cases/{$case->id}");

        $response->assertOk();
    }

    // ---- update ----------------------------------------------------------------------------------

    public function test_dentist_can_update_a_draft_case(): void
    {
        $actor = User::factory()->dentist()->create();
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/lab-cases/{$case->id}", [
            'shade' => 'B1',
        ]);

        $response->assertOk();
        $this->assertSame('B1', $response->json('shade'));
    }

    public function test_receptionist_cannot_update_a_lab_case(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/lab-cases/{$case->id}", ['shade' => 'B1']);

        $response->assertForbidden();
    }

    public function test_updating_a_sent_case_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $case = LabCase::factory()->sent()->create();

        $response = $this->actingAs($actor)->putJson("/api/lab-cases/{$case->id}", ['shade' => 'B1']);

        $response->assertStatus(422)->assertJson(['code' => 'invalid_lab_case_operation']);
    }

    // ---- send ------------------------------------------------------------------------------------

    public function test_receptionist_can_send_a_draft_case(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $lab = Lab::factory()->create(['default_turnaround_days' => 5]);
        $case = LabCase::factory()->create(['lab_id' => $lab->id]);

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/send");

        $response->assertOk();
        $this->assertSame('sent', $response->json('status'));
        $this->assertNotNull($response->json('sent_at'));
        $this->assertNotNull($response->json('due_at'));
    }

    public function test_send_does_not_override_a_manually_set_due_date(): void
    {
        $actor = User::factory()->admin()->create();
        $lab = Lab::factory()->create(['default_turnaround_days' => 5]);
        $case = LabCase::factory()->create(['lab_id' => $lab->id]);
        $this->actingAs($actor)->putJson("/api/lab-cases/{$case->id}", ['due_at' => '2027-01-15']);

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/send");

        $response->assertOk();
        $this->assertStringStartsWith('2027-01-15', $response->json('due_at'));
    }

    public function test_dentist_cannot_send_a_lab_case(): void
    {
        $actor = User::factory()->dentist()->create();
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/send");

        $response->assertForbidden();
    }

    public function test_sending_an_already_sent_case_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $case = LabCase::factory()->sent()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/send");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_lab_case_operation']);
    }

    // ---- receive / quality-check ------------------------------------------------------------------

    public function test_receptionist_can_receive_a_sent_case(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $case = LabCase::factory()->sent()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/receive");

        $response->assertOk();
        $this->assertSame('received', $response->json('status'));
        $this->assertNotNull($response->json('received_at'));
    }

    public function test_dentist_cannot_receive_a_lab_case(): void
    {
        $actor = User::factory()->dentist()->create();
        $case = LabCase::factory()->sent()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/receive");

        $response->assertForbidden();
    }

    public function test_receiving_a_draft_case_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/receive");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_lab_case_operation']);
    }

    public function test_admin_can_quality_check_a_received_case(): void
    {
        $actor = User::factory()->admin()->create();
        $case = LabCase::factory()->received()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/quality-check");

        $response->assertOk();
        $this->assertSame('quality_checked', $response->json('status'));
    }

    public function test_quality_checking_a_sent_case_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $case = LabCase::factory()->sent()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/quality-check");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_lab_case_operation']);
    }

    // ---- cancel ----------------------------------------------------------------------------------

    public function test_dentist_can_cancel_a_draft_case(): void
    {
        $actor = User::factory()->dentist()->create();
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/cancel");

        $response->assertOk();
        $this->assertSame('cancelled', $response->json('status'));
    }

    public function test_receptionist_cannot_cancel_a_lab_case(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/cancel");

        $response->assertForbidden();
    }

    public function test_cancel_rejected_once_a_case_has_been_received(): void
    {
        $actor = User::factory()->admin()->create();
        $case = LabCase::factory()->received()->create();

        $response = $this->actingAs($actor)->postJson("/api/lab-cases/{$case->id}/cancel");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_lab_case_operation']);
    }

    // ---- due/overdue widget ------------------------------------------------------------------------

    public function test_due_endpoint_returns_only_sent_cases_due_today_or_overdue(): void
    {
        $actor = User::factory()->admin()->create();
        $overdue = LabCase::factory()->sent()->create(['due_at' => now()->subDay()]);
        LabCase::factory()->sent()->create(['due_at' => now()->addWeek()]);
        LabCase::factory()->create();

        $response = $this->actingAs($actor)->getJson('/api/lab-cases/due');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($overdue->id, $ids);
        $this->assertCount(1, $ids);
    }

    // ---- destroy ---------------------------------------------------------------------------------

    public function test_admin_can_delete_a_draft_case(): void
    {
        $actor = User::factory()->admin()->create();
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/lab-cases/{$case->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('lab_cases', ['id' => $case->id]);
    }

    public function test_dentist_cannot_delete_a_lab_case(): void
    {
        $actor = User::factory()->dentist()->create();
        $case = LabCase::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/lab-cases/{$case->id}");

        $response->assertForbidden();
    }
}
