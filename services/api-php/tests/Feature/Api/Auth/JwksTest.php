<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JwksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function publishes_the_ed25519_public_key(): void
    {
        $response = $this->getJson('/.well-known/jwks.json');

        $response->assertOk()
            ->assertJsonCount(1, 'keys')
            ->assertJsonPath('keys.0.kty', 'OKP')
            ->assertJsonPath('keys.0.crv', 'Ed25519')
            ->assertJsonPath('keys.0.alg', 'EdDSA')
            ->assertJsonPath('keys.0.use', 'sig')
            ->assertJsonStructure(['keys' => [['kid', 'x']]]);

        $publicKey = base64_decode(strtr($response->json('keys.0.x'), '-_', '+/').'==');
        $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($publicKey));
        $this->assertSame(base64_decode(config('jwt.public_key')), $publicKey);
    }

    #[Test]
    public function access_token_verifies_against_the_published_key(): void
    {
        $user = User::factory()->create();
        $jwt = app(TokenServiceInterface::class)->issuePair($user)->accessToken;

        $publicKey = base64_decode(strtr($this->getJson('/.well-known/jwks.json')->json('keys.0.x'), '-_', '+/').'==');
        $kid = $this->getJson('/.well-known/jwks.json')->json('keys.0.kid');

        [$header, $payload, $signature] = explode('.', $jwt);
        $decodedHeader = json_decode(base64_decode(strtr($header, '-_', '+/')), true);

        $this->assertSame('EdDSA', $decodedHeader['alg']);
        $this->assertSame($kid, $decodedHeader['kid']);
        $this->assertTrue(sodium_crypto_sign_verify_detached(
            base64_decode(strtr($signature, '-_', '+/').'=='),
            "{$header}.{$payload}",
            $publicKey,
        ));
    }
}
