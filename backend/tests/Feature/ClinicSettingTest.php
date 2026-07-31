<?php

namespace Tests\Feature;

use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
