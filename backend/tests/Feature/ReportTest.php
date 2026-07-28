<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string}>
     */
    public static function financialEndpoints(): array
    {
        return [
            ['/api/reports/production?date_from=2026-06-01&date_to=2026-06-30'],
            ['/api/reports/collections?date_from=2026-06-01&date_to=2026-06-30'],
            ['/api/reports/ar-aging'],
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function operationalEndpoints(): array
    {
        return [
            ['/api/reports/appointments?date_from=2026-06-01&date_to=2026-06-30'],
            ['/api/reports/treatment-plan-acceptance?date_from=2026-06-01&date_to=2026-06-30'],
            ['/api/reports/new-patients?date_from=2026-06-01&date_to=2026-06-30'],
        ];
    }

    #[DataProvider('financialEndpoints')]
    public function test_financial_reports_are_admin_only(string $url): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create();

        $this->actingAs($admin)->getJson($url)->assertOk();
        $this->actingAs($dentist)->getJson($url)->assertForbidden();
        $this->actingAs($receptionist)->getJson($url)->assertForbidden();
    }

    #[DataProvider('operationalEndpoints')]
    public function test_operational_reports_are_open_to_every_role(string $url): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create();

        $this->actingAs($admin)->getJson($url)->assertOk();
        $this->actingAs($dentist)->getJson($url)->assertOk();
        $this->actingAs($receptionist)->getJson($url)->assertOk();
    }

    #[DataProvider('financialEndpoints')]
    public function test_guest_cannot_view_any_report(string $url): void
    {
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_production_report_returns_summary_and_rows_shape(): void
    {
        $admin = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create(['issue_date' => '2026-06-15']);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => '80.00']);

        $response = $this->actingAs($admin)
            ->getJson('/api/reports/production?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk()->assertJsonStructure(['summary' => ['total', 'by_dentist'], 'rows']);
        $response->assertJsonPath('summary.total', '80.00');
    }

    public function test_production_report_rejects_an_invalid_date_range(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/reports/production?date_from=2026-06-30&date_to=2026-06-01')
            ->assertUnprocessable();
    }

    public function test_collections_report_supports_csv_export(): void
    {
        $admin = User::factory()->admin()->create();
        Payment::factory()->create(['amount' => '50.00', 'received_at' => '2026-06-10']);

        $response = $this->actingAs($admin)
            ->get('/api/reports/collections?date_from=2026-06-01&date_to=2026-06-30&format=csv');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_new_patients_report_counts_registrations_in_range(): void
    {
        $admin = User::factory()->admin()->create();
        Patient::factory()->create(['created_at' => '2026-06-05']);
        Patient::factory()->create(['created_at' => '2026-05-01']);

        $response = $this->actingAs($admin)
            ->getJson('/api/reports/new-patients?date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk()->assertJsonPath('summary.total', 1);
    }
}
