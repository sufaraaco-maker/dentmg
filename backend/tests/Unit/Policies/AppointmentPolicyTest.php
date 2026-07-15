<?php

namespace Tests\Unit\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AppointmentPolicy;
    }

    public function test_admin_and_receptionist_can_create_appointments(): void
    {
        $this->assertTrue($this->policy->create(User::factory()->admin()->create()));
        $this->assertTrue($this->policy->create(User::factory()->create(['role' => 'receptionist'])));
    }

    public function test_dentist_cannot_create_appointments(): void
    {
        $this->assertFalse($this->policy->create(User::factory()->dentist()->create()));
    }

    public function test_admin_and_receptionist_can_update_cancel_and_mark_no_show(): void
    {
        $appointment = Appointment::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->update($admin, $appointment));
        $this->assertTrue($this->policy->cancel($admin, $appointment));
        $this->assertTrue($this->policy->markNoShow($admin, $appointment));

        $this->assertTrue($this->policy->update($receptionist, $appointment));
        $this->assertTrue($this->policy->cancel($receptionist, $appointment));
        $this->assertTrue($this->policy->markNoShow($receptionist, $appointment));
    }

    public function test_dentist_cannot_update_cancel_or_mark_no_show(): void
    {
        $appointment = Appointment::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($this->policy->update($dentist, $appointment));
        $this->assertFalse($this->policy->cancel($dentist, $appointment));
        $this->assertFalse($this->policy->markNoShow($dentist, $appointment));
    }

    public function test_only_admin_can_delete_an_appointment(): void
    {
        $appointment = Appointment::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $appointment));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $appointment));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $appointment));
    }

    public function test_any_role_can_view_appointments_clinic_wide(): void
    {
        $appointment = Appointment::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->dentist()->create(), $appointment));
    }
}
