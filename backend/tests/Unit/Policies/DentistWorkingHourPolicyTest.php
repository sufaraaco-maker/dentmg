<?php

namespace Tests\Unit\Policies;

use App\Models\DentistWorkingHour;
use App\Models\User;
use App\Policies\DentistWorkingHourPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentistWorkingHourPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DentistWorkingHourPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DentistWorkingHourPolicy;
    }

    public function test_admin_can_create_working_hours_for_a_dentist(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin, $dentist));
    }

    public function test_non_admin_cannot_create_working_hours(): void
    {
        $dentist = User::factory()->dentist()->create();

        $this->assertFalse($this->policy->create(User::factory()->create(['role' => 'receptionist']), $dentist));
        $this->assertFalse($this->policy->create(User::factory()->dentist()->create(), $dentist));
    }

    public function test_cannot_create_working_hours_for_a_non_dentist_user(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertFalse($this->policy->create($admin, $receptionist));
    }

    public function test_only_admin_can_delete_working_hours(): void
    {
        $workingHour = DentistWorkingHour::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $workingHour));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $workingHour));
    }
}
