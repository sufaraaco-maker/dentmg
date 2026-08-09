<?php

namespace App\Services;

use App\Enums\ClinicalNoteStatus;
use App\Events\PatientActivityOccurred;
use App\Exceptions\ClinicalNote\ClinicalNoteLockedException;
use App\Exceptions\ClinicalNote\InvalidClinicalNoteOperationException;
use App\Models\ClinicalNote;
use App\Models\ClinicalNoteAddendum;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClinicalNoteService
{
    /**
     * Fields locked the instant a note leaves `draft` (design doc §8 rule 3). `dentist_id` is
     * deliberately not editable at all through this method — set once at creation, same
     * "author of record, fixed from the start" treatment as `patient_id`.
     *
     * @var list<string>
     */
    private const EDITABLE_WHILE_DRAFT = [
        'note_type',
        'chief_complaint',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'appointment_id',
    ];

    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route) and `dentist_id`.
     */
    public function create(array $data, User $actor): ClinicalNote
    {
        return ClinicalNote::create([
            'patient_id' => $data['patient_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            'dentist_id' => $data['dentist_id'],
            'note_type' => $data['note_type'],
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'subjective' => $data['subjective'] ?? null,
            'objective' => $data['objective'] ?? null,
            'assessment' => $data['assessment'] ?? null,
            'plan' => $data['plan'] ?? null,
            'status' => ClinicalNoteStatus::Draft,
            'created_by_id' => $actor->id,
        ]);
    }

    /**
     * Draft-only edit (design doc §8 rules 1/3) — rejects the request the instant the note is
     * signed, enforced here rather than trusted to the caller.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ClinicalNote $clinicalNote, array $data, User $actor): ClinicalNote
    {
        $this->assertEditable($clinicalNote);

        $clinicalNote->fill(array_intersect_key($data, array_flip(self::EDITABLE_WHILE_DRAFT)));
        $clinicalNote->updated_by_id = $actor->id;
        $clinicalNote->save();

        return $clinicalNote;
    }

    /**
     * Transactional and idempotent (Step 2 requirement, design doc §3/§8 rule 3): row-locked so two
     * concurrent sign requests for the same note can never race each other, mirroring
     * `InvoiceService::issue()`/`PaymentService::apply()`'s identical `lockForUpdate()` pattern.
     * Idempotent by design — calling this on an already-`signed` note is a safe no-op that returns
     * the existing signed state rather than erroring, so a double-submitted "Sign" click (a slow
     * network retry, a double-click before the button disables) can never surface a spurious
     * failure or re-stamp `signed_at`/`signed_by_id` with a second, different value.
     */
    public function sign(ClinicalNote $clinicalNote, User $actor): ClinicalNote
    {
        return DB::transaction(function () use ($clinicalNote, $actor) {
            $locked = ClinicalNote::query()->whereKey($clinicalNote->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ClinicalNoteStatus::Signed) {
                return $locked;
            }

            if ($this->isBlank($locked)) {
                throw new InvalidClinicalNoteOperationException(
                    'Cannot sign an empty clinical note — at least one section must be completed first.'
                );
            }

            $locked->status = ClinicalNoteStatus::Signed;
            $locked->signed_at = now();
            $locked->signed_by_id = $actor->id;
            $locked->save();

            // Only the real transition fires — the idempotent already-signed early-return above
            // must never double-record (design doc §5).
            event(new PatientActivityOccurred(
                $locked, $actor, 'clinical_note.signed', 'clinical_notes', 'Clinical note signed',
            ));

            return $locked;
        });
    }

    /**
     * Addendums may only be added once the parent note is `signed` (design doc §4/§8 rule 4) —
     * appending context to a draft is just editing the draft itself. Deliberately never writes to
     * the parent `ClinicalNote` row: no `$touches` on `ClinicalNoteAddendum`, and this method never
     * calls `$clinicalNote->save()` — an addendum must never silently update the parent's
     * `updated_at`/`updated_by_id`, since it isn't an edit of the note (Step 2 requirement).
     */
    public function addAddendum(ClinicalNote $clinicalNote, string $body, User $actor): ClinicalNoteAddendum
    {
        if ($clinicalNote->status !== ClinicalNoteStatus::Signed) {
            throw new InvalidClinicalNoteOperationException(
                'Addendums can only be added to a signed clinical note.'
            );
        }

        return ClinicalNoteAddendum::create([
            'clinical_note_id' => $clinicalNote->id,
            'author_id' => $actor->id,
            'body' => $body,
        ]);
    }

    /**
     * Admin-only, soft delete, allowed regardless of `draft`/`signed` status (design doc §8 rule 6,
     * §15 Decision C) — never a hard delete, always recoverable via `restore()` and fully captured
     * by `Auditable`. Authorization itself is the Policy's job, not this method's.
     */
    public function delete(ClinicalNote $clinicalNote): void
    {
        $clinicalNote->delete();
    }

    private function assertEditable(ClinicalNote $clinicalNote): void
    {
        if ($clinicalNote->status === ClinicalNoteStatus::Signed) {
            throw new ClinicalNoteLockedException;
        }
    }

    private function isBlank(ClinicalNote $clinicalNote): bool
    {
        foreach (['chief_complaint', 'subjective', 'objective', 'assessment', 'plan'] as $field) {
            if (filled($clinicalNote->{$field})) {
                return false;
            }
        }

        return true;
    }
}
