<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientImage\StorePatientImageRequest;
use App\Http\Requests\PatientImage\UpdatePatientImageRequest;
use App\Http\Resources\PatientImageResource;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Services\PatientImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PatientImageController extends Controller
{
    public function __construct(private PatientImageService $patientImageService) {}

    public function index(Request $request, Patient $patient)
    {
        $this->authorize('viewAny', PatientImage::class);

        $images = $patient->images()
            ->with('uploadedBy')
            ->when($request->query('image_type'), fn ($query, $type) => $query->where('image_type', $type))
            ->when($request->query('tooth_number'), fn ($query, $tooth) => $query->where('tooth_number', $tooth))
            ->when($request->query('taken_from'), fn ($query, $date) => $query->where('taken_at', '>=', $date))
            ->when($request->query('taken_to'), fn ($query, $date) => $query->where('taken_at', '<=', $date))
            ->latest('taken_at')
            ->paginate((int) $request->query('per_page', 30));

        return PatientImageResource::collection($images);
    }

    public function store(StorePatientImageRequest $request, Patient $patient)
    {
        $images = $this->patientImageService->upload(
            $patient,
            $request->file('images'),
            $request->safe()->except('images'),
            $request->user(),
        );

        return PatientImageResource::collection(collect($images)->map->load('uploadedBy'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePatientImageRequest $request, PatientImage $patient_image)
    {
        $image = $this->patientImageService->update($patient_image, $request->validated());

        return new PatientImageResource($image->load('uploadedBy'));
    }

    public function destroy(PatientImage $patient_image)
    {
        $this->authorize('delete', $patient_image);

        $this->patientImageService->delete($patient_image);

        return response()->noContent();
    }

    /**
     * Streams the original file through an authenticated, policy-checked route — never a public/
     * static URL (design doc §9).
     */
    public function file(PatientImage $patient_image)
    {
        $this->authorize('view', $patient_image);

        return Storage::disk($patient_image->disk)->response($patient_image->path);
    }

    public function thumbnail(PatientImage $patient_image)
    {
        $this->authorize('view', $patient_image);

        abort_if($patient_image->thumbnail_path === null, 404);

        return Storage::disk($patient_image->disk)->response($patient_image->thumbnail_path);
    }
}
