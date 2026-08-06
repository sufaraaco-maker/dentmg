<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TreatmentPlanItemStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every method here returns a plain ['summary' => ..., 'rows' => [...]] array — no HTTP, CSV, or
 * view concerns (Reports design doc, Approval Log). ReportController formats the HTTP response
 * (JSON or CSV) from these same arrays, and DashboardService calls collections() directly for
 * `monthly_revenue` instead of reimplementing the aggregation (design doc §1/§7, Approval Log
 * item 5).
 *
 * No new tables: every report is a live query over data other modules already own (design doc
 * §3). Every method takes its date range/filters as explicit parameters rather than assuming a
 * single implicit scope, so a future per-clinic filter is an additive `WHERE`, not a reshape
 * (design doc's multi-tenant-readiness requirement).
 */
class ReportService
{
    /**
     * @return array{summary: array{total: string, by_dentist: list<array{dentist: string, amount: string, count: int}>}, rows: list<array{invoice_number: string, patient: string, date: ?string, description: string, dentist: string, amount: string}>}
     */
    public function production(string $dateFrom, string $dateTo, ?string $dentistId = null): array
    {
        // Full-day bounds, not bare Y-m-d strings: whereBetween compares as strings on some drivers,
        // and a stored value with any time-of-day suffix sorts after a bare date upper bound.
        $query = InvoiceItem::query()
            ->where('kind', InvoiceItemKind::Charge)
            ->whereHas('invoice', function (Builder $q) use ($dateFrom, $dateTo) {
                $q->where('status', '!=', InvoiceStatus::Draft)
                    ->whereBetween('issue_date', ["{$dateFrom} 00:00:00", "{$dateTo} 23:59:59"]);
            })
            ->with(['invoice.patient', 'treatmentPlanItem.treatmentPlan.dentist']);

        if ($dentistId !== null) {
            $query->whereHas(
                'treatmentPlanItem.treatmentPlan',
                fn (Builder $q) => $q->where('dentist_id', $dentistId)
            );
        }

        /** @var Collection<int, InvoiceItem> $items */
        $items = $query->get();

        $dentistName = fn (InvoiceItem $item): string => $item->treatmentPlanItem?->treatmentPlan?->dentist->name ?? 'Unassigned';

        $rows = $items->map(fn (InvoiceItem $item) => [
            'invoice_number' => $item->invoice->invoice_number,
            'patient' => $item->invoice->patient->full_name,
            'date' => $item->invoice->issue_date?->toDateString(),
            'description' => $item->description,
            'dentist' => $dentistName($item),
            'amount' => $item->amount,
        ])->values();

        $byDentist = $items->groupBy($dentistName)
            ->map(fn (Collection $group, string $name) => [
                'dentist' => $name,
                'amount' => $this->sumAmounts($group),
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => $this->sumAmounts($items),
                'by_dentist' => $byDentist,
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * @return array{summary: array{total: string, by_method: list<array{method: string, amount: string, count: int}>}, rows: list<array{date: string, patient: string, invoice_number: ?string, method: string, amount: string}>}
     */
    public function collections(string $dateFrom, string $dateTo, ?PaymentMethod $method = null): array
    {
        $query = Payment::query()
            ->whereBetween('received_at', ["{$dateFrom} 00:00:00", "{$dateTo} 23:59:59"])
            ->with(['patient', 'invoice']);

        if ($method !== null) {
            $query->where('method', $method);
        }

        // Most-recent-first: a real admin viewing this report expects recent activity at the top,
        // not whatever order Postgres happens to return without an explicit ORDER BY (undefined,
        // not guaranteed insertion order). Found while diagnosing a genuine E2E flake
        // (`reports.spec.ts`) that turned out to trace back to this real gap, not a test-only issue.
        // `received_at` is deliberately date-only (`Payment::$casts`, `PaymentService::create()`
        // defaults it to `now()->toDateString()`) — every payment recorded the same calendar day
        // ties under that column alone, so `created_at` (a real timestamp) breaks the tie, still
        // most-recent-first within a day.
        /** @var Collection<int, Payment> $payments */
        $payments = $query->orderByDesc('received_at')->orderByDesc('created_at')->get();

        $rows = $payments->map(fn (Payment $payment) => [
            'date' => $payment->received_at->toDateString(),
            'patient' => $payment->patient->full_name,
            'invoice_number' => $payment->invoice?->invoice_number,
            'method' => $payment->method->value,
            'amount' => (string) $payment->amount,
        ])->values();

        $byMethod = $payments->groupBy(fn (Payment $p) => $p->method->value)
            ->map(fn (Collection $group, string $methodValue) => [
                'method' => $methodValue,
                'amount' => $this->sumPaymentAmounts($group),
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => $this->sumPaymentAmounts($payments),
                'by_method' => $byMethod,
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * Point-in-time snapshot ("as of today"), not a date-range query — matches every competitor's
     * own framing of an aging report (design doc §4.3).
     *
     * @return array{summary: array{total: string, buckets: array<string, string>}, rows: list<array<string, mixed>>}
     */
    public function arAging(): array
    {
        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->with(['patient', 'items', 'payments'])
            ->get()
            ->filter(fn (Invoice $invoice) => bccomp($this->invoiceBalanceDue($invoice), '0.00', 2) > 0)
            ->values();

        $today = CarbonImmutable::today();
        $buckets = ['current' => '0.00', '1_30' => '0.00', '31_60' => '0.00', '61_90' => '0.00', '90_plus' => '0.00'];
        $total = '0.00';

        $rows = $invoices->map(function (Invoice $invoice) use ($today, &$buckets, &$total) {
            $balance = $this->invoiceBalanceDue($invoice);
            $daysOverdue = $invoice->due_date
                ? (int) $invoice->due_date->startOfDay()->diffInDays($today, false)
                : 0;
            $bucket = $this->agingBucket($daysOverdue);

            $buckets[$bucket] = bcadd($buckets[$bucket], $balance, 2);
            $total = bcadd($total, $balance, 2);

            return [
                'patient' => $invoice->patient->full_name,
                'invoice_number' => $invoice->invoice_number,
                'due_date' => $invoice->due_date?->toDateString(),
                'days_overdue' => max($daysOverdue, 0),
                'balance_due' => $balance,
            ];
        })->values();

        return [
            'summary' => [
                'total' => $total,
                'buckets' => $buckets,
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * @return array{summary: array{total: int, by_status: list<array{status: string, count: int}>, no_show_rate: float, cancellation_rate: float}, rows: list<array{date: string, patient: string, dentist: string, type: ?string, status: string}>}
     */
    public function appointmentAnalytics(string $dateFrom, string $dateTo, ?string $dentistId = null): array
    {
        $query = Appointment::query()
            ->whereBetween('start_at', ["{$dateFrom} 00:00:00", "{$dateTo} 23:59:59"])
            ->with(['patient', 'dentist', 'appointmentType']);

        if ($dentistId !== null) {
            $query->forDentist($dentistId);
        }

        /** @var Collection<int, Appointment> $appointments */
        $appointments = $query->get();

        $total = $appointments->count();
        $noShowCount = $appointments->where('status', AppointmentStatus::NoShow)->count();
        $cancelledCount = $appointments->where('status', AppointmentStatus::Cancelled)->count();

        $byStatus = $appointments->groupBy(fn (Appointment $a) => $a->status->value)
            ->map(fn (Collection $group, string $status) => ['status' => $status, 'count' => $group->count()])
            ->values()
            ->all();

        $rows = $appointments->map(fn (Appointment $a) => [
            'date' => $a->start_at->toDateString(),
            'patient' => $a->patient->full_name,
            'dentist' => $a->dentist->name,
            'type' => $a->appointmentType?->name,
            'status' => $a->status->value,
        ])->values();

        return [
            'summary' => [
                'total' => $total,
                'by_status' => $byStatus,
                'no_show_rate' => $total > 0 ? round($noShowCount / $total * 100, 1) : 0.0,
                'cancellation_rate' => $total > 0 ? round($cancelledCount / $total * 100, 1) : 0.0,
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * "Accepted"/"rejected" are read from `accepted_at`/`rejected_at` rather than the plan's
     * *current* status, so a plan that has since moved on to InProgress/Completed still counts as
     * accepted (its acceptance already happened and is a permanent fact even though the current
     * status has moved past it).
     *
     * @return array{summary: array{presented: int, accepted: int, rejected: int, acceptance_rate: float, accepted_value: string}, rows: list<array<string, mixed>>}
     */
    public function treatmentPlanAcceptance(string $dateFrom, string $dateTo, ?string $dentistId = null): array
    {
        $query = TreatmentPlan::query()
            ->whereNotNull('presented_at')
            ->whereBetween('presented_at', ["{$dateFrom} 00:00:00", "{$dateTo} 23:59:59"])
            ->with(['patient', 'dentist', 'items']);

        if ($dentistId !== null) {
            $query->where('dentist_id', $dentistId);
        }

        /** @var Collection<int, TreatmentPlan> $plans */
        $plans = $query->get();

        $presented = $plans->count();
        $acceptedPlans = $plans->whereNotNull('accepted_at')->values();
        $rejectedCount = $plans->whereNotNull('rejected_at')->count();

        $rows = $plans->map(fn (TreatmentPlan $plan) => [
            'patient' => $plan->patient->full_name,
            'dentist' => $plan->dentist?->name,
            'presented_at' => $plan->presented_at?->toDateString(),
            'status' => $plan->status->value,
            'value' => $this->sumEstimatedCost($plan->items),
        ])->values();

        return [
            'summary' => [
                'presented' => $presented,
                'accepted' => $acceptedPlans->count(),
                'rejected' => $rejectedCount,
                'acceptance_rate' => $presented > 0 ? round($acceptedPlans->count() / $presented * 100, 1) : 0.0,
                'accepted_value' => $acceptedPlans->reduce(
                    fn (string $carry, TreatmentPlan $plan) => bcadd($carry, $this->sumEstimatedCost($plan->items), 2),
                    '0.00'
                ),
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * @return array{summary: array{total: int, by_month: list<array{month: string, count: int}>}, rows: list<array{name: string, patient_code: string, registered_at: string}>}
     */
    public function newPatients(string $dateFrom, string $dateTo): array
    {
        /** @var Collection<int, Patient> $patients */
        $patients = Patient::query()
            ->whereBetween('created_at', ["{$dateFrom} 00:00:00", "{$dateTo} 23:59:59"])
            ->orderBy('created_at')
            ->get();

        $rows = $patients->map(fn (Patient $patient) => [
            'name' => $patient->full_name,
            'patient_code' => $patient->patient_code,
            'registered_at' => $patient->created_at->toDateString(),
        ])->values();

        $byMonth = $patients->groupBy(fn (Patient $p) => $p->created_at->format('Y-m'))
            ->map(fn (Collection $group, string $month) => ['month' => $month, 'count' => $group->count()])
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => $patients->count(),
                'by_month' => $byMonth,
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     */
    private function sumAmounts(Collection $items): string
    {
        return $items->reduce(fn (string $carry, InvoiceItem $item) => bcadd($carry, $item->amount, 2), '0.00');
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function sumPaymentAmounts(Collection $payments): string
    {
        return $payments->reduce(fn (string $carry, Payment $p) => bcadd($carry, (string) $p->amount, 2), '0.00');
    }

    /**
     * Same charge+tax-minus-discount formula as `InvoiceResource::sumTotal()`/`balance_due`
     * (design doc §4.3) — recomputed here rather than shared, matching this codebase's existing
     * precedent of each layer computing its own derived, never-stored totals
     * (`InvoiceResource`/`TreatmentPlanResource` each already do this independently).
     */
    private function invoiceBalanceDue(Invoice $invoice): string
    {
        $total = $invoice->items->reduce(
            fn (string $carry, InvoiceItem $item) => match ($item->kind) {
                InvoiceItemKind::Charge, InvoiceItemKind::Tax => bcadd($carry, $item->amount, 2),
                InvoiceItemKind::Discount => bcsub($carry, $item->amount, 2),
            },
            '0.00'
        );

        $amountPaid = $invoice->payments->reduce(fn (string $carry, Payment $p) => bcadd($carry, (string) $p->amount, 2), '0.00');

        return bcsub($total, $amountPaid, 2);
    }

    private function agingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /**
     * Mirrors `TreatmentPlanResource::sumEstimatedCost()` exactly — cancelled items never count
     * toward a plan's value.
     *
     * @param  Collection<int, TreatmentPlanItem>  $items
     */
    private function sumEstimatedCost(Collection $items): string
    {
        return $items
            ->filter(fn (TreatmentPlanItem $item) => $item->status !== TreatmentPlanItemStatus::Cancelled)
            ->reduce(fn (string $carry, TreatmentPlanItem $item) => bcadd($carry, $item->estimated_cost, 2), '0.00');
    }
}
