<?php

namespace Tests\Unit\Services;

use App\Models\DentalCondition;
use App\Services\DentalConditionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentalConditionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DentalConditionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DentalConditionService;
    }

    public function test_list_returns_every_condition_ordered_by_sort_order_then_name(): void
    {
        DentalCondition::factory()->create(['name' => 'Zzz', 'sort_order' => 1, 'is_active' => false]);
        DentalCondition::factory()->create(['name' => 'Aaa', 'sort_order' => 2]);
        DentalCondition::factory()->create(['name' => 'Bbb', 'sort_order' => 1]);

        $names = $this->service->list()->pluck('name')->all();

        $this->assertSame(['Bbb', 'Zzz', 'Aaa'], $names);
    }

    public function test_create_defaults_is_active_to_true_when_omitted(): void
    {
        $condition = $this->service->create([
            'name' => 'Crown',
            'category' => 'procedure',
            'default_color' => '#2563EB',
            'applies_to_surface' => false,
        ]);

        $this->assertTrue($condition->is_active);
        $this->assertDatabaseHas('dental_conditions', ['id' => $condition->id, 'is_active' => true]);
    }

    public function test_create_respects_an_explicit_is_active_value(): void
    {
        $condition = $this->service->create([
            'name' => 'Deprecated Finding',
            'category' => 'finding',
            'default_color' => '#EF4444',
            'applies_to_surface' => false,
            'is_active' => false,
        ]);

        $this->assertFalse($condition->is_active);
    }

    public function test_update_persists_changes(): void
    {
        $condition = DentalCondition::factory()->create(['name' => 'Old Name']);

        $updated = $this->service->update($condition, ['name' => 'New Name']);

        $this->assertSame('New Name', $updated->name);
        $this->assertDatabaseHas('dental_conditions', ['id' => $condition->id, 'name' => 'New Name']);
    }

    public function test_delete_removes_the_condition(): void
    {
        $condition = DentalCondition::factory()->create();

        $this->service->delete($condition);

        $this->assertDatabaseMissing('dental_conditions', ['id' => $condition->id]);
    }
}
