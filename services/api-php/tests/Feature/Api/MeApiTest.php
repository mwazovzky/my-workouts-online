<?php

namespace Tests\Feature\Api;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // GET /api/v1/me
    // -------------------------------------------------------

    #[Test]
    public function returns_the_bearer_user(): void
    {
        $user = User::factory()->create(['locale' => 'ru', 'theme_preference' => 'dark']);
        $token = app(TokenServiceInterface::class)->issuePair($user)->accessToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'ru',
                'theme_preference' => 'dark',
                'is_admin' => false,
                'email_verified_at' => $user->email_verified_at->toJSON(),
            ]]);
    }

    #[Test]
    public function reports_admin_role_and_unverified_email(): void
    {
        $admin = User::factory()->admin()->unverified()->create();

        $this->actingAs($admin)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.email_verified_at', null);
    }

    #[Test]
    public function new_users_default_to_english_and_system_theme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.theme_preference', 'system');
    }

    // -------------------------------------------------------
    // PATCH /api/v1/me (merge patch)
    // -------------------------------------------------------

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function singleFieldPatches(): array
    {
        return [
            'name' => [['name' => 'New Name']],
            'email' => [['email' => 'new@example.com']],
            'locale' => [['locale' => 'ru']],
            'theme' => [['theme_preference' => 'dark']],
        ];
    }

    /**
     * @param  array<string, string>  $patch
     */
    #[Test]
    #[DataProvider('singleFieldPatches')]
    public function patch_changes_only_the_fields_sent(array $patch): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'locale' => 'en',
            'theme_preference' => 'system',
        ]);
        $before = $user->only(['name', 'email', 'locale', 'theme_preference']);

        $this->actingAs($user)->patchJson('/api/v1/me', $patch)
            ->assertOk()
            ->assertJsonPath('data.'.array_key_first($patch), reset($patch));

        $this->assertSame(array_merge($before, $patch), $user->fresh()->only(['name', 'email', 'locale', 'theme_preference']));
    }

    #[Test]
    public function patch_applies_several_fields_together(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/me', [
            'name' => 'Ada',
            'locale' => 'ru',
            'theme_preference' => 'light',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ada')
            ->assertJsonPath('data.locale', 'ru')
            ->assertJsonPath('data.theme_preference', 'light');
    }

    #[Test]
    public function patch_with_an_empty_body_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/me', [])
            ->assertFieldErrors(['name'], 'required');
    }

    #[Test]
    public function patch_rejects_unknown_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Ada', 'nickname' => 'ada'])
            ->assertFieldErrors(['nickname'], 'unknown_field');
    }

    #[Test]
    public function changing_the_email_unverifies_the_account(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patchJson('/api/v1/me', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email_verified_at', null);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function keeping_the_same_email_keeps_the_account_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Updated', 'email' => $user->email])
            ->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function patch_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/me', ['email' => 'taken@example.com'])
            ->assertFieldErrors(['email'], 'not_unique');
    }

    #[Test]
    public function patch_rejects_a_non_string_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/me', ['email' => [['nested' => 1]]])
            ->assertFieldErrors(['email'], 'invalid_format');
    }

    #[Test]
    public function patch_rejects_an_unknown_locale_or_theme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/me', ['locale' => 'fr'])
            ->assertFieldErrors(['locale'], 'invalid_value');

        $this->actingAs($user)->patchJson('/api/v1/me', ['theme_preference' => 'purple'])
            ->assertFieldErrors(['theme_preference'], 'invalid_value');

        $this->actingAs($user)->patchJson('/api/v1/me', ['locale' => ''])
            ->assertFieldErrors(['locale'], 'required');
    }

    // -------------------------------------------------------
    // PUT /api/v1/me/password
    // -------------------------------------------------------

    #[Test]
    public function password_can_be_changed(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    #[Test]
    public function password_change_revokes_other_sessions_but_keeps_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $tokens = app(TokenServiceInterface::class);
        $current = $tokens->issuePair($user);
        $other = $tokens->issuePair($user);

        $this->withToken($current->accessToken)->putJson('/api/v1/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
        ])->assertNoContent();

        $this->assertNull(RefreshToken::query()->where('token_hash', RefreshToken::hashToken($current->refreshToken))->value('revoked_at'));
        $this->assertNotNull(RefreshToken::query()->where('token_hash', RefreshToken::hashToken($other->refreshToken))->value('revoked_at'));
    }

    #[Test]
    public function a_wrong_current_password_is_a_mismatch(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
        ])->assertFieldErrors(['current_password'], 'mismatch');
    }

    #[Test]
    public function a_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'old-password',
            'password' => 'short',
        ])->assertFieldErrors(['password']);
    }

    #[Test]
    public function password_change_no_longer_accepts_a_confirmation_field(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertFieldErrors(['password_confirmation'], 'unknown_field');
    }

    #[Test]
    public function password_change_rejects_a_non_string_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => [null, null],
            'password' => 'new-password-123',
        ])->assertFieldErrors(['current_password']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/me
    // -------------------------------------------------------

    #[Test]
    public function account_can_be_deleted_and_every_session_is_revoked(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        app(TokenServiceInterface::class)->issuePair($user);

        $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'password'])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('refresh_tokens', ['user_id' => $user->id, 'revoked_at' => null]);
    }

    #[Test]
    public function a_wrong_password_prevents_deletion(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'wrong-password'])
            ->assertFieldErrors(['password'], 'mismatch');

        $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => ['x']])
            ->assertFieldErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // -------------------------------------------------------
    // Access
    // -------------------------------------------------------

    #[Test]
    public function every_me_route_requires_authentication(): void
    {
        $this->assertProblem($this->getJson('/api/v1/me'), 401, 'unauthenticated');
        $this->assertProblem($this->patchJson('/api/v1/me', ['name' => 'X']), 401, 'unauthenticated');
        $this->assertProblem($this->putJson('/api/v1/me/password', ['current_password' => 'a', 'password' => 'b']), 401, 'unauthenticated');
        $this->assertProblem($this->deleteJson('/api/v1/me', ['password' => 'a']), 401, 'unauthenticated');
    }

    #[Test]
    public function the_retired_profile_routes_are_gone(): void
    {
        $user = User::factory()->create();

        $this->assertProblem($this->actingAs($user)->patchJson('/api/v1/profile', ['name' => 'X']), 404, 'not_found');
        $this->assertProblem($this->actingAs($user)->deleteJson('/api/v1/profile', ['password' => 'x']), 404, 'not_found');
        $this->assertProblem($this->actingAs($user)->putJson('/api/v1/profile/password', []), 404, 'not_found');
        $this->assertProblem($this->actingAs($user)->patchJson('/api/v1/profile/locale', ['locale' => 'ru']), 404, 'not_found');
        $this->assertProblem($this->actingAs($user)->patchJson('/api/v1/profile/theme', ['theme_preference' => 'dark']), 404, 'not_found');
    }
}
