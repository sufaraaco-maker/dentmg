<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\PatientAllergy;
use App\Models\PatientDocument;
use App\Models\PatientImage;
use App\Models\TreatmentPlan;
use App\Models\User;

/**
 * Design doc §7/§16 decision 3: not a conventional flat-role policy. `viewAny` just gates the
 * endpoint itself (matches every other module's shape); the real per-category filtering happens
 * in `PatientActivityController::index()` via `allowedCategories()`, which checks each category's
 * *real* owning policy at the class level — one `can()` call per category per request, never per
 * row — so Timeline's permissions can never drift out of sync with the module they're aggregating.
 *
 * One category per real policy, never a category spanning two policies with different rules —
 * this is exactly the bug found and fixed pre-implementation (design doc §5's correction note):
 * `clinical` originally covered TreatmentPlan/ClinicalNote/Medical History despite
 * ClinicalNotePolicy::viewAny() being Admin/Dentist-only while the other two are all-staff.
 */
class PatientActivityPolicy
{
    /** @var array<string, class-string> */
    public const CATEGORY_SUBJECT_MAP = [
        'appointments' => Appointment::class,
        'treatment_plans' => TreatmentPlan::class,
        'clinical_notes' => ClinicalNote::class,
        'billing' => Invoice::class,
        'medical_history' => PatientAllergy::class,
        'laboratory' => LabCase::class,
        'imaging' => PatientImage::class,
        'documents' => PatientDocument::class,
    ];

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('patient_activity.view');
    }

    /**
     * @return list<string>
     */
    public static function allowedCategories(User $actor): array
    {
        return array_values(array_filter(
            array_keys(self::CATEGORY_SUBJECT_MAP),
            fn (string $category) => $actor->can('viewAny', self::CATEGORY_SUBJECT_MAP[$category]),
        ));
    }
}
