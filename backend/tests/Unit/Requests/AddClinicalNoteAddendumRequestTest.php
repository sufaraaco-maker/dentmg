<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ClinicalNote\AddClinicalNoteAddendumRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AddClinicalNoteAddendumRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new AddClinicalNoteAddendumRequest)->rules();
    }

    public function test_body_is_required(): void
    {
        $this->assertTrue(Validator::make([], $this->rules())->fails());
    }

    public function test_valid_body_passes(): void
    {
        $this->assertTrue(Validator::make(['body' => 'Patient called back, pain resolved.'], $this->rules())->passes());
    }

    public function test_body_cannot_exceed_the_max_length(): void
    {
        $this->assertTrue(Validator::make(['body' => str_repeat('a', 10001)], $this->rules())->fails());
    }
}
