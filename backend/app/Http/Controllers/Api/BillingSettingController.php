<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingSetting\UpdateBillingSettingRequest;
use App\Http\Resources\BillingSettingResource;
use App\Models\BillingSetting;
use App\Services\BillingSettingService;

/**
 * A true singleton resource (design doc §6) — only `show`/`update`, no `{id}`, no `index`/`store`/
 * `destroy`.
 */
class BillingSettingController extends Controller
{
    public function __construct(private BillingSettingService $billingSettingService) {}

    public function show()
    {
        $this->authorize('view', BillingSetting::class);

        // Explicit 200: the self-healing firstOrCreate() in current() can leave the underlying
        // model's `wasRecentlyCreated` true, which Laravel's Resource response otherwise reads as
        // "auto-201" — wrong for both this read and update() below, which are never a real create.
        return (new BillingSettingResource($this->billingSettingService->current()))
            ->response()
            ->setStatusCode(200);
    }

    public function update(UpdateBillingSettingRequest $request)
    {
        $settings = $this->billingSettingService->update($request->validated());

        return (new BillingSettingResource($settings))->response()->setStatusCode(200);
    }
}
