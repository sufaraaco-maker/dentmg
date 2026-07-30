<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_view_own_profile(): void
    {
        $actor = User::factory()->dentist()->create(['name' => 'Dr. Ada Lovelace']);

        $response = $this->actingAs($actor)->getJson('/api/profile');

        $response->assertOk();
        $this->assertSame('Dr. Ada Lovelace', $response->json('name'));
    }

    public function test_a_user_can_update_their_own_name_and_email(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ]);

        $response->assertOk();
        $this->assertSame('New Name', $response->json('name'));
        $this->assertSame('new-email@example.com', $response->json('email'));
        $this->assertDatabaseHas('users', ['id' => $actor->id, 'name' => 'New Name']);
    }

    public function test_a_user_cannot_change_their_own_role_via_profile(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->putJson('/api/profile', [
            'name' => $actor->name,
            'role' => 'admin',
        ]);

        $response->assertOk();
        $this->assertSame('receptionist', $actor->fresh()->role->value);
    }

    public function test_updating_profile_email_must_be_unique(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/profile', ['email' => 'taken@example.com']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_a_user_can_change_their_own_password_with_correct_current_password(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('new-secure-password-1', $actor->fresh()->password));
    }

    public function test_changing_password_fails_with_wrong_current_password(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['current_password']);
        $this->assertTrue(Hash::check('password', $actor->fresh()->password));
    }

    public function test_changing_password_requires_password_confirmation_to_match(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }
}
