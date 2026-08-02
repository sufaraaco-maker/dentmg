<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Exceptions\AiAssistant\AiAssistantUnavailableException;
use App\Models\ClinicalNote;
use App\Models\ClinicSetting;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every AiAssistantService feature method checks gating (design doc §5) before ever constructing
 * an Anthropic client, so these gating paths are exercised through the real HTTP stack — no mocked
 * client needed. With no ANTHROPIC_API_KEY configured in the test environment (matching
 * PROJECT_CONTEXT.md's "optional, absent by default" guarantee — see phpunit.xml), the enabled+
 * acknowledged path throws `AiAssistantUnavailableException` (503) rather than actually reaching
 * the network, which itself proves the module never silently calls out when unconfigured.
 */
class AiAssistantGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_any_ai_assistant_endpoint(): void
    {
        $this->postJson('/api/ai-assistant/dashboard-insight', ['question' => 'x'])->assertUnauthorized();
        $this->postJson('/api/ai-assistant/smart-search', ['query' => 'x'])->assertUnauthorized();
        $this->getJson('/api/ai-assistant/interactions')->assertUnauthorized();
    }

    public function test_dashboard_insight_is_blocked_when_ai_assistant_disabled(): void
    {
        ClinicSetting::factory()->create(['ai_assistant_enabled' => false]);
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/dashboard-insight', [
            'question' => 'What was our collections rate last month?',
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'ai_assistant_disabled']);
    }

    public function test_dashboard_insight_reaches_unavailable_when_enabled_but_unconfigured(): void
    {
        ClinicSetting::factory()->create(['ai_assistant_enabled' => true]);
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/dashboard-insight', [
            'question' => 'What was our collections rate last month?',
        ]);

        // No ANTHROPIC_API_KEY in the test environment — proves this module never silently calls
        // out when the operator hasn't configured it, even with the clinic toggle on.
        $response->assertStatus(503)->assertJson(['code' => 'ai_assistant_unavailable']);
    }

    public function test_clinical_note_draft_is_blocked_without_phi_acknowledgment_even_when_enabled(): void
    {
        ClinicSetting::factory()->create([
            'ai_assistant_enabled' => true,
            'ai_assistant_phi_features_acknowledged' => false,
        ]);
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/ai-draft", [
            'shorthand' => 'pt c/o sensitivity #14, cold test +',
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'ai_assistant_phi_not_acknowledged']);
    }

    public function test_treatment_suggestions_is_blocked_without_phi_acknowledgment_even_when_enabled(): void
    {
        ClinicSetting::factory()->create([
            'ai_assistant_enabled' => true,
            'ai_assistant_phi_features_acknowledged' => false,
        ]);
        $actor = User::factory()->dentist()->create();
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/ai-suggestions");

        $response->assertStatus(422)->assertJson(['code' => 'ai_assistant_phi_not_acknowledged']);
    }

    public function test_clinical_note_draft_reaches_unavailable_once_phi_acknowledged(): void
    {
        ClinicSetting::factory()->create([
            'ai_assistant_enabled' => true,
            'ai_assistant_phi_features_acknowledged' => true,
        ]);
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/ai-draft", [
            'shorthand' => 'pt c/o sensitivity #14, cold test +',
        ]);

        $response->assertStatus(503)->assertJson(['code' => 'ai_assistant_unavailable']);
    }

    public function test_receptionist_cannot_request_a_clinical_note_draft(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $note = ClinicalNote::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/ai-draft", [
            'shorthand' => 'x',
        ]);

        $response->assertForbidden();
    }

    public function test_receptionist_cannot_request_treatment_suggestions(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $plan = TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/ai-suggestions");

        $response->assertForbidden();
    }

    public function test_receptionist_cannot_request_a_financial_report_narrative(): void
    {
        ClinicSetting::factory()->create(['ai_assistant_enabled' => true]);
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/report-narrative', [
            'report_type' => 'production',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertForbidden();
    }

    public function test_receptionist_can_request_an_operational_report_narrative(): void
    {
        ClinicSetting::factory()->create(['ai_assistant_enabled' => true]);
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/report-narrative', [
            'report_type' => 'new_patients',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        // Passes authorization (operational report, every role); only unavailable because no
        // ANTHROPIC_API_KEY is configured in the test environment.
        $response->assertStatus(503)->assertJson(['code' => 'ai_assistant_unavailable']);
    }

    public function test_draft_clinical_note_draft_assist_is_rejected_once_the_note_is_signed(): void
    {
        ClinicSetting::factory()->create([
            'ai_assistant_enabled' => true,
            'ai_assistant_phi_features_acknowledged' => true,
        ]);
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->signed()->create();

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/ai-draft", [
            'shorthand' => 'x',
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'clinical_note_locked']);
    }

    public function test_treatment_suggestions_is_rejected_once_the_plan_is_presented(): void
    {
        ClinicSetting::factory()->create([
            'ai_assistant_enabled' => true,
            'ai_assistant_phi_features_acknowledged' => true,
        ]);
        $actor = User::factory()->dentist()->create();
        $plan = TreatmentPlan::factory()->presented()->create();

        $response = $this->actingAs($actor)->postJson("/api/treatment-plans/{$plan->id}/ai-suggestions");

        $response->assertStatus(422)->assertJson(['code' => 'treatment_plan_item_locked']);
    }

    /**
     * `AiAssistantService::client()`'s key precedence (ai-assistant-settings-api-key-design.md §6):
     * a Settings-stored key must let the module past the "unconfigured" gate even with no
     * `ANTHROPIC_API_KEY` in the environment. Invoked via reflection on the private method directly
     * — constructing the SDK client is a local, no-network operation (it only stores config), so
     * this proves the precedence logic itself without the real network call an actual `messages->
     * create()` request would make (this test suite never makes real Anthropic API calls, per this
     * file's own class-level doc comment).
     */
    public function test_client_resolves_from_the_settings_stored_key_with_no_env_key_configured(): void
    {
        ClinicSetting::factory()->create([
            'ai_assistant_enabled' => true,
            'ai_assistant_api_key' => 'sk-ant-test-settings-key',
        ]);

        $service = app(AiAssistantService::class);
        $method = new \ReflectionMethod($service, 'client');
        $method->setAccessible(true);

        $client = $method->invoke($service);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_client_throws_unavailable_when_neither_settings_key_nor_env_key_is_configured(): void
    {
        ClinicSetting::factory()->create(['ai_assistant_enabled' => true]);

        $service = app(AiAssistantService::class);
        $method = new \ReflectionMethod($service, 'client');
        $method->setAccessible(true);

        $this->expectException(AiAssistantUnavailableException::class);
        $method->invoke($service);
    }
}
