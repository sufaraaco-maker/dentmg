<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class DashboardFinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_financial_summary(): void
    {
        $response = $this->getJson('/api/dashboard/financial-summary');

        $response->assertUnauthorized();
    }

    /**
     * Same gate, same reasoning as `reports/production`/`reports/collections`/`reports/ar-aging`
     * (`ReportTest::test_financial_reports_are_admin_only`) — financial dashboard data is exactly
     * as sensitive as the reports it's built from.
     */
    public function test_only_admin_can_view_financial_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create();

        $this->actingAs($admin)->getJson('/api/dashboard/financial-summary')->assertOk();
        $this->actingAs($dentist)->getJson('/api/dashboard/financial-summary')->assertForbidden();
        $this->actingAs($receptionist)->getJson('/api/dashboard/financial-summary')->assertForbidden();
    }

    public function test_financial_summary_returns_expected_structure(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/dashboard/financial-summary');

        $response->assertStatus(200)->assertJsonStructure([
            'monthly_revenue',
            'production_trend' => ['current', 'previous', 'change_pct'],
            'collections_trend' => ['current', 'previous', 'change_pct'],
            'ar_aging' => ['total', 'buckets' => ['current', '1_30', '31_60', '61_90', '90_plus']],
        ]);
    }

    public function test_monthly_revenue_equals_collections_trend_current_period(): void
    {
        $admin = User::factory()->admin()->create();
        Payment::factory()->create(['amount' => '150.00', 'received_at' => Date::today()->startOfMonth()]);
        Payment::factory()->create(['amount' => '75.50', 'received_at' => Date::today()->endOfMonth()]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/financial-summary');

        $response->assertJson([
            'monthly_revenue' => '225.50',
            'collections_trend' => ['current' => '225.50'],
        ]);
    }

    public function test_collections_trend_previous_period_reflects_last_calendar_month(): void
    {
        $admin = User::factory()->admin()->create();
        $lastMonth = Date::today()->copy()->subMonthNoOverflow();
        Payment::factory()->create(['amount' => '100.00', 'received_at' => $lastMonth->copy()->startOfMonth()]);
        // Two months back — must not be counted as "previous".
        Payment::factory()->create(['amount' => '999.00', 'received_at' => $lastMonth->copy()->subMonthNoOverflow()]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/financial-summary');

        $response->assertJson(['collections_trend' => ['previous' => '100.00']]);
    }

    public function test_change_pct_is_null_when_previous_period_had_no_activity(): void
    {
        $admin = User::factory()->admin()->create();
        Payment::factory()->create(['amount' => '150.00', 'received_at' => Date::today()->startOfMonth()]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/financial-summary');

        $response->assertJson(['collections_trend' => ['change_pct' => null]]);
    }

    public function test_change_pct_reflects_a_real_increase_between_periods(): void
    {
        $admin = User::factory()->admin()->create();
        $lastMonth = Date::today()->copy()->subMonthNoOverflow();
        Payment::factory()->create(['amount' => '100.00', 'received_at' => $lastMonth->copy()->startOfMonth()]);
        Payment::factory()->create(['amount' => '150.00', 'received_at' => Date::today()->startOfMonth()]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/financial-summary');

        // (150 - 100) / 100 * 100 = 50.0%
        $response->assertJson(['collections_trend' => ['change_pct' => 50.0]]);
    }

    public function test_production_trend_reflects_issued_invoice_charges_in_current_period(): void
    {
        $admin = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create(['issue_date' => Date::today()->startOfMonth()]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'quantity' => 1, 'unit_amount' => '400.00']);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/financial-summary');

        $response->assertJson(['production_trend' => ['current' => '400.00']]);
    }

    public function test_ar_aging_reflects_report_service_ar_aging_summary_directly(): void
    {
        $admin = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create([
            'issue_date' => Date::today()->copy()->subMonth(),
            'due_date' => Date::today()->copy()->subDays(10),
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'quantity' => 1, 'unit_amount' => '500.00']);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/financial-summary');

        $response->assertJson(['ar_aging' => ['total' => '500.00']]);
    }
}
