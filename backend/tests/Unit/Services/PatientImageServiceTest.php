<?php

namespace Tests\Unit\Services;

use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\User;
use App\Services\PatientImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private PatientImageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->service = new PatientImageService;
    }

    public function test_upload_stores_the_file_and_a_thumbnail_via_the_storage_facade(): void
    {
        $patient = Patient::factory()->create();
        $uploader = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('xray.jpg', 800, 600);

        [$image] = $this->service->upload($patient, [$file], [
            'image_type' => 'xray_periapical',
            'taken_at' => now()->toDateString(),
        ], $uploader);

        $this->assertSame('local', $image->disk);
        $this->assertStringStartsWith("patient-images/{$patient->id}/", $image->path);
        $this->assertNotNull($image->thumbnail_path);
        Storage::disk('local')->assertExists($image->path);
        Storage::disk('local')->assertExists($image->thumbnail_path);
        $this->assertSame(800, $image->width);
        $this->assertSame(600, $image->height);
    }

    public function test_upload_skips_the_thumbnail_gracefully_for_a_corrupt_image(): void
    {
        $patient = Patient::factory()->create();
        $uploader = User::factory()->admin()->create();
        // A .jpg-named file with non-image bytes — passes the mimes:jpg validation rule upstream in
        // real traffic terms this test bypasses (it calls the service directly), but must still not
        // crash thumbnail generation.
        $file = UploadedFile::fake()->createWithContent('broken.jpg', 'not a real image');

        [$image] = $this->service->upload($patient, [$file], [
            'image_type' => 'other',
            'taken_at' => now()->toDateString(),
        ], $uploader);

        $this->assertNull($image->thumbnail_path);
        Storage::disk('local')->assertExists($image->path);
    }

    public function test_update_only_touches_editable_metadata_fields(): void
    {
        $image = PatientImage::factory()->create(['path' => 'patient-images/x/original.jpg']);

        $updated = $this->service->update($image, [
            'notes' => 'updated',
            'path' => 'patient-images/x/hacked.jpg',
        ]);

        $this->assertSame('updated', $updated->notes);
        $this->assertSame('patient-images/x/original.jpg', $updated->path);
    }

    public function test_delete_soft_deletes_without_removing_the_stored_file(): void
    {
        $patient = Patient::factory()->create();
        $uploader = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('xray.jpg');

        [$image] = $this->service->upload($patient, [$file], [
            'image_type' => 'xray_periapical',
            'taken_at' => now()->toDateString(),
        ], $uploader);

        $this->service->delete($image);

        $this->assertSoftDeleted($image);
        Storage::disk('local')->assertExists($image->path);
    }
}
