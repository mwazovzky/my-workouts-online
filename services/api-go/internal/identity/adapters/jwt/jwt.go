// Package jwt signs and verifies the Ed25519 (EdDSA) access tokens with
// lestrrat-go/jwx and publishes the key as a JWKS document. Tokens carry the
// PHP API's claims (iss, aud, sub as a string, jti, iat, exp, auth_time, fam)
// and kid, so a token from either backend verifies against the other's
// JWKS for the same key pair.
package jwt

import (
	"crypto/ed25519"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"errors"
	"fmt"
	"strconv"
	"time"

	"github.com/lestrrat-go/jwx/v3/jwa"
	"github.com/lestrrat-go/jwx/v3/jws"
	jwxjwt "github.com/lestrrat-go/jwx/v3/jwt"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
)

// ErrKeysMissing fails every token operation when no key pair is
// configured; public routes keep working and readiness reports jwt down.
var ErrKeysMissing = errors.New("JWT keys are not configured: run `workouts keys generate` and set JWT_PRIVATE_KEY and JWT_PUBLIC_KEY")

// Keys is the signing key pair and its kid.
type Keys struct {
	private ed25519.PrivateKey
	public  ed25519.PublicKey
	kid     string
}

// ParseKeys decodes the base64 raw keys `workouts keys generate` and
// `php artisan jwt:keys` print: the 64-byte secret key (seed and public
// key) and the 32-byte public key. kid is keyID, or else the first 16 hex
// characters of the public key's SHA-256, as the PHP TokenService derives
// it. With both keys empty it returns nil Keys and no error.
func ParseKeys(privateB64, publicB64, keyID string) (*Keys, error) {
	if privateB64 == "" && publicB64 == "" {
		return nil, nil //nolint:nilnil // no keys is a valid, degraded configuration
	}

	private, err := base64.StdEncoding.DecodeString(privateB64)
	if err != nil || len(private) != ed25519.PrivateKeySize {
		return nil, fmt.Errorf("JWT_PRIVATE_KEY must be %d base64-encoded bytes", ed25519.PrivateKeySize)
	}

	public, err := base64.StdEncoding.DecodeString(publicB64)
	if err != nil || len(public) != ed25519.PublicKeySize {
		return nil, fmt.Errorf("JWT_PUBLIC_KEY must be %d base64-encoded bytes", ed25519.PublicKeySize)
	}

	if !ed25519.PublicKey(public).Equal(ed25519.PrivateKey(private).Public()) {
		return nil, errors.New("JWT_PUBLIC_KEY does not belong to JWT_PRIVATE_KEY")
	}

	if keyID == "" {
		sum := sha256.Sum256(public)
		keyID = hex.EncodeToString(sum[:])[:16]
	}

	return &Keys{private: private, public: public, kid: keyID}, nil
}

// GenerateKeys returns a new key pair in the env format.
func GenerateKeys() (privateB64, publicB64 string, err error) {
	public, private, err := ed25519.GenerateKey(nil)
	if err != nil {
		return "", "", fmt.Errorf("generate key: %w", err)
	}

	return base64.StdEncoding.EncodeToString(private), base64.StdEncoding.EncodeToString(public), nil
}

// Config configures a Signer.
type Config struct {
	Keys     *Keys
	Issuer   string
	Audience string
	// Leeway is the clock skew tolerated on exp (JWT_LEEWAY).
	Leeway time.Duration
	Clock  clock.Clock
}

// Signer implements ports.TokenSigner.
type Signer struct {
	cfg Config
}

var _ ports.TokenSigner = (*Signer)(nil)

// NewSigner returns a Signer; with nil Keys every operation fails with
// ErrKeysMissing.
func NewSigner(cfg Config) *Signer {
	return &Signer{cfg: cfg}
}

// Ready is the readiness check: an error while no key pair is configured.
func (s *Signer) Ready() error {
	if s.cfg.Keys == nil {
		return ErrKeysMissing
	}

	return nil
}

// Sign implements ports.TokenSigner.
func (s *Signer) Sign(c domain.AccessClaims) (string, error) {
	if s.cfg.Keys == nil {
		return "", ErrKeysMissing
	}

	token, err := jwxjwt.NewBuilder().
		Issuer(s.cfg.Issuer).
		Audience([]string{s.cfg.Audience}).
		Subject(strconv.FormatInt(c.UserID, 10)).
		JwtID(c.TokenID).
		IssuedAt(c.IssuedAt).
		Expiration(c.ExpiresAt).
		Claim("auth_time", c.AuthTime.Unix()).
		Claim("fam", c.FamilyID).
		Build()
	if err != nil {
		return "", fmt.Errorf("jwt: %w", err)
	}

	// One audience is a string, not an array, as lcobucci/jwt writes it.
	token.Options().Enable(jwxjwt.FlattenAudience)

	headers := jws.NewHeaders()
	if err := headers.Set(jws.KeyIDKey, s.cfg.Keys.kid); err != nil {
		return "", fmt.Errorf("jwt: %w", err)
	}

	if err := headers.Set(jws.TypeKey, "JWT"); err != nil {
		return "", fmt.Errorf("jwt: %w", err)
	}

	signed, err := jwxjwt.Sign(token, jwxjwt.WithKey(jwa.EdDSA(), s.cfg.Keys.private, jws.WithProtectedHeaders(headers)))
	if err != nil {
		return "", fmt.Errorf("jwt: %w", err)
	}

	return string(signed), nil
}

// Verify implements ports.TokenSigner.
func (s *Signer) Verify(raw string) (domain.AccessClaims, error) {
	if s.cfg.Keys == nil {
		return domain.AccessClaims{}, ErrKeysMissing
	}

	token, err := jwxjwt.Parse([]byte(raw),
		jwxjwt.WithKey(jwa.EdDSA(), s.cfg.Keys.public),
		jwxjwt.WithIssuer(s.cfg.Issuer),
		jwxjwt.WithAudience(s.cfg.Audience),
		jwxjwt.WithClock(jwxjwt.ClockFunc(s.cfg.Clock.Now)),
		jwxjwt.WithAcceptableSkew(s.cfg.Leeway),
		jwxjwt.WithRequiredClaim(jwxjwt.SubjectKey),
		jwxjwt.WithRequiredClaim(jwxjwt.ExpirationKey),
		jwxjwt.WithRequiredClaim("fam"),
		jwxjwt.WithRequiredClaim("auth_time"),
	)
	if errors.Is(err, jwxjwt.TokenExpiredError()) {
		return domain.AccessClaims{}, domain.ErrAccessTokenExpired()
	}

	if err != nil {
		return domain.AccessClaims{}, domain.ErrUnauthenticated()
	}

	return claimsOf(token)
}

func claimsOf(token jwxjwt.Token) (domain.AccessClaims, error) {
	var (
		c        domain.AccessClaims
		subject  string
		family   string
		authTime float64
	)

	subject, _ = token.Subject()
	userID, err := strconv.ParseInt(subject, 10, 64)
	if err != nil {
		return c, domain.ErrUnauthenticated()
	}

	if err := token.Get("fam", &family); err != nil {
		return c, domain.ErrUnauthenticated()
	}

	if err := token.Get("auth_time", &authTime); err != nil {
		return c, domain.ErrUnauthenticated()
	}

	c.UserID = userID
	c.FamilyID = family
	c.TokenID, _ = token.JwtID()
	c.AuthTime = time.Unix(int64(authTime), 0).UTC()
	c.IssuedAt, _ = token.IssuedAt()
	c.ExpiresAt, _ = token.Expiration()

	return c, nil
}

// JWKS returns the published key set: the public key as an OKP Ed25519 JWK
// with kid, alg EdDSA and use sig.
func (s *Signer) JWKS() ([]map[string]any, error) {
	if s.cfg.Keys == nil {
		return nil, ErrKeysMissing
	}

	return []map[string]any{{
		"kty": "OKP",
		"crv": "Ed25519",
		"alg": "EdDSA",
		"use": "sig",
		"kid": s.cfg.Keys.kid,
		"x":   base64.RawURLEncoding.EncodeToString(s.cfg.Keys.public),
	}}, nil
}
