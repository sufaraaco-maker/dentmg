<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Requests are treated as coming from the SPA frontend (needed for
     * Sanctum's stateful session cookie auth) whenever a Referer header
     * matching a configured stateful domain is present.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.frontend_url'));

        // Phase 4 (Advanced Permissions & Audit) Step 2: every Policy now calls
        // User::hasPermission(), which is false for everything if role_permissions is empty. Any
        // test class using RefreshDatabase gets a real migrated (but otherwise empty) DB, so the
        // permission catalog + default matrix must be seeded here, once, centrally — not
        // copy-pasted into every Feature/Policy test file individually.
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(PermissionSeeder::class);
        }
    }
}
