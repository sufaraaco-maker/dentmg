<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_users(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_user_can_list_users(): void
    {
        $actor = User::factory()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/users');

        $response->assertOk();
        $this->assertCount(4, $response->json('data'));
    }

    public function test_list_users_can_be_searched_by_name_or_email(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['name' => 'Ahmed Dentist', 'email' => 'ahmed@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'else@example.com']);

        $response = $this->actingAs($actor)->getJson('/api/users?search=ahmed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Ahmed Dentist', $response->json('data.0.name'));
    }

    public function test_any_authenticated_user_can_view_a_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/users/{$target->id}");

        $response->assertOk()->assertJson(['id' => $target->id, 'email' => $target->email]);
    }

    public function test_admin_can_create_a_user(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/users', [
            'name' => 'New Staff',
            'email' => 'new-staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'receptionist',
        ]);

        $response->assertCreated()->assertJson(['role' => 'receptionist']);
        $this->assertDatabaseHas('users', ['email' => 'new-staff@example.com', 'role' => 'receptionist']);
    }

    public function test_non_admin_cannot_create_a_user(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/users', [
            'name' => 'New Staff',
            'email' => 'new-staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'receptionist',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new-staff@example.com']);
    }

    public function test_create_user_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/users', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'role' => 'not-a-role',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_create_user_rejects_duplicate_email(): void
    {
        $actor = User::factory()->admin()->create();
        $existing = User::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/users', [
            'name' => 'Someone',
            'email' => $existing->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'receptionist',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_can_update_a_user(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
            'role' => 'dentist',
        ]);

        $response->assertOk()->assertJson(['name' => 'Updated Name', 'role' => 'dentist']);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Name', 'role' => 'dentist']);
    }

    public function test_non_admin_cannot_update_a_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['name' => 'Original Name']);

        $response = $this->actingAs($actor)->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Original Name']);
    }

    public function test_updating_email_rejects_duplicate(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/users/{$target->id}", [
            'email' => $other->email,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_can_delete_another_user(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/users/{$target->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_non_admin_cannot_delete_a_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/users/{$target->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/users/{$actor->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $actor->id, 'deleted_at' => null]);
    }

    // User avatar upload (2026-08-13) — always the `public` disk, same pattern as the clinic logo
    // (ClinicSettingTest), gated by the same `users.manage` policy as every other user-management action.

    public function test_admin_can_upload_a_users_avatar(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/users/{$target->id}/avatar", [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('avatar_url'));
        $target->refresh();
        Storage::disk('public')->assertExists($target->avatar_path);
    }

    public function test_uploading_a_new_avatar_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)->postJson("/api/users/{$target->id}/avatar", [
            'avatar' => UploadedFile::fake()->image('first.png'),
        ])->assertOk();
        $firstPath = $target->refresh()->avatar_path;

        $this->actingAs($actor)->postJson("/api/users/{$target->id}/avatar", [
            'avatar' => UploadedFile::fake()->image('second.png'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($target->refresh()->avatar_path);
    }

    public function test_admin_can_remove_a_users_avatar(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)->postJson("/api/users/{$target->id}/avatar", [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertOk();
        $path = $target->refresh()->avatar_path;

        $response = $this->actingAs($actor)->deleteJson("/api/users/{$target->id}/avatar");

        $response->assertOk();
        $this->assertNull($response->json('avatar_url'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_admin_cannot_upload_a_users_avatar(): void
    {
        Storage::fake('public');
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/users/{$target->id}/avatar", [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $response->assertForbidden();
    }

    public function test_avatar_upload_rejects_a_non_image_file(): void
    {
        Storage::fake('public');
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/users/{$target->id}/avatar", [
            'avatar' => UploadedFile::fake()->create('avatar.pdf', 100),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['avatar']);
    }

    public function test_soft_deleted_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $user->delete();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }
}
