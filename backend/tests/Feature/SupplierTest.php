<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_suppliers(): void
    {
        $response = $this->getJson('/api/suppliers');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_suppliers(): void
    {
        $actor = User::factory()->dentist()->create();
        Supplier::factory()->count(2)->create();

        $response = $this->actingAs($actor)->getJson('/api/suppliers');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_can_create_a_supplier(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/suppliers', [
            'name' => 'Henry Schein',
            'email' => 'orders@henryschein.example',
        ]);

        $response->assertCreated();
        $this->assertSame('Henry Schein', $response->json('name'));
        $this->assertTrue($response->json('is_active'));
    }

    public function test_receptionist_cannot_create_a_supplier(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson('/api/suppliers', ['name' => 'Patterson']);

        $response->assertForbidden();
    }

    public function test_dentist_cannot_create_a_supplier(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson('/api/suppliers', ['name' => 'Patterson']);

        $response->assertForbidden();
    }

    public function test_admin_can_deactivate_a_supplier(): void
    {
        $actor = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertNoContent();
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_receptionist_cannot_deactivate_a_supplier(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertForbidden();
    }
}
