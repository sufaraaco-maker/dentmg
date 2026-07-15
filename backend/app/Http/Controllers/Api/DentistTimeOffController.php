<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DentistTimeOff\StoreDentistTimeOffRequest;
use App\Http\Resources\DentistTimeOffResource;
use App\Models\DentistTimeOff;
use App\Models\User;
use App\Services\DentistTimeOffService;

class DentistTimeOffController extends Controller
{
    public function __construct(private DentistTimeOffService $timeOffService) {}

    public function index(User $user)
    {
        $this->authorize('viewAny', DentistTimeOff::class);

        $timeOff = $this->timeOffService->listForDentist($user->id);

        return DentistTimeOffResource::collection($timeOff);
    }

    public function store(StoreDentistTimeOffRequest $request, User $user)
    {
        $timeOff = $this->timeOffService->create($user, $request->validated());

        return (new DentistTimeOffResource($timeOff))->response()->setStatusCode(201);
    }

    public function destroy(User $user, DentistTimeOff $timeOff)
    {
        $this->authorize('delete', $timeOff);

        $this->timeOffService->delete($timeOff);

        return response()->noContent();
    }
}
