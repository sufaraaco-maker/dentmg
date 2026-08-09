<?php

namespace Tests\Unit\Models;

use App\Models\AppointmentType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 3 design doc §2.5 — belt-and-suspenders guard on
 * top of "no update/destroy route or service method exists anywhere."
 */
class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_audit_log_entry_throws(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        AppointmentType::factory()->create();

        $log = AuditLog::query()->where('action', 'created')->firstOrFail();

        $this->expectException(LogicException::class);

        $log->action = 'tampered';
        $log->save();
    }

    public function test_deleting_an_audit_log_entry_throws(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        AppointmentType::factory()->create();

        $log = AuditLog::query()->where('action', 'created')->firstOrFail();

        $this->expectException(LogicException::class);

        $log->delete();
    }
}
