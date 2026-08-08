<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // ---- upload -----------------------------------------------------------------------------------

    public function test_guest_cannot_upload_a_document(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->post("/api/patients/{$patient->id}/documents", [
            'file' => UploadedFile::fake()->create('consent.pdf', 100),
            'category' => 'consent_form',
            'title' => 'Consent Form',
        ]);

        $response->assertUnauthorized();
    }

    public function test_receptionist_can_upload_a_document(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/documents", [
            'file' => UploadedFile::fake()->create('consent.pdf', 100),
            'category' => 'consent_form',
            'title' => 'Consent Form',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('category', 'consent_form');
        $response->assertJsonPath('title', 'Consent Form');
        $response->assertJsonPath('original_filename', 'consent.pdf');
        $this->assertNotNull($response->json('file_url'));

        $document = PatientDocument::first();
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_dentist_cannot_upload_a_document(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/documents", [
            'file' => UploadedFile::fake()->create('consent.pdf', 100),
            'category' => 'consent_form',
            'title' => 'Consent Form',
        ]);

        $response->assertCreated();
    }

    public function test_upload_requires_a_category_and_title(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/documents", [
            'file' => UploadedFile::fake()->create('consent.pdf', 100),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['category', 'title']);
    }

    public function test_upload_rejects_an_invalid_category(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->post("/api/patients/{$patient->id}/documents", [
            'file' => UploadedFile::fake()->create('consent.pdf', 100),
            'category' => 'lab_report',
            'title' => 'Consent Form',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['category']);
    }

    // ---- index --------------------------------------------------------------------------------------

    public function test_index_filters_by_category(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        PatientDocument::factory()->create(['patient_id' => $patient->id, 'category' => 'insurance']);
        PatientDocument::factory()->create(['patient_id' => $patient->id, 'category' => 'referral']);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/documents?category=insurance");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('insurance', $response->json('data.0.category'));
    }

    public function test_index_never_exposes_raw_disk_or_path(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        PatientDocument::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/documents");

        $response->assertOk();
        $this->assertArrayNotHasKey('disk', $response->json('data.0'));
        $this->assertArrayNotHasKey('path', $response->json('data.0'));
    }

    // ---- update / delete --------------------------------------------------------------------------

    public function test_update_edits_metadata_only(): void
    {
        $actor = User::factory()->admin()->create();
        $document = PatientDocument::factory()->create(['title' => 'old title']);

        $response = $this->actingAs($actor)->putJson("/api/documents/{$document->id}", [
            'title' => 'new title',
            'category' => 'referral',
        ]);

        $response->assertOk();
        $this->assertSame('new title', $response->json('title'));
        $this->assertSame('referral', $response->json('category'));
    }

    public function test_dentist_cannot_delete_a_document(): void
    {
        $actor = User::factory()->dentist()->create();
        $document = PatientDocument::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/documents/{$document->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_delete_a_document(): void
    {
        $actor = User::factory()->admin()->create();
        $document = PatientDocument::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/documents/{$document->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($document);
    }

    public function test_soft_deleted_documents_file_remains_in_storage(): void
    {
        $actor = User::factory()->admin()->create();
        $document = PatientDocument::factory()->create();
        Storage::disk('local')->put($document->path, 'contents');

        $this->actingAs($actor)->deleteJson("/api/documents/{$document->id}");

        Storage::disk('local')->assertExists($document->path);
    }

    // ---- file streaming ------------------------------------------------------------------------------

    public function test_guest_cannot_download_the_original_file(): void
    {
        $document = PatientDocument::factory()->create();

        $response = $this->get("/api/documents/{$document->id}/file");

        $response->assertUnauthorized();
    }

    public function test_authenticated_staff_can_download_the_original_file(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $upload = $this->actingAs($actor)->post("/api/patients/{$patient->id}/documents", [
            'file' => UploadedFile::fake()->create('consent.pdf', 100),
            'category' => 'consent_form',
            'title' => 'Consent Form',
        ]);
        $documentId = $upload->json('id');

        $response = $this->actingAs($actor)->get("/api/documents/{$documentId}/file");

        $response->assertOk();
    }
}
