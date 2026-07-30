<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_billing_settings(): void
    {
        $response = $this->getJson('/api/billing-settings');

        $response->assertUnauthorized();
    }

    public function test_dentist_cannot_view_billing_settings(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->getJson('/api/billing-settings');

        $response->assertForbidden();
    }

    public function test_admin_can_view_billing_settings(): void
    {
        $actor = User::factory()->admin()->create();
        BillingSetting::factory()->create(['currency_code' => 'EUR']);

        $response = $this->actingAs($actor)->getJson('/api/billing-settings');

        $response->assertOk();
        $this->assertSame('EUR', $response->json('currency_code'));
    }

    public function test_viewing_billing_settings_self_heals_a_default_row_when_none_exists(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->getJson('/api/billing-settings');

        $response->assertOk();
        $this->assertSame('USD', $response->json('currency_code'));
        $this->assertSame('INV', $response->json('invoice_number_prefix'));
        $this->assertDatabaseCount('billing_settings', 1);
    }

    public function test_admin_can_update_billing_settings(): void
    {
        $actor = User::factory()->admin()->create();
        BillingSetting::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/billing-settings', [
            'currency_code' => 'GBP',
            'tax_rate' => 7.5,
            'invoice_number_prefix' => 'INVOICE-',
        ]);

        $response->assertOk();
        $this->assertSame('GBP', $response->json('currency_code'));
        $this->assertSame('7.50', $response->json('tax_rate'));
        $this->assertDatabaseCount('billing_settings', 1);
    }

    public function test_updating_billing_settings_cannot_set_next_invoice_sequence(): void
    {
        $actor = User::factory()->admin()->create();
        BillingSetting::factory()->create(['next_invoice_sequence' => 5]);

        $response = $this->actingAs($actor)->putJson('/api/billing-settings', [
            'currency_code' => 'USD',
            'invoice_number_prefix' => 'INV',
            'next_invoice_sequence' => 999,
        ]);

        $response->assertOk();
        $this->assertSame(5, $response->json('next_invoice_sequence'));
    }

    public function test_receptionist_cannot_update_billing_settings(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->putJson('/api/billing-settings', [
            'currency_code' => 'USD',
            'invoice_number_prefix' => 'INV',
        ]);

        $response->assertForbidden();
    }
}
