<?php

namespace Tests\Feature;

use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_updating_clinic_settings_without_a_name_field_leaves_the_stored_name_untouched(): void
    {
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create(['name' => 'Bright Smile Dental']);

        $response = $this->actingAs($actor)->putJson('/api/clinic-settings', [
            'phone' => '+1 555-0199',
        ]);

        $response->assertOk();
        $this->assertSame('Bright Smile Dental', $response->json('name'));
        $this->assertSame('+1 555-0199', $response->json('phone'));
    }

    // Clinic logo upload (2026-08-13) — always the `public` disk, always a plain rendered URL.

    public function test_admin_can_upload_a_clinic_logo(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/clinic-settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('logo_url'));
        $settings = ClinicSetting::query()->first();
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_uploading_a_new_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create();

        $this->actingAs($actor)->postJson('/api/clinic-settings/logo', [
            'logo' => UploadedFile::fake()->image('first.png'),
        ])->assertOk();
        $firstPath = ClinicSetting::query()->first()->logo_path;

        $this->actingAs($actor)->postJson('/api/clinic-settings/logo', [
            'logo' => UploadedFile::fake()->image('second.png'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists(ClinicSetting::query()->first()->logo_path);
    }

    public function test_admin_can_remove_the_clinic_logo(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();
        ClinicSetting::factory()->create();

        $this->actingAs($actor)->postJson('/api/clinic-settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();
        $path = ClinicSetting::query()->first()->logo_path;

        $response = $this->actingAs($actor)->deleteJson('/api/clinic-settings/logo');

        $response->assertOk();
        $this->assertNull($response->json('logo_url'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_dentist_cannot_upload_a_clinic_logo(): void
    {
        Storage::fake('public');
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson('/api/clinic-settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertForbidden();
    }

    public function test_clinic_logo_upload_rejects_a_non_image_file(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/clinic-settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.pdf', 100),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['logo']);
    }
}
