<?php

namespace Tests\Unit\Policies;

use App\Models\DentalChartEntry;
use App\Models\User;
use App\Policies\DentalChartEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentalChartEntryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DentalChartEntryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DentalChartEntryPolicy;
    }

    public function test_admin_and_dentist_can_create_update_complete_and_cancel_entries(): void
    {
        $entry = DentalChartEntry::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $entry));
        $this->assertTrue($this->policy->complete($admin, $entry));
        $this->assertTrue($this->policy->cancel($admin, $entry));

        $this->assertTrue($this->policy->create($dentist));
        $this->assertTrue($this->policy->update($dentist, $entry));
        $this->assertTrue($this->policy->complete($dentist, $entry));
        $this->assertTrue($this->policy->cancel($dentist, $entry));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $entry));
        $this->assertFalse($this->policy->complete($receptionist, $entry));
        $this->assertFalse($this->policy->cancel($receptionist, $entry));
    }

    /**
     * Resolved decision #4 (design draft §19) — no dentist-ownership/IDOR restriction, unlike
     * `AppointmentPolicy::start()/complete()`. Any dentist may act on any patient's entry.
     */
    public function test_any_dentist_can_act_on_an_entry_recorded_by_another_dentist(): void
    {
        $recordingDentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();

        $entry = DentalChartEntry::factory()->create(['dentist_id' => $recordingDentist->id]);

        $this->assertTrue($this->policy->update($otherDentist, $entry));
        $this->assertTrue($this->policy->complete($otherDentist, $entry));
        $this->assertTrue($this->policy->cancel($otherDentist, $entry));
    }

    public function test_only_admin_can_delete_an_entry(): void
    {
        $entry = DentalChartEntry::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $entry));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $entry));
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'receptionist']), $entry));
    }

    public function test_any_role_can_view_entries(): void
    {
        $entry = DentalChartEntry::factory()->create();

        $this->assertTrue($this->policy->viewAny(User::factory()->create(['role' => 'receptionist'])));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'receptionist']), $entry));
    }
}
