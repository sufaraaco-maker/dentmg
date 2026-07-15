<?php

namespace Tests\Unit\Policies;

use App\Models\AppointmentType;
use App\Models\User;
use App\Policies\AppointmentTypePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTypePolicyTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentTypePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AppointmentTypePolicy;
    }

    public function test_only_admin_can_create_update_or_delete_appointment_types(): void
    {
        $type = AppointmentType::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $type));
        $this->assertTrue($this->policy->delete($admin, $type));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $type));
        $this->assertFalse($this->policy->delete($dentist, $type));
    }

    public function test_any_role_can_view_appointment_types(): void
    {
        $type = AppointmentType::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->dentist()->create()));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $type));
    }
}
