<?php

namespace Tests\Feature;

use App\Enums\DentalChartEntryStatus;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentalChartEntryTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'dental_condition_id' => DentalCondition::factory()->create(['applies_to_surface' => false])->id,
            'dentist_id' => User::factory()->dentist()->create()->id,
            'tooth_number' => '16',
            'status' => 'active',
        ], $overrides);
    }

    // ---- index ---------------------------------------------------------------------------

    public function test_guest_cannot_list_dental_chart_entries(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/dental-chart-entries");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_a_patients_dental_chart_entries(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();
        $other = Patient::factory()->create();

        DentalChartEntry::factory()->count(2)->create(['patient_id' => $patient->id]);
        DentalChartEntry::factory()->create(['patient_id' => $other->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/dental-chart-entries");

        $response->assertOk();
        $this->assertCount(2, $response->json());
        $this->assertArrayHasKey('dental_condition', $response->json()[0]);
        $this->assertArrayHasKey('dentist', $response->json()[0]);
        $this->assertArrayHasKey('created_by', $response->json()[0]);
    }

    public function test_list_filters_by_status_tooth_number_and_category(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $procedure = DentalCondition::factory()->procedure()->create();

        DentalChartEntry::factory()->create([
            'patient_id' => $patient->id,
            'tooth_number' => '16',
            'status' => DentalChartEntryStatus::Planned,
            'dental_condition_id' => $procedure->id,
        ]);
        DentalChartEntry::factory()->create([
            'patient_id' => $patient->id,
            'tooth_number' => '11',
            'status' => DentalChartEntryStatus::Active,
        ]);

        $response = $this->actingAs($actor)->getJson(
            "/api/patients/{$patient->id}/dental-chart-entries?status=planned&tooth_number=16&category=procedure"
        );

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('16', $response->json()[0]['tooth_number']);
    }

    // ---- store ----------------------------------------------------------------------------

    public function test_guest_cannot_create_a_dental_chart_entry(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/dental-chart-entries", $this->validPayload());

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_a_dental_chart_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/dental-chart-entries",
            $this->validPayload()
        );

        $response->assertCreated()->assertJson([
            'patient_id' => $patient->id,
            'tooth_number' => '16',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('dental_chart_entries', [
            'id' => $response->json('id'),
            'patient_id' => $patient->id,
            'created_by_id' => $actor->id,
        ]);
    }

    public function test_dentist_can_create_a_dental_chart_entry(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/dental-chart-entries",
            $this->validPayload()
        );

        $response->assertCreated();
    }

    public function test_receptionist_cannot_create_a_dental_chart_entry(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/dental-chart-entries",
            $this->validPayload()
        );

        $response->assertForbidden();
    }

    public function test_create_dental_chart_entry_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/dental-chart-entries", []);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'dental_condition_id', 'dentist_id', 'tooth_number', 'status',
        ]);
    }

    public function test_create_rejects_an_invalid_fdi_tooth_code(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson(
            "/api/patients/{$patient->id}/dental-chart-entries",
            $this->validPayload(['tooth_number' => '91'])
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['tooth_number']);
    }

    // ---- update -----------------------------------------------------------------------------

    public function test_admin_can_update_a_dental_chart_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $entry = DentalChartEntry::factory()->create(['status' => DentalChartEntryStatus::Active, 'notes' => 'Old']);

        $response = $this->actingAs($actor)->putJson("/api/dental-chart-entries/{$entry->id}", [
            'notes' => 'New notes',
        ]);

        $response->assertOk()->assertJson(['notes' => 'New notes']);
        $this->assertDatabaseHas('dental_chart_entries', ['id' => $entry->id, 'updated_by_id' => $actor->id]);
    }

    public function test_receptionist_cannot_update_a_dental_chart_entry(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $entry = DentalChartEntry::factory()->create(['status' => DentalChartEntryStatus::Active]);

        $response = $this->actingAs($actor)->putJson("/api/dental-chart-entries/{$entry->id}", [
            'notes' => 'New notes',
        ]);

        $response->assertForbidden();
    }

    public function test_updating_a_terminal_entrys_locked_fields_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $entry = DentalChartEntry::factory()->completed()->create();

        $response = $this->actingAs($actor)->putJson("/api/dental-chart-entries/{$entry->id}", [
            'tooth_number' => '11',
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'dental_chart_entry_locked']);
    }

    public function test_notes_stay_editable_on_a_terminal_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $entry = DentalChartEntry::factory()->cancelled()->create();

        $response = $this->actingAs($actor)->putJson("/api/dental-chart-entries/{$entry->id}", [
            'notes' => 'Patient declined treatment',
        ]);

        $response->assertOk()->assertJson(['notes' => 'Patient declined treatment']);
    }

    // ---- complete / cancel -------------------------------------------------------------------

    public function test_admin_can_complete_a_planned_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $entry = DentalChartEntry::factory()->planned()->create();

        $response = $this->actingAs($actor)->postJson("/api/dental-chart-entries/{$entry->id}/complete");

        $response->assertOk()->assertJson(['status' => 'completed']);
        $this->assertNotNull($response->json('completed_at'));
    }

    public function test_completing_a_non_planned_entry_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $entry = DentalChartEntry::factory()->create(['status' => DentalChartEntryStatus::Active]);

        $response = $this->actingAs($actor)->postJson("/api/dental-chart-entries/{$entry->id}/complete");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_status_transition']);
    }

    public function test_receptionist_cannot_complete_an_entry(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $entry = DentalChartEntry::factory()->planned()->create();

        $response = $this->actingAs($actor)->postJson("/api/dental-chart-entries/{$entry->id}/complete");

        $response->assertForbidden();
    }

    public function test_dentist_can_cancel_a_planned_entry(): void
    {
        $actor = User::factory()->dentist()->create();
        $entry = DentalChartEntry::factory()->planned()->create();

        $response = $this->actingAs($actor)->postJson("/api/dental-chart-entries/{$entry->id}/cancel");

        $response->assertOk()->assertJson(['status' => 'cancelled']);
        $this->assertNotNull($response->json('cancelled_at'));
    }

    public function test_cancelling_an_already_terminal_entry_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $entry = DentalChartEntry::factory()->completed()->create();

        $response = $this->actingAs($actor)->postJson("/api/dental-chart-entries/{$entry->id}/cancel");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_status_transition']);
    }

    // ---- destroy ------------------------------------------------------------------------------

    public function test_admin_can_delete_a_dental_chart_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $entry = DentalChartEntry::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/dental-chart-entries/{$entry->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('dental_chart_entries', ['id' => $entry->id]);
    }

    public function test_dentist_cannot_delete_a_dental_chart_entry(): void
    {
        $actor = User::factory()->dentist()->create();
        $entry = DentalChartEntry::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/dental-chart-entries/{$entry->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('dental_chart_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }
}
