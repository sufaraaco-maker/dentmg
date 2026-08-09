<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(private AuditLogService $auditLogService) {}

    public function login(Request $request, string $throttleKey, string $email, string $password): User
    {
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            // Phase 4 Step 3 design doc §2.2: not a distinct audit action — the real credential
            // failures that got this throttle key rate-limited were already each logged as
            // login_failed on their own prior request.
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            // No resolvable User for an unknown/wrong email — auditable_id is null, the
            // attempted email goes in `context` (never the password, which never reaches here).
            $this->auditLogService->recordEvent(User::class, null, 'login_failed', context: ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        $this->auditLogService->recordEvent(User::class, (string) $user->id, 'login_succeeded');

        return $user;
    }

    public function logout(Request $request): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        // Logged while still authenticated — AuditLogService::write() reads Auth::id() for the
        // "who performed this" actor field, which would resolve to null once the guard below
        // actually logs out, wrongly recording a logout with no actor.
        if ($user) {
            $this->auditLogService->recordEvent(User::class, (string) $user->id, 'logged_out');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
