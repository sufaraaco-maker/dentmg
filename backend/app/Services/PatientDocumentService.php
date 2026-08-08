<?php

namespace App\Services;

use App\Events\PatientActivityOccurred;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Design doc §6/§16 decision 5: every read/write/delete goes through the Storage facade using the
 * disk stored on the row — never a hardcoded disk. Clones PatientImageService's convention exactly,
 * minus thumbnail generation (design doc §1.3/§15 — generic documents don't need it).
 */
class PatientDocumentService
{
    private const EDITABLE_FIELDS = ['category', 'title', 'notes'];

    /**
     * One file per call — see StorePatientDocumentRequest for why this doesn't batch the way
     * PatientImageService::upload() does.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function upload(Patient $patient, UploadedFile $file, array $metadata, User $uploader): PatientDocument
    {
        return $this->storeOne($patient, $file, $metadata, $uploader, config('filesystems.default'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PatientDocument $document, array $data): PatientDocument
    {
        $document->update(array_intersect_key($data, array_flip(self::EDITABLE_FIELDS)));

        return $document;
    }

    /**
     * Soft delete only — admin-only at the policy layer. The underlying file is intentionally NOT
     * removed from storage on a soft delete, matching PatientImageService's identical discipline (a
     * restored record must still have its file); a hard-delete/purge path is not part of V1.
     */
    public function delete(PatientDocument $document): void
    {
        $document->delete();
    }

    private function storeOne(Patient $patient, UploadedFile $file, array $metadata, User $uploader, string $disk): PatientDocument
    {
        $id = (string) Str::uuid();
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'bin');
        $path = "patient-documents/{$patient->id}/{$id}.{$extension}";

        $stored = Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        if (! $stored) {
            throw new \RuntimeException('Failed to store the uploaded document.');
        }

        $document = PatientDocument::create([
            'patient_id' => $patient->id,
            'uploaded_by' => $uploader->id,
            'category' => $metadata['category'],
            'title' => $metadata['title'],
            'original_filename' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $metadata['notes'] ?? null,
        ]);

        event(new PatientActivityOccurred(
            $document, $uploader, 'document.uploaded', 'documents', "Document uploaded: {$document->title}",
        ));

        return $document;
    }
}
