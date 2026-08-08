<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientDocument\StorePatientDocumentRequest;
use App\Http\Requests\PatientDocument\UpdatePatientDocumentRequest;
use App\Http\Resources\PatientDocumentResource;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Services\PatientDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Patient-scoped only — design doc §4.1/§16 decision 4: unlike Laboratory, there's no pre-existing
 * flat `GET /documents` endpoint to reconcile with, so this never adds one.
 */
class PatientDocumentController extends Controller
{
    public function __construct(private PatientDocumentService $patientDocumentService) {}

    public function index(Request $request, Patient $patient)
    {
        $this->authorize('viewAny', PatientDocument::class);

        $documents = $patient->documents()
            ->with('uploadedBy')
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return PatientDocumentResource::collection($documents);
    }

    public function store(StorePatientDocumentRequest $request, Patient $patient)
    {
        $document = $this->patientDocumentService->upload(
            $patient,
            $request->file('file'),
            $request->safe()->except('file'),
            $request->user(),
        );

        return (new PatientDocumentResource($document->load('uploadedBy')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePatientDocumentRequest $request, PatientDocument $patient_document)
    {
        $document = $this->patientDocumentService->update($patient_document, $request->validated());

        return new PatientDocumentResource($document->load('uploadedBy'));
    }

    public function destroy(PatientDocument $patient_document)
    {
        $this->authorize('delete', $patient_document);

        $this->patientDocumentService->delete($patient_document);

        return response()->noContent();
    }

    /**
     * Streams the original file through an authenticated, policy-checked route — never a public/
     * static URL, mirrors PatientImageController::file().
     */
    public function file(PatientDocument $patient_document)
    {
        $this->authorize('view', $patient_document);

        return Storage::disk($patient_document->disk)->response($patient_document->path);
    }
}
