<?php

namespace Tests\Unit\Policies;

use App\Models\InvoiceItem;
use App\Models\User;
use App\Policies\InvoiceItemPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceItemPolicyTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceItemPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new InvoiceItemPolicy;
    }

    public function test_admin_and_receptionist_can_create_edit_and_delete_items(): void
    {
        $item = InvoiceItem::factory()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->create(['role' => 'receptionist'])] as $actor) {
            $this->assertTrue($this->policy->create($actor));
            $this->assertTrue($this->policy->update($actor, $item));
            $this->assertTrue($this->policy->delete($actor, $item));
        }
    }

    public function test_dentist_cannot_create_edit_or_delete_items(): void
    {
        $item = InvoiceItem::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->update($dentist, $item));
        $this->assertFalse($this->policy->delete($dentist, $item));
    }

    public function test_any_role_can_view_items(): void
    {
        $item = InvoiceItem::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->viewAny($dentist));
        $this->assertTrue($this->policy->view($dentist, $item));
    }
}
