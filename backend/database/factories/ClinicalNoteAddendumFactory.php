<?php

namespace Database\Factories;

use App\Models\ClinicalNote;
use App\Models\ClinicalNoteAddendum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalNoteAddendum>
 */
class ClinicalNoteAddendumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinical_note_id' => ClinicalNote::factory()->signed(),
            'author_id' => User::factory()->dentist(),
            'body' => fake()->paragraph(),
        ];
    }
}
