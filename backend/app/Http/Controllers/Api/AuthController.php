<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function login(LoginRequest $request)
    {
        $user = $this->authService->login(
            $request,
            $request->throttleKey(),
            $request->string('email'),
            $request->string('password'),
        );

        return new UserResource($user);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request);

        return response()->noContent();
    }

    public function user(Request $request)
    {
        return new UserResource($request->user());
    }
}
