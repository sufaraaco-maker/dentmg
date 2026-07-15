<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentType\StoreAppointmentTypeRequest;
use App\Http\Requests\AppointmentType\UpdateAppointmentTypeRequest;
use App\Http\Resources\AppointmentTypeResource;
use App\Models\AppointmentType;
use App\Services\AppointmentTypeService;

class AppointmentTypeController extends Controller
{
    public function __construct(private AppointmentTypeService $appointmentTypeService) {}

    public function index()
    {
        $this->authorize('viewAny', AppointmentType::class);

        $types = $this->appointmentTypeService->list();

        return AppointmentTypeResource::collection($types);
    }

    public function store(StoreAppointmentTypeRequest $request)
    {
        $type = $this->appointmentTypeService->create($request->validated());

        return (new AppointmentTypeResource($type))->response()->setStatusCode(201);
    }

    public function show(AppointmentType $appointmentType)
    {
        $this->authorize('view', $appointmentType);

        return new AppointmentTypeResource($appointmentType);
    }

    public function update(UpdateAppointmentTypeRequest $request, AppointmentType $appointmentType)
    {
        $appointmentType = $this->appointmentTypeService->update($appointmentType, $request->validated());

        return new AppointmentTypeResource($appointmentType);
    }

    public function destroy(AppointmentType $appointmentType)
    {
        $this->authorize('delete', $appointmentType);

        $this->appointmentTypeService->delete($appointmentType);

        return response()->noContent();
    }
}
