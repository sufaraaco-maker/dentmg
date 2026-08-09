<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\TreatmentPlanItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard 2.0 (design doc `docs/modules/dashboard-2.0-design.md`) splits the former single
 * `summary()` payload in two: operational data here, open to every role, and `financialSummary()`
 * — a separate method backing a separately-gated endpoint (`view-financial-reports`, admin-only,
 * §3 of the design doc) — for revenue-bearing data. `monthly_revenue` previously lived in
 * `summary()` with no gate at all, the same figure `reports/collections` already restricted to
 * admins; that inconsistency is fixed by the split, not preserved.
 */
class DashboardService
{
    /** Widget-sized, not a full report — a full drill-down list is out of scope (design doc §7). */
    private const UNSCHEDULED_TREATMENT_LIMIT = 5;

    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Operational stat cards for the main dashboard — open to every role.
     * Falls back to 0 for modules that are not implemented yet.
     */
    public function summary(): array
    {
        return [
            'total_patients' => $this->countIfExists('patients'),
            'today_appointments' => $this->todayAppointments(),
            'unscheduled_accepted_treatment' => $this->unscheduledAcceptedTreatment(),
        ];
    }

    /**
     * Financial stat cards — admin-only (`view-financial-reports`, enforced by the Form Request,
     * not here). Each figure is a thin wrapper around an existing `ReportService` method; no new
     * aggregation logic beyond the period-over-period trend delta itself (design doc §1.2).
     */
    public function financialSummary(): array
    {
        $collectionsTrend = $this->collectionsTrend();

        return [
            'monthly_revenue' => $collectionsTrend['current'],
            'production_trend' => $this->productionTrend(),
            'collections_trend' => $collectionsTrend,
            'ar_aging' => $this->reportService->arAging()['summary'],
        ];
    }

    /**
     * This calendar month vs. last calendar month, both read from `ReportService::production()`
     * — no new production aggregation, only the trend delta (design doc §1.2).
     *
     * @return array{current: string, previous: string, change_pct: float|null}
     */
    private function productionTrend(): array
    {
        return $this->monthOverMonthTrend(
            fn (string $from, string $to) => $this->reportService->production($from, $to)['summary']['total'],
        );
    }

    /**
     * Same shape as `productionTrend()`, reading `ReportService::collections()` instead. This is
     * also what `monthly_revenue`'s headline figure is: `collections_trend.current`.
     *
     * @return array{current: string, previous: string, change_pct: float|null}
     */
    private function collectionsTrend(): array
    {
        return $this->monthOverMonthTrend(
            fn (string $from, string $to) => $this->reportService->collections($from, $to)['summary']['total'],
        );
    }

    /**
     * @param  callable(string, string): string  $totalFor  Given a date-range, returns a decimal-string total.
     * @return array{current: string, previous: string, change_pct: float|null}
     */
    private function monthOverMonthTrend(callable $totalFor): array
    {
        $today = Date::today();
        $currentStart = $today->copy()->startOfMonth();
        $currentEnd = $today->copy()->endOfMonth();
        $previousStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $today->copy()->subMonthNoOverflow()->endOfMonth();

        $current = $totalFor($currentStart->toDateString(), $currentEnd->toDateString());
        $previous = $totalFor($previousStart->toDateString(), $previousEnd->toDateString());

        return [
            'current' => $current,
            'previous' => $previous,
            'change_pct' => $this->changePct($current, $previous),
        ];
    }

    /**
     * `null`, never `0`, when the previous period had no activity — a percentage computed against
     * zero is fabricated, not real (design doc §1.2/§3.1's "no fabricated trend" rule).
     */
    private function changePct(string $current, string $previous): ?float
    {
        if (bccomp($previous, '0', 2) === 0) {
            return null;
        }

        $ratio = bcdiv(bcsub($current, $previous, 6), $previous, 6);

        return round(((float) $ratio) * 100, 2);
    }

    /**
     * "Accepted but not yet scheduled" treatment (design doc §0/§1.1): the item-status enum
     * deliberately never stores a "scheduled" state — it's derived from `appointment_id` pointing
     * to a non-cancelled Appointment (`TreatmentPlanItemStatus` docblock). So "unscheduled" means
     * either no appointment linked at all, or the linked one was itself cancelled.
     *
     * @return array{count: int, items: list<array{patient: string, patient_id: string, treatment_plan_id: string, item_description: string, accepted_at: ?string}>}
     */
    private function unscheduledAcceptedTreatment(): array
    {
        $query = TreatmentPlanItem::query()
            ->whereHas('treatmentPlan', fn (Builder $q) => $q->where('status', TreatmentPlanStatus::Accepted->value))
            ->where('status', TreatmentPlanItemStatus::Planned->value)
            ->where(function (Builder $q) {
                $q->whereNull('appointment_id')
                    ->orWhereHas('appointment', fn (Builder $q) => $q->where('status', AppointmentStatus::Cancelled->value));
            });

        $count = (clone $query)->count();

        $items = $query
            ->with('treatmentPlan.patient')
            ->orderBy('created_at')
            ->limit(self::UNSCHEDULED_TREATMENT_LIMIT)
            ->get()
            ->map(fn (TreatmentPlanItem $item) => [
                'patient' => $item->treatmentPlan->patient->full_name,
                'patient_id' => $item->treatmentPlan->patient_id,
                'treatment_plan_id' => $item->treatment_plan_id,
                'item_description' => $item->procedure_name,
                'accepted_at' => $item->treatmentPlan->accepted_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ['count' => $count, 'items' => $items];
    }

    private function countIfExists(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    /**
     * Full-day bounds, not a bare Y-m-d string (same convention as ReportService's date-range
     * queries): `whereBetween` compares as strings on some drivers, so a bare upper bound would
     * silently exclude any row whose stored value carries a time-of-day suffix.
     */
    private function todayAppointments(): int
    {
        if (! Schema::hasTable('appointments')) {
            return 0;
        }

        $today = Date::today()->toDateString();

        return DB::table('appointments')
            ->whereBetween('start_at', ["{$today} 00:00:00", "{$today} 23:59:59"])
            ->count();
    }
}
