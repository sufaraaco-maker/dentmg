<?php

namespace App\Policies;

use App\Models\DentalChartEntry;
use App\Models\User;

class DentalChartEntryPolicy
{
    /**
     * Clinic-wide read visibility for all staff, including receptionists (design draft §19 —
     * front desk needs charting context for scheduling).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('dental_chart.view');
    }

    public function view(User $actor, DentalChartEntry $dentalChartEntry): bool
    {
        return $actor->hasPermission('dental_chart.view');
    }

    /**
     * Charting is clinical work (admin, dentist). No per-record ownership/IDOR check — any
     * dentist may chart for any patient, resolved decision #4 (design draft §19): DentalSuite has
     * no "assigned/primary dentist per patient" concept, so a `$actor->is($entry->dentist)` check
     * like `AppointmentPolicy::start()/complete()` isn't applicable here.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('dental_chart.manage');
    }

    public function update(User $actor, DentalChartEntry $dentalChartEntry): bool
    {
        return $actor->hasPermission('dental_chart.manage');
    }

    public function complete(User $actor, DentalChartEntry $dentalChartEntry): bool
    {
        return $actor->hasPermission('dental_chart.manage');
    }

    public function cancel(User $actor, DentalChartEntry $dentalChartEntry): bool
    {
        return $actor->hasPermission('dental_chart.manage');
    }

    /**
     * Delete is gated tighter than the clinical transitions (admin-only) — a data-correction
     * action, not a clinical one (design draft §19).
     */
    public function delete(User $actor, DentalChartEntry $dentalChartEntry): bool
    {
        return $actor->hasPermission('dental_chart.delete');
    }
}
