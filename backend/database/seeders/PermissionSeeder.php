<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Phase 4 (Advanced Permissions & Audit) design doc §1.1/§1.2 — the permission catalog and its
 * default role assignment. Every entry below is derived 1:1 from a direct, full read of the
 * matching Policy class as it exists *before* Phase 4 Step 2 touches it, so this seeder is the
 * proof that Step 1 introduces zero effective permission change: every role keeps exactly the
 * access it has today, just now as data instead of hardcoded `UserRole::X` comparisons.
 *
 * Methodology: Policy methods sharing an identical role-set within one Policy class are collapsed
 * into one permission key (e.g. AppointmentPolicy's create/update/cancel/markNoShow/confirm/
 * checkIn all check `[Admin, Receptionist]` identically, so they share `appointments.manage`)
 * rather than one key per method — this keeps the matrix a manageable ~68 rows instead of ~150+,
 * while still preserving every distinct authorization boundary the app actually draws. Identity-
 * based checks that are not role checks (e.g. `$actor->is($appointment->dentist)`,
 * `$actor->id === $log->user_id`) are NOT represented here — they stay hardcoded in the Policy as
 * an additional `||`/`&&` condition alongside the permission check (Step 2's job, not Step 1's).
 *
 * Two capabilities this phase introduces — `manage-permissions` (this matrix itself) and
 * `view-audit-logs` (the new general Audit Log viewer, Step 3) — are deliberately NOT catalog
 * entries. They're checked via a hardcoded `isAdmin()` Gate (AppServiceProvider, matching the
 * existing `view-financial-reports` pattern) instead of going through this matrix, so there is no
 * possible state in which an admin's own ability to fix the matrix depends on the matrix already
 * being correct (design doc §1.4).
 *
 * Explicitly out of scope for this phase: the two pre-existing Report Gates
 * (`view-financial-reports`, `view-operational-reports`) stay hardcoded, unconverted — the
 * approved scope was "the 27 Policy classes," not these two Gates. Flagged for a future decision,
 * not silently folded in.
 */
class PermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    private const ADMIN = UserRole::Admin;

    private const DENTIST = UserRole::Dentist;

    private const RECEPTIONIST = UserRole::Receptionist;

    public function run(): void
    {
        foreach ($this->catalog() as $entry) {
            $permission = Permission::updateOrCreate(
                ['key' => $entry['key']],
                [
                    'group' => $entry['group'],
                    'description' => $entry['description'],
                ],
            );

            foreach ($entry['roles'] as $role) {
                RolePermission::firstOrCreate([
                    'role' => $role->value,
                    'permission_key' => $permission->key,
                ]);
            }
        }

        RolePermission::flushCache();
    }

    /**
     * @return list<array{key: string, group: string, description: string, roles: list<UserRole>}>
     */
    private function catalog(): array
    {
        $admin = self::ADMIN;
        $dentist = self::DENTIST;
        $receptionist = self::RECEPTIONIST;

        return [
            // AppointmentPolicy.php
            ['key' => 'appointments.view', 'group' => 'appointments', 'description' => 'View appointments', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'appointments.manage', 'group' => 'appointments', 'description' => 'Create/update/cancel/confirm/check-in appointments (a treating dentist may still start/complete their own regardless)', 'roles' => [$admin, $receptionist]],
            ['key' => 'appointments.delete', 'group' => 'appointments', 'description' => 'Delete appointments', 'roles' => [$admin]],

            // AppointmentTypePolicy.php
            ['key' => 'appointment_types.view', 'group' => 'appointment_types', 'description' => 'View appointment types', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'appointment_types.manage', 'group' => 'appointment_types', 'description' => 'Create/update/delete appointment types', 'roles' => [$admin]],

            // BillingSettingPolicy.php
            ['key' => 'billing_settings.manage', 'group' => 'billing_settings', 'description' => 'View/update billing settings', 'roles' => [$admin]],

            // ClinicSettingPolicy.php
            ['key' => 'clinic_settings.view', 'group' => 'clinic_settings', 'description' => 'View clinic settings', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'clinic_settings.manage', 'group' => 'clinic_settings', 'description' => 'Update clinic settings', 'roles' => [$admin]],

            // ClinicalNotePolicy.php
            ['key' => 'clinical_notes.view', 'group' => 'clinical_notes', 'description' => 'View clinical notes', 'roles' => [$admin, $dentist]],
            ['key' => 'clinical_notes.manage', 'group' => 'clinical_notes', 'description' => 'Create/update/sign/addend clinical notes', 'roles' => [$admin, $dentist]],
            ['key' => 'clinical_notes.delete', 'group' => 'clinical_notes', 'description' => 'Delete clinical notes', 'roles' => [$admin]],

            // DentalChartEntryPolicy.php
            ['key' => 'dental_chart.view', 'group' => 'dental_chart', 'description' => 'View dental chart entries', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'dental_chart.manage', 'group' => 'dental_chart', 'description' => 'Create/update/complete/cancel dental chart entries', 'roles' => [$admin, $dentist]],
            ['key' => 'dental_chart.delete', 'group' => 'dental_chart', 'description' => 'Delete dental chart entries', 'roles' => [$admin]],

            // DentalConditionPolicy.php
            ['key' => 'dental_conditions.view', 'group' => 'dental_conditions', 'description' => 'View the dental condition catalog', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'dental_conditions.manage', 'group' => 'dental_conditions', 'description' => 'Create/update/delete dental conditions', 'roles' => [$admin]],

            // DentistTimeOffPolicy.php — self-service (`$actor->is($dentist)`) stays hardcoded
            ['key' => 'dentist_time_off.view', 'group' => 'dentist_time_off', 'description' => 'View dentist time-off', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'dentist_time_off.manage_any', 'group' => 'dentist_time_off', 'description' => "Create/delete any dentist's time-off (a dentist may still manage their own regardless)", 'roles' => [$admin]],

            // DentistWorkingHourPolicy.php
            ['key' => 'dentist_working_hours.view', 'group' => 'dentist_working_hours', 'description' => 'View dentist working hours', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'dentist_working_hours.manage', 'group' => 'dentist_working_hours', 'description' => 'Create/delete dentist working hours', 'roles' => [$admin]],

            // InvoiceItemPolicy.php
            ['key' => 'invoice_items.view', 'group' => 'invoice_items', 'description' => 'View invoice line items', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'invoice_items.manage', 'group' => 'invoice_items', 'description' => 'Create/update/delete invoice line items', 'roles' => [$admin, $receptionist]],

            // InvoicePolicy.php
            ['key' => 'invoices.view', 'group' => 'invoices', 'description' => 'View invoices', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'invoices.manage', 'group' => 'invoices', 'description' => 'Create/update/issue/void invoices', 'roles' => [$admin, $receptionist]],
            ['key' => 'invoices.delete', 'group' => 'invoices', 'description' => 'Delete invoices', 'roles' => [$admin]],

            // LabCasePolicy.php
            ['key' => 'lab_cases.view', 'group' => 'lab_cases', 'description' => 'View lab cases', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'lab_cases.prescribe', 'group' => 'lab_cases', 'description' => 'Create/update/cancel lab cases (clinical prescription)', 'roles' => [$admin, $dentist]],
            ['key' => 'lab_cases.process', 'group' => 'lab_cases', 'description' => 'Send/receive/quality-check lab cases (logistics)', 'roles' => [$admin, $receptionist]],
            ['key' => 'lab_cases.delete', 'group' => 'lab_cases', 'description' => 'Delete lab cases', 'roles' => [$admin]],

            // LabPolicy.php
            ['key' => 'labs.view', 'group' => 'labs', 'description' => 'View the lab vendor catalog', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'labs.manage', 'group' => 'labs', 'description' => 'Create/update/delete lab vendors', 'roles' => [$admin]],

            // MedicalHistoryPolicy.php (PatientAllergy/PatientMedicalCondition/PatientMedication)
            ['key' => 'medical_history.view', 'group' => 'medical_history', 'description' => 'View allergies/conditions/medications', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'medical_history.manage', 'group' => 'medical_history', 'description' => 'Create/update/delete allergies/conditions/medications', 'roles' => [$admin, $dentist]],

            // PatientActivityPolicy.php — per-category enforcement composes other resources'
            // .view permissions via $actor->can(), so no further catalog entries are needed here.
            ['key' => 'patient_activity.view', 'group' => 'patient_activity', 'description' => 'View a patient timeline/activity feed (categories are further filtered by each category\'s own .view permission)', 'roles' => [$admin, $dentist, $receptionist]],

            // PatientDocumentPolicy.php
            ['key' => 'patient_documents.view', 'group' => 'patient_documents', 'description' => 'View patient documents', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'patient_documents.manage', 'group' => 'patient_documents', 'description' => 'Upload/update patient documents', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'patient_documents.delete', 'group' => 'patient_documents', 'description' => 'Delete patient documents', 'roles' => [$admin]],

            // PatientImagePolicy.php
            ['key' => 'patient_images.view', 'group' => 'patient_images', 'description' => 'View patient images', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'patient_images.manage', 'group' => 'patient_images', 'description' => 'Upload/update patient images', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'patient_images.delete', 'group' => 'patient_images', 'description' => 'Delete patient images', 'roles' => [$admin]],

            // PatientPolicy.php
            ['key' => 'patients.view', 'group' => 'patients', 'description' => 'View patients', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'patients.manage', 'group' => 'patients', 'description' => 'Create/update patients', 'roles' => [$admin, $receptionist]],
            ['key' => 'patients.delete', 'group' => 'patients', 'description' => 'Delete patients', 'roles' => [$admin]],
            ['key' => 'patients.view_audit_logs', 'group' => 'patients', 'description' => "View a patient's audit log", 'roles' => [$admin]],

            // PaymentPolicy.php
            ['key' => 'payments.view', 'group' => 'payments', 'description' => 'View payments', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'payments.manage', 'group' => 'payments', 'description' => 'Create/update/apply/refund payments', 'roles' => [$admin, $receptionist]],
            ['key' => 'payments.delete', 'group' => 'payments', 'description' => 'Delete payments', 'roles' => [$admin]],

            // PurchaseOrderPolicy.php
            ['key' => 'purchase_orders.view', 'group' => 'purchase_orders', 'description' => 'View purchase orders', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'purchase_orders.manage', 'group' => 'purchase_orders', 'description' => 'Create/update/place/receive/cancel purchase orders', 'roles' => [$admin, $receptionist]],
            ['key' => 'purchase_orders.delete', 'group' => 'purchase_orders', 'description' => 'Delete purchase orders', 'roles' => [$admin]],

            // StockMovementPolicy.php
            ['key' => 'stock_movements.view', 'group' => 'stock_movements', 'description' => "View a supply's stock movement ledger", 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'stock_movements.create', 'group' => 'stock_movements', 'description' => 'Record a manual stock movement', 'roles' => [$admin, $dentist, $receptionist]],

            // SupplierPolicy.php
            ['key' => 'suppliers.view', 'group' => 'suppliers', 'description' => 'View suppliers', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'suppliers.manage', 'group' => 'suppliers', 'description' => 'Create/update/delete suppliers', 'roles' => [$admin]],

            // SupplyCategoryPolicy.php
            ['key' => 'supply_categories.view', 'group' => 'supply_categories', 'description' => 'View supply categories', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'supply_categories.manage', 'group' => 'supply_categories', 'description' => 'Create/update/delete supply categories', 'roles' => [$admin]],

            // SupplyPolicy.php
            ['key' => 'supplies.view', 'group' => 'supplies', 'description' => 'View the supply catalog', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'supplies.manage', 'group' => 'supplies', 'description' => 'Create/update/delete supplies', 'roles' => [$admin, $receptionist]],

            // TreatmentPlanItemPolicy.php
            ['key' => 'treatment_plan_items.view', 'group' => 'treatment_plan_items', 'description' => 'View treatment plan items', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'treatment_plan_items.manage', 'group' => 'treatment_plan_items', 'description' => 'Create/update/complete/cancel treatment plan items', 'roles' => [$admin, $dentist]],
            ['key' => 'treatment_plan_items.delete', 'group' => 'treatment_plan_items', 'description' => 'Delete treatment plan items', 'roles' => [$admin]],

            // TreatmentPlanPolicy.php
            ['key' => 'treatment_plans.view', 'group' => 'treatment_plans', 'description' => 'View treatment plans', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'treatment_plans.manage', 'group' => 'treatment_plans', 'description' => 'Create/update/present/accept/reject/start/complete/cancel treatment plans, and create revisions', 'roles' => [$admin, $dentist]],
            ['key' => 'treatment_plans.delete', 'group' => 'treatment_plans', 'description' => 'Delete treatment plans', 'roles' => [$admin]],

            // UserPolicy.php — `users.manage` is protected from revocation from Admin
            // (UpdateRolePermissionsRequest), preventing a self-lockout scenario.
            ['key' => 'users.view', 'group' => 'users', 'description' => 'View other users', 'roles' => [$admin, $dentist, $receptionist]],
            ['key' => 'users.manage', 'group' => 'users', 'description' => 'Create/update/delete user accounts, including role assignment', 'roles' => [$admin]],
        ];
    }
}
