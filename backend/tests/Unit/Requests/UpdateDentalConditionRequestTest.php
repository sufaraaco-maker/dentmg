<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\DentalCondition\UpdateDentalConditionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateDentalConditionRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new UpdateDentalConditionRequest)->rules();
    }

    public function test_empty_payload_passes_since_every_field_is_optional(): void
    {
        $this->assertTrue(Validator::make([], $this->rules())->passes());
    }

    public function test_a_provided_field_must_still_satisfy_its_own_rules(): void
    {
        $this->assertTrue(Validator::make(['default_color' => 'not-a-hex-color'], $this->rules())->fails());
        $this->assertTrue(Validator::make(['category' => 'not-a-real-category'], $this->rules())->fails());
        $this->assertTrue(Validator::make(['name' => ''], $this->rules())->fails());
    }

    public function test_a_valid_partial_payload_passes(): void
    {
        $this->assertTrue(Validator::make(['name' => 'Composite Filling', 'is_active' => false], $this->rules())->passes());
    }
}
