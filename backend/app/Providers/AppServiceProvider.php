<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Models\User;
use App\Notifications\Channels\DatabaseChannel;
use App\Observers\AuditLogObserver;
use App\Policies\MedicalHistoryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Channels\DatabaseChannel as BaseDatabaseChannel;
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
        // Phase 5 (Notification System) design doc §3.1 / Decision D1: Laravel's ChannelManager
        // resolves the `database` channel out of the container, so binding this subclass makes
        // every notification's plain `via() => ['database']` write the four additive columns
        // (category / subject / patient) as well as the stock ones — without any notification
        // class needing to know a custom channel exists.
        $this->app->bind(BaseDatabaseChannel::class, DatabaseChannel::class);
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

        // Phase 5C (Notification System — scheduled/administrative types) design doc §2: types 12-13
        // are reactive off AuditLog's own `created` event, not scheduled — AuditLogService::write()
        // always goes through a real Eloquent `create()`, so this fires for free on every audit row.
        AuditLog::observe(AuditLogObserver::class);
    }
}
