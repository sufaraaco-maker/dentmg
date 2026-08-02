<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_clinic_settings(): void
    {
        $response = $this->getJson('/api/clinic-settings');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_view_clinic_settings(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        ClinicSetting::factory()->create(['name' => 'Bright Smile Dental']);

        $response = $this->actingAs($actor)->getJson('/api/clinic-settings');

        $response->assertOk();
        $this->assertSame('Bright Smile Dental', $response->json('name'));
    }

    public function test_viewing_clinic_settings_self_heals_a_stub_row_when_none_exists(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->getJson('/api/clinic-settings');

        $response->assertOk();
        $this->assertSame('', $response->json('name'));
        $this->assertDatabaseCount('clinic_settings', 1);
    }

    public function test_admin_can_update_clinic_settings(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'name' => 'Downtown Dental Clinic',
            'phone' => '+1 555-0100',
            'address' => '123 Main St',
            'email' => 'contact@downtowndental.example',
        ]);

        $response->assertOk();
        $this->assertSame('Downtown Dental Clinic', $response->json('name'));
        $this->assertDatabaseHas('clinic_settings', ['name' => 'Downtown Dental Clinic']);
        $this->assertDatabaseCount('clinic_settings', 1);
    }

    public function test_dentist_cannot_update_clinic_settings(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', ['name' => 'X']);

        $response->assertForbidden();
    }

    public function test_receptionist_cannot_update_clinic_settings(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', ['name' => 'X']);

        $response->assertForbidden();
    }

    public function test_updating_clinic_settings_requires_a_name(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', ['name' => '']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_ai_assistant_toggles_default_to_false(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->getJson('/api/clinic-settings');

        $response->assertOk();
        $this->assertFalse($response->json('ai_assistant_enabled'));
        $this->assertFalse($response->json('ai_assistant_phi_features_acknowledged'));
    }

    public function test_admin_can_enable_ai_assistant_without_acknowledging_phi_features(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'name' => 'Downtown Dental Clinic',
            'ai_assistant_enabled' => true,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('ai_assistant_enabled'));
        $this->assertFalse($response->json('ai_assistant_phi_features_acknowledged'));
    }

    /**
     * Regression test: a fresh, never-configured clinic's self-healed row has a blank `name`
     * (`ClinicSettingService::current()`). The AI Assistant Settings screen must be able to save
     * its two toggles on their own without being forced to also resend (and re-validate as
     * non-blank) a practice name it never touches.
     */
    public function test_admin_can_save_ai_assistant_toggles_alone_when_practice_name_is_still_blank(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create(['name' => '']);

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'ai_assistant_enabled' => true,
            'ai_assistant_phi_features_acknowledged' => true,
        ]);

        $response->assertOk();
        $this->assertSame('', $response->json('name'));
        $this->assertTrue($response->json('ai_assistant_enabled'));
        $this->assertTrue($response->json('ai_assistant_phi_features_acknowledged'));
    }

    public function test_updating_clinic_settings_without_a_name_field_leaves_the_stored_name_untouched(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create(['name' => 'Bright Smile Dental']);

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'ai_assistant_enabled' => true,
        ]);

        $response->assertOk();
        $this->assertSame('Bright Smile Dental', $response->json('name'));
    }

    // AI Assistant API key (ai-assistant-settings-api-key-design.md §11) — the response must never
    // expose the raw value, only a `configured` boolean + last-4, and the value must never land in
    // the audit trail in plain form.

    public function test_admin_can_set_the_ai_assistant_api_key_and_the_response_never_exposes_the_raw_value(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'ai_assistant_api_key' => 'sk-ant-abcd1234',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('ai_assistant_api_key_configured'));
        $this->assertSame('1234', $response->json('ai_assistant_api_key_last4'));
        $response->assertJsonMissingPath('ai_assistant_api_key');

        // Stored encrypted, not plaintext. `ClinicSetting::query()->value()` hydrates a full model
        // (casts apply, decrypting it) — `DB::table()` is the actual raw column value, bypassing
        // Eloquent entirely.
        $raw = DB::table('clinic_settings')->value('ai_assistant_api_key');
        $this->assertNotSame('sk-ant-abcd1234', $raw);
        $this->assertSame('sk-ant-abcd1234', ClinicSetting::query()->first()->ai_assistant_api_key);
    }

    public function test_saving_the_ai_assistant_api_key_does_not_write_its_raw_value_into_the_audit_trail(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create();

        $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'ai_assistant_api_key' => 'sk-ant-abcd1234',
        ])->assertOk();

        $log = AuditLog::query()->where('auditable_type', ClinicSetting::class)->latest('created_at')->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('ai_assistant_api_key', $log->changes ?? []);
        $this->assertStringNotContainsString('sk-ant-abcd1234', json_encode($log->changes));
    }

    public function test_admin_can_remove_the_ai_assistant_api_key_with_an_explicit_null(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create(['ai_assistant_api_key' => 'sk-ant-abcd1234']);

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'ai_assistant_api_key' => null,
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('ai_assistant_api_key_configured'));
        $this->assertNull($response->json('ai_assistant_api_key_last4'));
        $this->assertNull(ClinicSetting::query()->first()->ai_assistant_api_key);
    }

    public function test_omitting_the_ai_assistant_api_key_field_leaves_a_previously_saved_key_untouched(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create(['ai_assistant_api_key' => 'sk-ant-abcd1234']);

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'ai_assistant_enabled' => true,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('ai_assistant_api_key_configured'));
        $this->assertSame('sk-ant-abcd1234', ClinicSetting::query()->first()->ai_assistant_api_key);
    }

    public function test_dentist_cannot_set_the_ai_assistant_api_key(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'ai_assistant_api_key' => 'sk-ant-abcd1234',
        ]);

        $response->assertForbidden();
    }
}
