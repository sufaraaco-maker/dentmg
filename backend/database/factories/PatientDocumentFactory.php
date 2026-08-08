<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientDocument>
 */
class PatientDocumentFactory extends Factory
{
    public function definition(): array
    {
        $id = fake()->uuid();

        return [
            'patient_id' => Patient::factory(),
            'uploaded_by' => User::factory(),
            'category' => fake()->randomElement(DocumentCategory::cases()),
            'title' => fake()->sentence(3),
            'original_filename' => "{$id}.pdf",
            'disk' => 'local',
            'path' => "patient-documents/test/{$id}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 2_000_000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
