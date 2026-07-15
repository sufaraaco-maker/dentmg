<?php

namespace Tests\Unit\Policies;

use App\Models\DentistTimeOff;
use App\Models\User;
use App\Policies\DentistTimeOffPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentistTimeOffPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DentistTimeOffPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DentistTimeOffPolicy;
    }

    public function test_admin_can_create_time_off_for_any_dentist(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($admin, $dentist));
    }

    public function test_dentist_can_create_their_own_time_off(): void
    {
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->policy->create($dentist, $dentist));
    }

    public function test_dentist_cannot_create_time_off_for_another_dentist(): void
    {
        $dentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();

        $this->assertFalse($this->policy->create($dentist, $otherDentist));
    }

    public function test_cannot_create_time_off_for_a_non_dentist_user(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertFalse($this->policy->create($admin, $receptionist));
    }

    public function test_delete_allowed_for_admin_or_the_owning_dentist_only(): void
    {
        $dentist = User::factory()->dentist()->create();
        $timeOff = DentistTimeOff::factory()->create(['user_id' => $dentist->id]);

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $timeOff));
        $this->assertTrue($this->policy->delete($dentist, $timeOff));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $timeOff));
    }
}
