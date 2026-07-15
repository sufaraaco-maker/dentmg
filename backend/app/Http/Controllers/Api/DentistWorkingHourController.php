<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DentistWorkingHour\StoreDentistWorkingHourRequest;
use App\Http\Resources\DentistWorkingHourResource;
use App\Models\DentistWorkingHour;
use App\Models\User;
use App\Services\DentistWorkingHourService;

class DentistWorkingHourController extends Controller
{
    public function __construct(private DentistWorkingHourService $workingHourService) {}

    public function index(User $user)
    {
        $this->authorize('viewAny', DentistWorkingHour::class);

        $workingHours = $this->workingHourService->listForDentist($user->id);

        return DentistWorkingHourResource::collection($workingHours);
    }

    public function store(StoreDentistWorkingHourRequest $request, User $user)
    {
        $workingHour = $this->workingHourService->create($user, $request->validated());

        return (new DentistWorkingHourResource($workingHour))->response()->setStatusCode(201);
    }

    public function destroy(User $user, DentistWorkingHour $workingHour)
    {
        $this->authorize('delete', $workingHour);

        $this->workingHourService->delete($workingHour);

        return response()->noContent();
    }
}
