<?php

namespace Tests\Unit\Policies;

use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Models\User;
use App\Policies\MedicalHistoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `MedicalHistoryPolicy` is the first policy in this codebase registered against more than one
 * model — `Gate::policy(PatientAllergy::class, ...)` / `PatientMedicalCondition::class` /
 * `PatientMedication::class` all point at this one class in `AppServiceProvider::boot()`, since
 * Laravel's naming-convention auto-discovery only ever maps one policy per model (see
 * `docs/decisions.md`'s 2026-08-08 entry for the full rationale). That registration is exactly the
 * part a future refactor of `AppServiceProvider` could silently break for one model and not the
 * others, so every group below is duplicated three times on purpose — once per model, both via
 * direct policy-method calls (the authorization *rules*) and via the real `Gate`/`User::can()`
 * facade (the *registration* those rules are reached through) — rather than asserting the shared
 * logic once and trusting all three registrations equally.
 */
class MedicalHistoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MedicalHistoryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MedicalHistoryPolicy;
    }

    // ---- Direct policy-method calls, exhaustive per model -----------------------------------

    public function test_allergy_viewAny_and_view_are_open_to_all_roles(): void
    {
        $allergy = PatientAllergy::factory()->create();

        foreach (['admin', 'dentist', 'receptionist'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->assertTrue($this->policy->viewAny($actor), "viewAny should be true for {$role}");
            $this->assertTrue($this->policy->view($actor, $allergy), "view should be true for {$role}");
        }
    }

    public function test_allergy_create_update_delete_are_admin_and_dentist_only(): void
    {
        $allergy = PatientAllergy::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $allergy));
        $this->assertTrue($this->policy->delete($admin, $allergy));

        $this->assertTrue($this->policy->create($dentist));
        $this->assertTrue($this->policy->update($dentist, $allergy));
        $this->assertTrue($this->policy->delete($dentist, $allergy));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $allergy));
        $this->assertFalse($this->policy->delete($receptionist, $allergy));
    }

    public function test_medical_condition_viewAny_and_view_are_open_to_all_roles(): void
    {
        $condition = PatientMedicalCondition::factory()->create();

        foreach (['admin', 'dentist', 'receptionist'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->assertTrue($this->policy->viewAny($actor), "viewAny should be true for {$role}");
            $this->assertTrue($this->policy->view($actor, $condition), "view should be true for {$role}");
        }
    }

    public function test_medical_condition_create_update_delete_are_admin_and_dentist_only(): void
    {
        $condition = PatientMedicalCondition::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $condition));
        $this->assertTrue($this->policy->delete($admin, $condition));

        $this->assertTrue($this->policy->create($dentist));
        $this->assertTrue($this->policy->update($dentist, $condition));
        $this->assertTrue($this->policy->delete($dentist, $condition));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $condition));
        $this->assertFalse($this->policy->delete($receptionist, $condition));
    }

    public function test_medication_viewAny_and_view_are_open_to_all_roles(): void
    {
        $medication = PatientMedication::factory()->create();

        foreach (['admin', 'dentist', 'receptionist'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->assertTrue($this->policy->viewAny($actor), "viewAny should be true for {$role}");
            $this->assertTrue($this->policy->view($actor, $medication), "view should be true for {$role}");
        }
    }

    public function test_medication_create_update_delete_are_admin_and_dentist_only(): void
    {
        $medication = PatientMedication::factory()->create();
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $medication));
        $this->assertTrue($this->policy->delete($admin, $medication));

        $this->assertTrue($this->policy->create($dentist));
        $this->assertTrue($this->policy->update($dentist, $medication));
        $this->assertTrue($this->policy->delete($dentist, $medication));

        $this->assertFalse($this->policy->create($receptionist));
        $this->assertFalse($this->policy->update($receptionist, $medication));
        $this->assertFalse($this->policy->delete($receptionist, $medication));
    }

    // ---- Gate::policy() registration itself, per model ---------------------------------------
    //
    // The groups above test `MedicalHistoryPolicy`'s rules in isolation; these test that Laravel's
    // Gate actually resolves each of the three models to *this* policy class in the first place —
    // the specific thing `Gate::policy()` in `AppServiceProvider::boot()` is responsible for, and
    // the part that's new/fragile about this multi-model-single-policy pattern.

    public function test_gate_resolves_patient_allergy_to_medical_history_policy(): void
    {
        $allergy = PatientAllergy::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($admin->can('viewAny', PatientAllergy::class));
        $this->assertTrue($admin->can('create', PatientAllergy::class));
        $this->assertTrue($admin->can('update', $allergy));
        $this->assertTrue($admin->can('delete', $allergy));
        $this->assertFalse($receptionist->can('create', PatientAllergy::class));
    }

    public function test_gate_resolves_patient_medical_condition_to_medical_history_policy(): void
    {
        $condition = PatientMedicalCondition::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($admin->can('viewAny', PatientMedicalCondition::class));
        $this->assertTrue($admin->can('create', PatientMedicalCondition::class));
        $this->assertTrue($admin->can('update', $condition));
        $this->assertTrue($admin->can('delete', $condition));
        $this->assertFalse($receptionist->can('create', PatientMedicalCondition::class));
    }

    public function test_gate_resolves_patient_medication_to_medical_history_policy(): void
    {
        $medication = PatientMedication::factory()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue($admin->can('viewAny', PatientMedication::class));
        $this->assertTrue($admin->can('create', PatientMedication::class));
        $this->assertTrue($admin->can('update', $medication));
        $this->assertTrue($admin->can('delete', $medication));
        $this->assertFalse($receptionist->can('create', PatientMedication::class));
    }
}
