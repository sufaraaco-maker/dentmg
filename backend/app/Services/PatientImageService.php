<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Design doc §7 decision 5 / Approval Log item 5: every read/write/delete goes through the Storage
 * facade using the disk stored on the row — never a hardcoded disk or a raw filesystem path anywhere
 * else in the codebase. Thumbnail generation uses PHP's built-in GD extension (already enabled
 * project-wide, confirmed in ci.yml) — no new Composer dependency (design doc §10).
 */
class PatientImageService
{
    private const THUMBNAIL_MAX_DIMENSION = 400;

    private const EDITABLE_FIELDS = [
        'image_type', 'tooth_number', 'surfaces', 'taken_at',
        'treatment_plan_item_id', 'appointment_id', 'notes',
    ];

    /**
     * @param  list<UploadedFile>  $files
     * @param  array<string, mixed>  $metadata  Shared across every file in this batch (design doc §6).
     * @return list<PatientImage>
     */
    public function upload(Patient $patient, array $files, array $metadata, User $uploader): array
    {
        $disk = config('filesystems.default');

        return array_map(
            fn (UploadedFile $file) => $this->storeOne($patient, $file, $metadata, $uploader, $disk),
            $files,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PatientImage $patientImage, array $data): PatientImage
    {
        $patientImage->update(array_intersect_key($data, array_flip(self::EDITABLE_FIELDS)));

        return $patientImage;
    }

    /**
     * Soft delete only — admin-only at the policy layer (design doc §5). The underlying file is
     * intentionally NOT removed from storage on a soft delete, matching this codebase's existing
     * soft-delete discipline (a restored record must still have its file); a hard-delete/purge path
     * is not part of V1.
     */
    public function delete(PatientImage $patientImage): void
    {
        $patientImage->delete();
    }

    private function storeOne(Patient $patient, UploadedFile $file, array $metadata, User $uploader, string $disk): PatientImage
    {
        $id = (string) Str::uuid();
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $path = "patient-images/{$patient->id}/{$id}.{$extension}";

        $stored = Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        if (! $stored) {
            throw new \RuntimeException('Failed to store the uploaded image.');
        }

        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        $thumbnailPath = $this->generateThumbnail($disk, $file->getRealPath(), $patient->id, $id);

        return PatientImage::create([
            'patient_id' => $patient->id,
            'uploaded_by' => $uploader->id,
            'image_type' => $metadata['image_type'],
            'tooth_number' => $metadata['tooth_number'] ?? null,
            'surfaces' => $metadata['surfaces'] ?? null,
            'taken_at' => $metadata['taken_at'],
            'treatment_plan_item_id' => $metadata['treatment_plan_item_id'] ?? null,
            'appointment_id' => $metadata['appointment_id'] ?? null,
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'file_size' => $file->getSize() ?? 0,
            'width' => $width,
            'height' => $height,
            'notes' => $metadata['notes'] ?? null,
        ]);
    }

    /**
     * Best-effort — a corrupt/unsupported source image skips the thumbnail rather than failing the
     * whole upload (design doc: the gallery falls back to the full image when no thumbnail exists).
     * Always normalizes to JPEG output regardless of source format, keeping this simple.
     */
    private function generateThumbnail(string $disk, string $sourcePath, string $patientId, string $imageId): ?string
    {
        $imageInfo = @getimagesize($sourcePath);

        if ($imageInfo === false) {
            return null;
        }

        [$sourceWidth, $sourceHeight, $type] = $imageInfo;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if ($source === false || $source === null) {
            return null;
        }

        $ratio = min(self::THUMBNAIL_MAX_DIMENSION / $sourceWidth, self::THUMBNAIL_MAX_DIMENSION / $sourceHeight, 1);
        $thumbWidth = max(1, (int) round($sourceWidth * $ratio));
        $thumbHeight = max(1, (int) round($sourceHeight * $ratio));

        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($thumbnail, null, 80);
        $contents = ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumbnail);

        if ($contents === false) {
            return null;
        }

        $thumbnailPath = "patient-images/{$patientId}/thumbnails/{$imageId}.jpg";
        Storage::disk($disk)->put($thumbnailPath, $contents);

        return $thumbnailPath;
    }
}
