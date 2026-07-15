<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a real bug found while manually verifying the Patients module:
 * Laravel's default guest-redirect (route('login')) crashes with a 500 for any
 * unauthenticated API request that doesn't send Accept: application/json — which
 * postJson()/getJson() always do, so the normal test suite never caught it.
 * See bootstrap/app.php and docs/decisions.md.
 */
class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_request_without_accept_header_gets_json_401_not_a_crash(): void
    {
        $response = $this->post('/api/patients', [], ['Content-Type' => 'application/json']);

        $response->assertUnauthorized();
        $response->assertJson(['message' => 'Unauthenticated.']);
    }
}
