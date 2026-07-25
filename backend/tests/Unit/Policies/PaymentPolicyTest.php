<?php

namespace Tests\Unit\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PaymentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PaymentPolicy;
    }

    public function test_admin_and_receptionist_can_create_update_apply_and_refund(): void
    {
        $payment = Payment::factory()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->create(['role' => 'receptionist'])] as $actor) {
            $this->assertTrue($this->policy->create($actor));
            $this->assertTrue($this->policy->update($actor, $payment));
            $this->assertTrue($this->policy->apply($actor, $payment));
            $this->assertTrue($this->policy->refund($actor, $payment));
        }
    }

    public function test_dentist_cannot_create_update_apply_or_refund(): void
    {
        $payment = Payment::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($this->policy->create($dentist));
        $this->assertFalse($this->policy->update($dentist, $payment));
        $this->assertFalse($this->policy->apply($dentist, $payment));
        $this->assertFalse($this->policy->refund($dentist, $payment));
    }

    public function test_only_admin_can_delete_a_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $payment));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $payment));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $payment));
    }

    public function test_any_role_can_view_payments(): void
    {
        $payment = Payment::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->viewAny($dentist));
        $this->assertTrue($this->policy->view($dentist, $payment));
    }
}
