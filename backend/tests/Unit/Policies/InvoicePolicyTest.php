<?php

namespace Tests\Unit\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new InvoicePolicy;
    }

    public function test_admin_and_receptionist_can_create_and_edit_invoices(): void
    {
        $invoice = Invoice::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $invoice));
        $this->assertTrue($this->policy->create($receptionist));
        $this->assertTrue($this->policy->update($receptionist, $invoice));

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->update($dentist, $invoice));
    }

    public function test_admin_and_receptionist_can_issue_and_void(): void
    {
        $invoice = Invoice::factory()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->create(['role' => 'receptionist'])] as $actor) {
            $this->assertTrue($this->policy->issue($actor, $invoice));
            $this->assertTrue($this->policy->void($actor, $invoice));
        }
    }

    public function test_dentist_cannot_issue_or_void(): void
    {
        $invoice = Invoice::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($this->policy->issue($dentist, $invoice));
        $this->assertFalse($this->policy->void($dentist, $invoice));
    }

    public function test_only_admin_can_delete_an_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $invoice));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $invoice));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $invoice));
    }

    public function test_any_role_can_view_invoices(): void
    {
        $invoice = Invoice::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->viewAny($dentist));
        $this->assertTrue($this->policy->view($dentist, $invoice));
    }
}
