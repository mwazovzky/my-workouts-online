<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // PATCH /api/v1/profile
    // -------------------------------------------------------

    #[Test]
    public function authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    #[Test]
    public function updating_email_clears_email_verified_at(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => $user->name,
            'email' => 'new@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'email_verified_at' => null,
        ]);
    }

    #[Test]
    public function keeping_same_email_does_not_clear_email_verified_at(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function update_profile_requires_name_and_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    #[Test]
    public function update_profile_rejects_duplicate_email(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => 'Name',
            'email' => 'taken@example.com',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function guest_cannot_update_profile(): void
    {
        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Name',
            'email' => 'email@example.com',
        ]);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // PUT /api/v1/profile/password
    // -------------------------------------------------------

    #[Test]
    public function authenticated_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    #[Test]
    public function wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['current_password']);
    }

    #[Test]
    public function password_confirmation_mismatch_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function guest_cannot_update_password(): void
    {
        $response = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // PATCH /api/v1/profile/locale
    // -------------------------------------------------------

    #[Test]
    public function authenticated_user_can_update_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/locale', [
            'locale' => 'ru',
        ]);

        $response->assertOk();
        $this->assertSame('ru', $user->fresh()->locale);
    }

    #[Test]
    public function invalid_locale_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/locale', [
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function guest_cannot_update_locale(): void
    {
        $response = $this->patchJson('/api/v1/profile/locale', ['locale' => 'en']);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // PATCH /api/v1/profile/theme
    // -------------------------------------------------------

    #[Test]
    public function authenticated_user_can_update_theme(): void
    {
        $user = User::factory()->create(['theme_preference' => 'system']);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/theme', [
            'theme_preference' => 'dark',
        ]);

        $response->assertOk();
        $this->assertSame('dark', $user->fresh()->theme_preference);
    }

    #[Test]
    public function invalid_theme_preference_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/theme', [
            'theme_preference' => 'purple',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['theme_preference']);
    }

    #[Test]
    public function guest_cannot_update_theme(): void
    {
        $response = $this->patchJson('/api/v1/profile/theme', ['theme_preference' => 'dark']);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // DELETE /api/v1/profile
    // -------------------------------------------------------

    #[Test]
    public function authenticated_user_can_delete_account(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/profile', [
            'password' => 'password',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function wrong_password_prevents_account_deletion(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/profile', [
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function guest_cannot_delete_account(): void
    {
        $response = $this->deleteJson('/api/v1/profile', ['password' => 'password']);

        $response->assertUnauthorized();
    }
}
