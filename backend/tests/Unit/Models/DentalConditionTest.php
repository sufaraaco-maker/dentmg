<?php

namespace Tests\Unit\Models;

use App\Enums\DentalConditionCategory;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentalConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_boolean_fields_are_cast(): void
    {
        $condition = DentalCondition::factory()->create([
            'category' => DentalConditionCategory::Procedure,
            'applies_to_surface' => true,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(DentalConditionCategory::class, $condition->category);
        $this->assertSame(DentalConditionCategory::Procedure, $condition->category);
        $this->assertIsBool($condition->applies_to_surface);
        $this->assertIsBool($condition->is_active);
    }

    public function test_scope_active_only_returns_active_conditions(): void
    {
        DentalCondition::factory()->create(['is_active' => true]);
        DentalCondition::factory()->create(['is_active' => false]);

        $this->assertCount(1, DentalCondition::active()->get());
    }

    public function test_has_many_dental_chart_entries(): void
    {
        $condition = DentalCondition::factory()->create();
        DentalChartEntry::factory()->count(2)->create(['dental_condition_id' => $condition->id]);

        $this->assertCount(2, $condition->dentalChartEntries);
    }

    public function test_deactivating_a_condition_does_not_break_entries_that_reference_it(): void
    {
        $condition = DentalCondition::factory()->create(['is_active' => true]);
        $entry = DentalChartEntry::factory()->create(['dental_condition_id' => $condition->id]);

        $condition->update(['is_active' => false]);

        $this->assertSame($condition->id, $entry->fresh()->dentalCondition->id);
        $this->assertFalse($entry->fresh()->dentalCondition->is_active);
    }
}
