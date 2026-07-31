<?php

namespace Tests\Feature;

use App\Models\AiInteractionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_insight_requires_a_question(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/dashboard-insight', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['question']);
    }

    public function test_smart_search_requires_a_query(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/smart-search', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['query']);
    }

    public function test_report_narrative_requires_a_valid_report_type(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/report-narrative', [
            'report_type' => 'not_a_real_report',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['report_type']);
    }

    public function test_report_narrative_requires_a_date_range_unless_ar_aging(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/ai-assistant/report-narrative', [
            'report_type' => 'production',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date_from', 'date_to']);
    }

    public function test_user_can_record_their_own_decision_on_a_suggestion(): void
    {
        $actor = User::factory()->dentist()->create();
        $log = AiInteractionLog::factory()->create(['user_id' => $actor->id, 'accepted' => null]);

        $response = $this->actingAs($actor)->postJson("/api/ai-assistant/interactions/{$log->id}/decision", [
            'accepted' => true,
            'response_summary' => 'Edited before saving',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('ai_interaction_logs', [
            'id' => $log->id,
            'accepted' => true,
            'response_summary' => 'Edited before saving',
        ]);
    }

    public function test_user_cannot_record_a_decision_on_someone_elses_interaction(): void
    {
        $owner = User::factory()->dentist()->create();
        $other = User::factory()->dentist()->create();
        $log = AiInteractionLog::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->postJson("/api/ai-assistant/interactions/{$log->id}/decision", [
            'accepted' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_record_decision_requires_accepted_boolean(): void
    {
        $actor = User::factory()->dentist()->create();
        $log = AiInteractionLog::factory()->create(['user_id' => $actor->id]);

        $response = $this->actingAs($actor)->postJson("/api/ai-assistant/interactions/{$log->id}/decision", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['accepted']);
    }

    public function test_index_returns_only_the_actors_own_interactions_by_default(): void
    {
        $actor = User::factory()->dentist()->create();
        $other = User::factory()->dentist()->create();
        AiInteractionLog::factory()->create(['user_id' => $actor->id]);
        AiInteractionLog::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($actor)->getJson('/api/ai-assistant/interactions');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_view_every_users_interactions_with_all_param(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        AiInteractionLog::factory()->create(['user_id' => $admin->id]);
        AiInteractionLog::factory()->create(['user_id' => $dentist->id]);

        $response = $this->actingAs($admin)->getJson('/api/ai-assistant/interactions?all=1');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_non_admin_all_param_is_ignored_and_still_scoped_to_own(): void
    {
        $actor = User::factory()->dentist()->create();
        $other = User::factory()->dentist()->create();
        AiInteractionLog::factory()->create(['user_id' => $actor->id]);
        AiInteractionLog::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($actor)->getJson('/api/ai-assistant/interactions?all=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
