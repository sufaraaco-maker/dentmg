<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function __construct(private PatientService $patientService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $patients = $this->patientService->paginate($request->string('search')->value() ?: null);

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request)
    {
        $patient = $this->patientService->create($request->validated());

        return (new PatientResource($patient))->response()->setStatusCode(201);
    }

    public function show(Patient $patient)
    {
        $this->authorize('view', $patient);

        return new PatientResource($patient);
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $patient = $this->patientService->update($patient, $request->validated());

        return new PatientResource($patient);
    }

    public function destroy(Patient $patient)
    {
        $this->authorize('delete', $patient);

        $this->patientService->delete($patient);

        return response()->noContent();
    }

    public function auditLogs(Patient $patient)
    {
        $this->authorize('viewAuditLogs', Patient::class);

        return AuditLogResource::collection(
            $patient->auditLogs()->with('user')->paginate(15)
        );
    }
}
