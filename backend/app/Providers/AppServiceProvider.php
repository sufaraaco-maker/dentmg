<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Models\User;
use App\Policies\MedicalHistoryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep API responses flat (no "data" envelope) to match the rest of the API.
        JsonResource::withoutWrapping();

        // Baseline throttle for every api/* route (bootstrap/app.php wires this in via
        // throttleApi()). /login has its own tighter, purpose-built limiter
        // (see AuthService::MAX_ATTEMPTS) — this is the general-purpose backstop for
        // everything else (patients, users, appointments, etc.), which previously had none.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('api.throttle_per_minute'))->by(
            $request->user()?->id ?: $request->ip()
        ));

        // Reports has no natural Eloquent model to attach a Policy to (design doc §5), so it uses
        // two plain Gate abilities instead — financial reports expose practice-wide revenue/A-R,
        // a categorically more sensitive scope than any single patient's billing record.
        Gate::define('view-financial-reports', fn (User $user) => $user->role === UserRole::Admin);
        Gate::define('view-operational-reports', fn (User $user) => true);

        // Phase 4 (Advanced Permissions & Audit) design doc §1.4: managing the role_permissions
        // matrix itself is checked via a hardcoded isAdmin() Gate, never routed through the
        // matrix it governs — this is what makes self-lockout structurally impossible, not just
        // validated-against (see UpdateRolePermissionsRequest for the matrix's own separate
        // users.manage protection).
        Gate::define('manage-permissions', fn (User $user) => $user->isAdmin());

        // Phase 4 Step 3 design doc §2.6: the general (non-patient-scoped) Audit Log viewer —
        // same hardcoded isAdmin() pattern as manage-permissions, for the same reason.
        Gate::define('view-audit-logs', fn (User $user) => $user->isAdmin());

        // Medical History (Phase 2.3, design doc §6.4): one policy class for all three entities,
        // registered explicitly since Laravel's naming-convention auto-discovery only maps one
        // policy per model.
        Gate::policy(PatientAllergy::class, MedicalHistoryPolicy::class);
        Gate::policy(PatientMedicalCondition::class, MedicalHistoryPolicy::class);
        Gate::policy(PatientMedication::class, MedicalHistoryPolicy::class);
    }
}
