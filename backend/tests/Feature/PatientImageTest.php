<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // ---- upload -----------------------------------------------------------------------------------

    public function test_guest_cannot_upload_images(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->image('xray.jpg')],
            'image_type' => 'xray_periapical',
            'taken_at' => now()->toDateString(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_receptionist_can_upload_an_image(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->image('xray.jpg', 800, 600)],
            'image_type' => 'xray_periapical',
            'tooth_number' => '16',
            'taken_at' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertCount(1, $response->json());
        $this->assertSame('xray_periapical', $response->json('0.image_type'));
        $this->assertSame('16', $response->json('0.tooth_number'));
        $this->assertNotNull($response->json('0.file_url'));
        $this->assertNotNull($response->json('0.thumbnail_url'));

        $image = PatientImage::first();
        Storage::disk('local')->assertExists($image->path);
        Storage::disk('local')->assertExists($image->thumbnail_path);
    }

    public function test_uploading_multiple_files_in_one_request_creates_one_row_each(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
            'image_type' => 'intraoral_photo',
            'taken_at' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertCount(3, $response->json());
        $this->assertSame(3, PatientImage::count());
    }

    public function test_upload_rejects_an_invalid_tooth_code(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->image('xray.jpg')],
            'image_type' => 'xray_periapical',
            'tooth_number' => '99',
            'taken_at' => now()->toDateString(),
        ]);

        $response->assertUnprocessable();
    }

    public function test_upload_rejects_a_non_image_file(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->create('report.pdf', 100)],
            'image_type' => 'other',
            'taken_at' => now()->toDateString(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_upload_rejects_a_future_taken_at_date(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->image('xray.jpg')],
            'image_type' => 'xray_periapical',
            'taken_at' => now()->addDay()->toDateString(),
        ]);

        $response->assertUnprocessable();
    }

    public function test_traceability_links_must_belong_to_the_same_patient(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $otherPatientsAppointment = Appointment::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->image('xray.jpg')],
            'image_type' => 'xray_periapical',
            'taken_at' => now()->toDateString(),
            'appointment_id' => $otherPatientsAppointment->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_traceability_link_to_own_patients_treatment_plan_item_succeeds(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $item = TreatmentPlanItem::factory()->create([
            'treatment_plan_id' => TreatmentPlan::factory()->create(['patient_id' => $patient->id]),
        ]);

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->image('xray.jpg')],
            'image_type' => 'xray_periapical',
            'taken_at' => now()->toDateString(),
            'treatment_plan_item_id' => $item->id,
        ]);

        $response->assertCreated();
        $this->assertSame($item->id, $response->json('0.treatment_plan_item_id'));
    }

    // ---- index --------------------------------------------------------------------------------------

    public function test_index_filters_by_image_type_and_tooth(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        PatientImage::factory()->create(['patient_id' => $patient->id, 'image_type' => 'xray_periapical', 'tooth_number' => '16']);
        PatientImage::factory()->create(['patient_id' => $patient->id, 'image_type' => 'intraoral_photo', 'tooth_number' => null]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/images?image_type=xray_periapical");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('16', $response->json('data.0.tooth_number'));
    }

    public function test_index_never_exposes_raw_disk_or_path(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        PatientImage::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/images");

        $response->assertOk();
        $this->assertArrayNotHasKey('disk', $response->json('data.0'));
        $this->assertArrayNotHasKey('path', $response->json('data.0'));
    }

    // ---- update / delete --------------------------------------------------------------------------

    public function test_update_edits_metadata_only(): void
    {
        $actor = User::factory()->admin()->create();
        $image = PatientImage::factory()->create(['notes' => 'old note']);

        $response = $this->actingAs($actor)->putJson("/api/images/{$image->id}", [
            'notes' => 'new note',
            'image_type' => 'xray_bitewing',
        ]);

        $response->assertOk();
        $this->assertSame('new note', $response->json('notes'));
        $this->assertSame('xray_bitewing', $response->json('image_type'));
    }

    public function test_dentist_cannot_delete_an_image(): void
    {
        $actor = User::factory()->dentist()->create();
        $image = PatientImage::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/images/{$image->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_delete_an_image(): void
    {
        $actor = User::factory()->admin()->create();
        $image = PatientImage::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/images/{$image->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($image);
    }

    // ---- file / thumbnail streaming ----------------------------------------------------------------

    public function test_guest_cannot_download_the_original_file(): void
    {
        $image = PatientImage::factory()->create();

        $response = $this->get("/api/images/{$image->id}/file");

        $response->assertUnauthorized();
    }

    public function test_authenticated_staff_can_download_the_original_file(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $upload = $this->actingAs($actor)->post("/api/patients/{$patient->id}/images", [
            'images' => [UploadedFile::fake()->image('xray.jpg', 800, 600)],
            'image_type' => 'xray_periapical',
            'taken_at' => now()->toDateString(),
        ]);
        $imageId = $upload->json('0.id');

        $response = $this->actingAs($actor)->get("/api/images/{$imageId}/file");

        $response->assertOk();
    }
}
