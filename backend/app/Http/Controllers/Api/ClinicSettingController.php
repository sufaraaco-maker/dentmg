<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicSetting\UpdateClinicSettingRequest;
use App\Http\Resources\ClinicSettingResource;
use App\Models\ClinicSetting;
use App\Services\ClinicSettingService;

/**
 * A true singleton resource (design doc §6) — only `show`/`update`, no `{id}`, no `index`/`store`/
 * `destroy`.
 */
class ClinicSettingController extends Controller
{
    public function __construct(private ClinicSettingService $clinicSettingService) {}

    public function show()
    {
        $this->authorize('view', ClinicSetting::class);

        // Explicit 200: the self-healing firstOrCreate() in current() can leave the underlying
        // model's `wasRecentlyCreated` true, which Laravel's Resource response otherwise reads as
        // "auto-201" — wrong for both this read and update() below, which are never a real create.
        return (new ClinicSettingResource($this->clinicSettingService->current()))
            ->response()
            ->setStatusCode(200);
    }

    public function update(UpdateClinicSettingRequest $request)
    {
        $settings = $this->clinicSettingService->update($request->validated());

        return (new ClinicSettingResource($settings))->response()->setStatusCode(200);
    }
}
