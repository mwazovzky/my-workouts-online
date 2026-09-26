<?php

namespace Database\Factories;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RefreshToken>
 */
class RefreshTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'family_id' => (string) Str::uuid(),
            'token_hash' => RefreshToken::hashToken(bin2hex(random_bytes(32))),
            'device_name' => fake()->word(),
            'authenticated_at' => now(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
            'replaced_by_id' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
