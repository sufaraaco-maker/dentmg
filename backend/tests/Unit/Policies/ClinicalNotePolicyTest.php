<?php

namespace Tests\Unit\Policies;

use App\Models\ClinicalNote;
use App\Models\User;
use App\Policies\ClinicalNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    private ClinicalNotePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ClinicalNotePolicy;
    }

    public function test_admin_and_dentist_can_view_create_update_sign_and_addend(): void
    {
        $note = ClinicalNote::factory()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->dentist()->create()] as $actor) {
            $this->assertTrue($this->policy->viewAny($actor));
            $this->assertTrue($this->policy->view($actor, $note));
            $this->assertTrue($this->policy->create($actor));
            $this->assertTrue($this->policy->update($actor, $note));
            $this->assertTrue($this->policy->sign($actor, $note));
            $this->assertTrue($this->policy->addAddendum($actor, $note));
        }
    }

    /**
     * Design doc §10/§15 Decision D: a deliberate divergence from Dental Chart/Treatment Plans,
     * which both grant receptionist read access — Clinical Notes grants receptionists nothing at
     * all, not even view.
     */
    public function test_receptionist_has_no_access_at_all(): void
    {
        $note = ClinicalNote::factory()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertFalse($this->policy->viewAny($receptionist));
        $this->assertFalse($this->policy->view($receptionist, $note));
        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $note));
        $this->assertFalse($this->policy->sign($receptionist, $note));
        $this->assertFalse($this->policy->addAddendum($receptionist, $note));
        $this->assertFalse($this->policy->delete($receptionist, $note));
    }

    public function test_only_admin_can_delete_a_clinical_note(): void
    {
        $note = ClinicalNote::factory()->create();

        $this->assertTrue($this->policy->delete(User::factory()->admin()->create(), $note));
        $this->assertFalse($this->policy->delete(User::factory()->dentist()->create(), $note));
    }
}
