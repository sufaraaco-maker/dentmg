<?php

namespace App\Services;

use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Enums\TreatmentPlanItemStatus;
use App\Events\PatientActivityOccurred;
use App\Exceptions\Invoice\InvalidInvoiceItemException;
use App\Exceptions\Invoice\InvalidInvoiceStatusTransitionException;
use App\Exceptions\Invoice\InvoiceItemLockedException;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * One service for both Invoice and InvoiceItem operations, mirroring TreatmentPlanService's
 * consolidation (design doc §7/§20-equivalent reasoning) — item mutations routinely need to check
 * parent-invoice state (the draft-only lock, below), so splitting into two services would just
 * move that coupling to call sites instead of removing it.
 */
class InvoiceService
{
    /**
     * Clinic-wide invoice list (frontend-ux-redesign design doc §5.1/§11) — every other Invoice
     * endpoint is deliberately patient-scoped and unpaginated (a single patient's lifetime invoice
     * count stays small, per `InvoiceController::index()`'s own doc comment), but the Sidebar's
     * Billing entry needs a real destination across the whole practice, which does need paging.
     * Mirrors `PatientService::paginate()`'s exact shape (optional search, default 15/page).
     */
    public function paginateAll(?string $search = null, ?InvoiceStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->when($status, fn ($query) => $query->withStatus($status))
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('invoice_number', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p
                    ->whereLike('first_name', "%{$search}%")
                    ->orWhereLike('last_name', "%{$search}%")
                )
            ))
            ->with(['items.treatmentPlanItem', 'createdBy', 'payments', 'patient'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route, once Step 3 exists).
     */
    public function createDraft(array $data, User $actor): Invoice
    {
        return Invoice::create([
            'patient_id' => $data['patient_id'],
            'created_by_id' => $actor->id,
            'sequence_number' => null,
            'invoice_number' => null,
            // Snapshot at creation, not issue (design doc §6) — currency context applies from the
            // first item entered, not just once finalized.
            'currency_code' => BillingSetting::query()->value('currency_code') ?? 'USD',
            'status' => InvoiceStatus::Draft,
            'notes' => $data['notes'] ?? null,
            'issue_date' => $data['issue_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);
    }

    /**
     * Design doc §8/§9: notes/issue_date/due_date are editable only while draft — administrative
     * metadata on the invoice itself is gated by the exact same centralized lock as every item
     * mutation below (requirement 3), not a separate, weaker check.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Invoice $invoice, array $data): Invoice
    {
        $this->assertEditable($invoice);

        $invoice->update(array_intersect_key($data, array_flip(['notes', 'issue_date', 'due_date'])));

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addItem(Invoice $invoice, array $data, User $actor): InvoiceItem
    {
        $this->assertEditable($invoice);

        $kind = $data['kind'] instanceof InvoiceItemKind ? $data['kind'] : InvoiceItemKind::from($data['kind']);
        $treatmentPlanItemId = $data['treatment_plan_item_id'] ?? null;

        $this->assertKindAllowsSource($kind, $treatmentPlanItemId);

        $description = $data['description'] ?? null;
        $unitAmount = $data['unit_amount'] ?? null;

        if ($treatmentPlanItemId !== null) {
            $sourceItem = $this->findTreatmentPlanItemForPatient($treatmentPlanItemId, $invoice->patient_id);

            $description ??= $sourceItem->procedure_name;
            $unitAmount ??= (string) $sourceItem->unit_cost;
        }

        if ($description === null || $unitAmount === null) {
            throw new InvalidInvoiceItemException('A description and unit amount are required.');
        }

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'treatment_plan_item_id' => $treatmentPlanItemId,
            'kind' => $kind,
            'description' => $description,
            'quantity' => $data['quantity'] ?? 1,
            'unit_amount' => $unitAmount,
            'sequence' => $data['sequence'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(InvoiceItem $item, array $data): InvoiceItem
    {
        $this->assertEditable($item->invoice);

        $kind = array_key_exists('kind', $data)
            ? ($data['kind'] instanceof InvoiceItemKind ? $data['kind'] : InvoiceItemKind::from($data['kind']))
            : $item->kind;
        $treatmentPlanItemId = array_key_exists('treatment_plan_item_id', $data)
            ? $data['treatment_plan_item_id']
            : $item->treatment_plan_item_id;

        $this->assertKindAllowsSource($kind, $treatmentPlanItemId);

        if ($treatmentPlanItemId !== null && array_key_exists('treatment_plan_item_id', $data)) {
            $this->findTreatmentPlanItemForPatient($treatmentPlanItemId, $item->invoice->patient_id);
        }

        $item->update($data);

        return $item;
    }

    public function deleteItem(InvoiceItem $item): void
    {
        $this->assertEditable($item->invoice);

        $item->delete();
    }

    /**
     * Design doc §5/§6/§8, requirements 1 and 2: atomic and row-locked. Locks the invoice row
     * itself (prevents a concurrent double-issue race on the same invoice — e.g. two rapid
     * clicks) and, inside the same transaction, atomically reserves the next sequence number from
     * `billing_settings` (see reserveNextSequenceNumber() for why that — not
     * MAX(invoices.sequence_number) — is the concurrency-safe source).
     */
    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, InvoiceStatus::Issued);

            $reservation = $this->reserveNextSequenceNumber();

            $locked->sequence_number = $reservation['sequence_number'];
            $locked->invoice_number = $reservation['prefix'].'-'.str_pad(
                (string) $reservation['sequence_number'],
                6,
                '0',
                STR_PAD_LEFT
            );
            $locked->status = InvoiceStatus::Issued;
            $locked->issue_date ??= now()->toDateString();
            $locked->issued_at = now();
            $locked->save();

            $fresh = $locked->fresh('items');

            event(new PatientActivityOccurred(
                $fresh, Auth::user(), 'invoice.issued', 'billing',
                "Invoice {$fresh->invoice_number} issued for {$this->total($fresh)}",
            ));

            return $fresh;
        });
    }

    /**
     * Design doc §5/§8, requirements 1 and 2: atomic and row-locked, symmetric with issue(). Void
     * is only reachable from issued — InvoiceStatus::transitionsFrom() enforces this; a draft
     * invoice is deleted instead, never voided (design doc §8).
     */
    public function void(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $this->assertTransition($locked->status, InvoiceStatus::Void);

            $locked->status = InvoiceStatus::Void;
            $locked->voided_at = now();
            $locked->save();

            event(new PatientActivityOccurred(
                $locked, Auth::user(), 'invoice.voided', 'billing', "Invoice {$locked->invoice_number} voided",
            ));

            return $locked->fresh('items');
        });
    }

    /**
     * Soft delete — draft only (design doc §9: "DELETE .../invoices/{invoice} (soft delete —
     * admin-only, draft only)"). An issued/void invoice is a real financial record and is never
     * deleted; the only correction path for those is void (design doc §8).
     */
    public function deleteInvoice(Invoice $invoice): void
    {
        $this->assertEditable($invoice);

        $invoice->delete();
    }

    /**
     * The "not yet invoiced, completed" picker source for design doc §3 step 1 / §9 —
     * `patient_id`-scoped and anti-joined against `invoice_items` (§7/§13). A `TreatmentPlanItem`
     * counts as already billed only if it's referenced by a non-deleted item on a non-void
     * invoice: a void invoice's items are deliberately excluded from that exclusion set (§8), so
     * a corrected/voided invoice's lines become billable again rather than being permanently
     * absorbed.
     *
     * @return Collection<int, TreatmentPlanItem>
     */
    public function billableTreatmentPlanItems(string $patientId): Collection
    {
        return TreatmentPlanItem::query()
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patientId))
            ->where('status', TreatmentPlanItemStatus::Completed)
            ->whereNotIn('id', function ($query) {
                $query->select('invoice_items.treatment_plan_item_id')
                    ->from('invoice_items')
                    ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                    ->whereNotNull('invoice_items.treatment_plan_item_id')
                    ->whereNull('invoice_items.deleted_at')
                    ->where('invoices.status', '!=', InvoiceStatus::Void->value);
            })
            ->with('dentalCondition')
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Invoice-level total (design doc §4/§6): SUM(charge) + SUM(tax) - SUM(discount) across
     * non-deleted items — a single query, never stored/cached, same "don't store derivable state"
     * principle as TreatmentPlanService::estimatedCost().
     */
    public function total(Invoice $invoice): string
    {
        $subtotals = $invoice->items()
            ->selectRaw('kind, COALESCE(SUM(unit_amount * quantity), 0) as subtotal')
            ->groupBy('kind')
            ->pluck('subtotal', 'kind');

        $total = ((float) ($subtotals[InvoiceItemKind::Charge->value] ?? 0))
            + ((float) ($subtotals[InvoiceItemKind::Tax->value] ?? 0))
            - ((float) ($subtotals[InvoiceItemKind::Discount->value] ?? 0));

        return number_format($total, 2, '.', '');
    }

    /**
     * Requirement 3: the single, unavoidable gate every mutation method above routes through —
     * private, so no code path (Controller, console command, tinker, a future job) can add/edit/
     * remove an item or edit invoice metadata on a non-draft invoice without passing through this
     * check first. This is the module's actual enforcement of "no silent mutation of an issued
     * invoice" (design doc Decision Log item 2) — the Policy layer governs *who* may attempt an
     * action, this governs *whether the invoice's current state allows it at all*, and the second
     * check can never be skipped by calling the Service directly.
     */
    private function assertEditable(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw InvoiceItemLockedException::forInvoice($invoice);
        }
    }

    private function assertTransition(InvoiceStatus $from, InvoiceStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new InvalidInvoiceStatusTransitionException($from, $to);
        }
    }

    private function assertKindAllowsSource(InvoiceItemKind $kind, ?string $treatmentPlanItemId): void
    {
        if ($kind !== InvoiceItemKind::Charge && $treatmentPlanItemId !== null) {
            throw new InvalidInvoiceItemException('Only charge items may reference a treatment plan item.');
        }
    }

    private function findTreatmentPlanItemForPatient(string $treatmentPlanItemId, string $patientId): TreatmentPlanItem
    {
        $sourceItem = TreatmentPlanItem::query()
            ->where('id', $treatmentPlanItemId)
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patientId))
            ->first();

        if ($sourceItem === null) {
            throw new InvalidInvoiceItemException("The selected treatment plan item does not belong to this invoice's patient.");
        }

        return $sourceItem;
    }

    /**
     * Atomically reserves the next invoice sequence number by locking and incrementing
     * `billing_settings.next_invoice_sequence` in place — deliberately NOT derived from
     * `MAX(invoices.sequence_number)` (design doc §6, requirement 2).
     *
     * Locking "whichever invoice row currently happens to be the max" does not actually serialize
     * concurrent issue() calls: the row being locked (some other, already-issued invoice) is
     * never the row being updated (the invoice being issued right now), so its value never
     * changes. Two concurrent callers can both lock it, both unblock re-reading the same
     * unchanged value, and both compute the identical "next" number — the database's unique
     * constraint on `invoices.sequence_number` would still catch the resulting collision and
     * throw, but only as a last-resort safety net, not the graceful serialization requirement 2
     * asks for.
     *
     * Locking and incrementing this row instead fixes that: the locked row IS the row being
     * modified, so a second concurrent caller blocks on this exact row and, once unblocked,
     * re-reads the already-incremented value (Postgres re-checks the row's latest committed
     * version when a FOR UPDATE wait resolves) — genuine serialization, not a race caught after
     * the fact.
     *
     * MUST be called from inside the same transaction as the write that consumes the returned
     * number (see issue()) — the lock is held only until that transaction commits.
     *
     * @return array{sequence_number: int, prefix: string}
     */
    private function reserveNextSequenceNumber(): array
    {
        $settings = BillingSetting::query()->lockForUpdate()->first();

        if ($settings === null) {
            // First-ever issued invoice with no settings row created yet — self-heal with the
            // documented defaults (design doc §6) rather than requiring a seeder/admin step
            // first. No separate lock needed for this freshly-created row: under READ COMMITTED,
            // no other transaction can see it until this one commits.
            $settings = BillingSetting::create([
                'currency_code' => 'USD',
                'invoice_number_prefix' => 'INV',
                'next_invoice_sequence' => 1,
            ]);
        }

        $reserved = $settings->next_invoice_sequence;

        $settings->next_invoice_sequence = $reserved + 1;
        $settings->save();

        return ['sequence_number' => $reserved, 'prefix' => $settings->invoice_number_prefix];
    }
}
