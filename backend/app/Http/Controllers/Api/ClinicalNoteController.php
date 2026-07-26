<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalNote\AddClinicalNoteAddendumRequest;
use App\Http\Requests\ClinicalNote\StoreClinicalNoteRequest;
use App\Http\Requests\ClinicalNote\UpdateClinicalNoteRequest;
use App\Http\Resources\ClinicalNoteResource;
use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Services\ClinicalNoteService;
use Illuminate\Http\Request;

class ClinicalNoteController extends Controller
{
    /** @var list<string> */
    private const WITH = ['dentist', 'signedBy', 'createdBy', 'addendums.author'];

    public function __construct(private ClinicalNoteService $clinicalNoteService) {}

    public function index(Patient $patient)
    {
        $this->authorize('viewAny', ClinicalNote::class);

        $notes = ClinicalNote::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest('created_at')
            ->get();

        return ClinicalNoteResource::collection($notes);
    }

    public function store(StoreClinicalNoteRequest $request, Patient $patient)
    {
        $note = $this->clinicalNoteService->create(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new ClinicalNoteResource($note->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(ClinicalNote $clinical_note)
    {
        $this->authorize('view', $clinical_note);

        return new ClinicalNoteResource($clinical_note->load(self::WITH));
    }

    public function update(UpdateClinicalNoteRequest $request, ClinicalNote $clinical_note)
    {
        $note = $this->clinicalNoteService->update($clinical_note, $request->validated(), $request->user());

        return new ClinicalNoteResource($note->load(self::WITH));
    }

    public function sign(Request $request, ClinicalNote $clinical_note)
    {
        $this->authorize('sign', $clinical_note);

        $note = $this->clinicalNoteService->sign($clinical_note, $request->user());

        return new ClinicalNoteResource($note->load(self::WITH));
    }

    public function addAddendum(AddClinicalNoteAddendumRequest $request, ClinicalNote $clinical_note)
    {
        $this->clinicalNoteService->addAddendum($clinical_note, $request->validated()['body'], $request->user());

        return new ClinicalNoteResource($clinical_note->load(self::WITH));
    }

    public function destroy(ClinicalNote $clinical_note)
    {
        $this->authorize('delete', $clinical_note);

        $this->clinicalNoteService->delete($clinical_note);

        return response()->noContent();
    }
}
