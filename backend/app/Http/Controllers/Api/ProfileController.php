<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfilePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\Request;

/**
 * Self-service account management (design doc §4.3/§6). Every action resolves its target from
 * `$request->user()`, never a route-model-bound `{user}` parameter — structurally impossible to
 * reach another user's account through this controller, so no Policy is needed (design doc §5).
 */
class ProfileController extends Controller
{
    public function __construct(private ProfileService $profileService) {}

    public function show(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $this->profileService->updateProfile($request->user(), $request->validated());

        return new UserResource($user);
    }

    public function updatePassword(UpdateProfilePasswordRequest $request)
    {
        $this->profileService->updatePassword($request->user(), $request->validated('password'));

        return response()->noContent();
    }
}
