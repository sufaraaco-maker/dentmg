<?php

namespace Tests;

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
    }
}
