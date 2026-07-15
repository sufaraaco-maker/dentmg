<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userService->paginate($request->string('search')->value() ?: null);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update($user, $request->validated());

        return new UserResource($user);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user);

        return response()->noContent();
    }
}
