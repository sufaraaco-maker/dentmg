<?php

namespace Database\Factories;

use App\Enums\ImageType;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientImage>
 */
class PatientImageFactory extends Factory
{
    public function definition(): array
    {
        $id = fake()->uuid();

        return [
            'patient_id' => Patient::factory(),
            'uploaded_by' => User::factory(),
            'image_type' => fake()->randomElement(ImageType::cases()),
            'tooth_number' => null,
            'surfaces' => null,
            'taken_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'treatment_plan_item_id' => null,
            'appointment_id' => null,
            'disk' => 'local',
            'path' => "patient-images/test/{$id}.jpg",
            'thumbnail_path' => "patient-images/test/thumbnails/{$id}.jpg",
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(20_000, 4_000_000),
            'width' => 1024,
            'height' => 768,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
