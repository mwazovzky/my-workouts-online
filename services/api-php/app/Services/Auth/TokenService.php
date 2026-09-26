<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AccessTokenException;
use App\Exceptions\Auth\RefreshTokenException;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;

class TokenService implements TokenServiceInterface
{
    private readonly Eddsa $signer;

    private ?InMemory $privateKey = null;

    private ?InMemory $publicKey = null;

    /**
     * Keys are loaded lazily so that a missing configuration only fails the
     * operations that need them (signing, verifying, JWKS), never the
     * construction of the guard on an anonymous request.
     *
     * @param  array{issuer: string, audience: string, private_key: ?string, public_key: ?string, key_id: ?string, access_ttl: int, refresh_ttl: int, leeway: int}  $config
     */
    public function __construct(private readonly array $config)
    {
        $this->signer = new Eddsa;
    }

    public function hasKeys(): bool
    {
        return ! empty($this->config['private_key']) && ! empty($this->config['public_key']);
    }

    private function privateKey(): InMemory
    {
        return $this->privateKey ??= InMemory::base64Encoded($this->requireKey('private_key'));
    }

    private function publicKey(): InMemory
    {
        return $this->publicKey ??= InMemory::base64Encoded($this->requireKey('public_key'));
    }

    private function requireKey(string $name): string
    {
        if (! $this->hasKeys()) {
            throw new \RuntimeException('JWT keys are not configured. Run `php artisan jwt:keys` and set JWT_PRIVATE_KEY / JWT_PUBLIC_KEY.');
        }

        return (string) $this->config[$name];
    }

    public function issuePair(User $user, ?string $deviceName = null): TokenPair
    {
        $now = CarbonImmutable::now();

        return $this->createPair(
            user: $user,
            familyId: (string) Str::uuid(),
            deviceName: $deviceName,
            authenticatedAt: $now,
        );
    }

    public function refresh(string $plainRefreshToken): TokenPair
    {
        $current = RefreshToken::query()
            ->where('token_hash', RefreshToken::hashToken($plainRefreshToken))
            ->first();

        if (! $current) {
            throw RefreshTokenException::invalid();
        }

        if ($current->isRotated()) {
            $this->revokeFamily($current->family_id);

            throw RefreshTokenException::reused();
        }

        if ($current->isRevoked()) {
            throw RefreshTokenException::invalid();
        }

        if ($current->isExpired()) {
            throw RefreshTokenException::expired();
        }

        $reusedConcurrently = false;

        $pair = DB::transaction(function () use ($current, &$reusedConcurrently): ?TokenPair {
            $locked = RefreshToken::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();

            if ($locked->isRotated() || $locked->isRevoked()) {
                $reusedConcurrently = true;

                return null;
            }

            $pair = $this->createPair(
                user: $locked->user,
                familyId: $locked->family_id,
                deviceName: $locked->device_name,
                authenticatedAt: $locked->authenticated_at,
            );

            $replacement = RefreshToken::query()
                ->where('token_hash', RefreshToken::hashToken($pair->refreshToken))
                ->firstOrFail();

            $locked->update([
                'replaced_by_id' => $replacement->id,
                'revoked_at' => CarbonImmutable::now(),
            ]);

            return $pair;
        });

        if ($reusedConcurrently || $pair === null) {
            $this->revokeFamily($current->family_id);

            throw RefreshTokenException::reused();
        }

        return $pair;
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => CarbonImmutable::now()]);
    }

    public function revokeAllForUser(User $user, ?string $exceptFamilyId = null): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->when($exceptFamilyId, fn ($query) => $query->where('family_id', '!=', $exceptFamilyId))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => CarbonImmutable::now()]);
    }

    public function stepUp(User $user, string $familyId): string
    {
        $now = CarbonImmutable::now();

        RefreshToken::query()
            ->where('family_id', $familyId)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['authenticated_at' => $now]);

        return $this->buildAccessToken($user, $familyId, $now, $now);
    }

    public function parseAccessToken(string $jwt): AccessTokenClaims
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse($jwt);
        } catch (CannotDecodeContent|InvalidTokenStructure|UnsupportedHeaderFound) {
            throw AccessTokenException::invalid();
        }

        if (! $token instanceof Plain) {
            throw AccessTokenException::invalid();
        }

        try {
            (new Validator)->assert(
                $token,
                new SignedWith($this->signer, $this->publicKey()),
                new IssuedBy($this->config['issuer']),
                new PermittedFor($this->config['audience']),
            );
        } catch (RequiredConstraintsViolated) {
            throw AccessTokenException::invalid();
        }

        $claims = $token->claims();

        if (! $claims->has('sub') || ! $claims->has('exp') || ! $claims->has('fam') || ! $claims->has('auth_time')) {
            throw AccessTokenException::invalid();
        }

        $now = CarbonImmutable::now()->subSeconds($this->config['leeway']);

        if ($token->isExpired($now)) {
            throw AccessTokenException::expired();
        }

        return new AccessTokenClaims(
            userId: (int) $claims->get('sub'),
            familyId: (string) $claims->get('fam'),
            tokenId: (string) $claims->get('jti'),
            authenticatedAt: CarbonImmutable::createFromTimestamp((int) $claims->get('auth_time')),
            expiresAt: CarbonImmutable::instance($claims->get('exp')),
        );
    }

    public function jwks(): array
    {
        return [
            'keys' => [
                [
                    'kty' => 'OKP',
                    'crv' => 'Ed25519',
                    'alg' => 'EdDSA',
                    'use' => 'sig',
                    'kid' => $this->keyId(),
                    'x' => rtrim(strtr(base64_encode($this->publicKey()->contents()), '+/', '-_'), '='),
                ],
            ],
        ];
    }

    private function createPair(User $user, string $familyId, ?string $deviceName, CarbonImmutable $authenticatedAt): TokenPair
    {
        $now = CarbonImmutable::now();
        $refreshExpiresAt = $now->addSeconds($this->config['refresh_ttl']);
        $plainRefreshToken = bin2hex(random_bytes(32));

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'family_id' => $familyId,
            'token_hash' => RefreshToken::hashToken($plainRefreshToken),
            'device_name' => $deviceName,
            'authenticated_at' => $authenticatedAt,
            'expires_at' => $refreshExpiresAt,
        ]);

        return new TokenPair(
            accessToken: $this->buildAccessToken($user, $familyId, $authenticatedAt, $now),
            expiresIn: $this->config['access_ttl'],
            refreshToken: $plainRefreshToken,
            refreshExpiresAt: $refreshExpiresAt,
        );
    }

    private function buildAccessToken(User $user, string $familyId, CarbonImmutable $authenticatedAt, CarbonImmutable $now): string
    {
        $builder = (new Builder(new JoseEncoder, ChainedFormatter::default()))
            ->withHeader('kid', $this->keyId())
            ->issuedBy($this->config['issuer'])
            ->permittedFor($this->config['audience'])
            ->relatedTo((string) $user->id)
            ->identifiedBy((string) Str::uuid())
            ->issuedAt($now)
            ->expiresAt($now->addSeconds($this->config['access_ttl']))
            ->withClaim('auth_time', $authenticatedAt->getTimestamp())
            ->withClaim('fam', $familyId);

        return $builder->getToken($this->signer, $this->privateKey())->toString();
    }

    private function keyId(): string
    {
        return $this->config['key_id'] ?: substr(hash('sha256', $this->publicKey()->contents()), 0, 16);
    }
}
