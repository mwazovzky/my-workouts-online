package jwt_test

import (
	"encoding/base64"
	"encoding/json"
	"os"
	"reflect"
	"strings"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/jwt"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
)

// The key pair in services/api-php/phpunit.xml. testdata/php-access-token.txt
// and testdata/php-jwks.json were produced by the PHP TokenService with it:
// user 7, issued 2026-10-11T09:00:00.25Z, auth_time five minutes earlier.
const (
	testPrivateKey = "SNdNP8JHR4IZdp8IesFeqOLNQr2l8U05g/h7h4l2ho8nsYzYswZYM40Mn8Ez9bAYRKT96SVgnnDdHOK7M+khjg=="
	testPublicKey  = "J7GM2LMGWDONDJ/BM/WwGESk/eklYJ5w3RziuzPpIY4="
)

var phpIssuedAt = time.Date(2026, 10, 11, 9, 0, 0, 250_000_000, time.UTC)

func newSigner(t *testing.T, clk clock.Clock) *jwt.Signer {
	t.Helper()

	keys, err := jwt.ParseKeys(testPrivateKey, testPublicKey, "")
	if err != nil {
		t.Fatal(err)
	}

	return jwt.NewSigner(jwt.Config{Keys: keys, Issuer: "http://localhost", Audience: "my-workouts-test", Leeway: 30 * time.Second, Clock: clk})
}

func readFile(t *testing.T, name string) string {
	t.Helper()

	data, err := os.ReadFile("testdata/" + name)
	if err != nil {
		t.Fatal(err)
	}

	return strings.TrimSpace(string(data))
}

func TestVerifiesATokenThePHPAPISigned(t *testing.T) {
	t.Parallel()

	claims, err := newSigner(t, clock.NewFake(phpIssuedAt.Add(time.Minute))).Verify(readFile(t, "php-access-token.txt"))
	if err != nil {
		t.Fatalf("Verify(PHP token) error = %v", err)
	}

	want := domain.AccessClaims{
		UserID:    7,
		FamilyID:  "0f8fad5b-d9cb-469f-a165-70867728950e",
		TokenID:   "6fa54597-74e9-4b2d-af69-57539d2d11fb",
		AuthTime:  time.Date(2026, 10, 11, 8, 55, 0, 0, time.UTC),
		IssuedAt:  phpIssuedAt.Truncate(time.Second),
		ExpiresAt: phpIssuedAt.Add(15 * time.Minute).Truncate(time.Second),
	}
	if diff := cmp.Diff(want, claims); diff != "" {
		t.Errorf("claims (-want +got):\n%s", diff)
	}
}

// A Go token has the PHP token's header and claim names, with the same JSON
// types (iat and exp are numbers in both, fractional in PHP).
func TestSignsTheSameShapeAsThePHPAPI(t *testing.T) {
	t.Parallel()

	signer := newSigner(t, clock.NewFake(phpIssuedAt))

	token, err := signer.Sign(domain.AccessClaims{
		UserID: 7, FamilyID: "0f8fad5b-d9cb-469f-a165-70867728950e", TokenID: "id",
		AuthTime: phpIssuedAt.Add(-5 * time.Minute), IssuedAt: phpIssuedAt, ExpiresAt: phpIssuedAt.Add(15 * time.Minute),
	})
	if err != nil {
		t.Fatal(err)
	}

	php := readFile(t, "php-access-token.txt")

	for i, part := range []string{"header", "claims"} {
		goShape, phpShape := shape(t, token, i), shape(t, php, i)
		if diff := cmp.Diff(phpShape, goShape); diff != "" {
			t.Errorf("%s names and types differ (-php +go):\n%s", part, diff)
		}
	}

	header := decodePart(t, token, 0)
	if header["kid"] != "6ea67caa64eb2e85" || header["alg"] != "EdDSA" {
		t.Errorf("header = %v, want the PHP kid and EdDSA", header)
	}

	if claims := decodePart(t, token, 1); claims["aud"] != "my-workouts-test" || claims["sub"] != "7" {
		t.Errorf("claims = %v, want aud as a string and sub as a string", claims)
	}

	if _, err := signer.Verify(token); err != nil {
		t.Errorf("Verify(own token) error = %v", err)
	}
}

func TestJWKSMatchesThePHPAPI(t *testing.T) {
	t.Parallel()

	keys, err := newSigner(t, clock.System{}).JWKS()
	if err != nil {
		t.Fatal(err)
	}

	var php struct {
		Keys []map[string]any `json:"keys"`
	}
	if err := json.Unmarshal([]byte(readFile(t, "php-jwks.json")), &php); err != nil {
		t.Fatal(err)
	}

	if diff := cmp.Diff(php.Keys, keys); diff != "" {
		t.Errorf("JWKS (-php +go):\n%s", diff)
	}
}

func TestVerifyFailures(t *testing.T) {
	t.Parallel()

	php := readFile(t, "php-access-token.txt")
	parts := strings.Split(php, ".")

	tampered := decodePart(t, php, 1)
	tampered["sub"] = "8"
	tamperedPayload, _ := json.Marshal(tampered)
	forged := parts[0] + "." + base64.RawURLEncoding.EncodeToString(tamperedPayload) + "." + parts[2]

	tests := []struct {
		name  string
		token string
		now   time.Time
		code  apperr.Code
	}{
		{"within the leeway", php, phpIssuedAt.Add(15*time.Minute + 20*time.Second), ""},
		{"expired past the leeway", php, phpIssuedAt.Add(16 * time.Minute), apperr.CodeTokenExpired},
		{"tampered", forged, phpIssuedAt, apperr.CodeUnauthenticated},
		{"garbage", "not-a-jwt", phpIssuedAt, apperr.CodeUnauthenticated},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			_, err := newSigner(t, clock.NewFake(tt.now)).Verify(tt.token)

			var got apperr.Code
			if appErr, ok := apperr.As(err); ok {
				got = appErr.Code
			} else if err != nil {
				t.Fatalf("Verify() error = %v, want an apperr", err)
			}

			if got != tt.code {
				t.Errorf("code = %q, want %q", got, tt.code)
			}
		})
	}

	// The PHP token's audience is my-workouts-test; a signer for another
	// audience rejects it.
	keys, _ := jwt.ParseKeys(testPrivateKey, testPublicKey, "")
	other := jwt.NewSigner(jwt.Config{Keys: keys, Issuer: "http://localhost", Audience: "my-workouts", Clock: clock.NewFake(phpIssuedAt)})

	if _, err := other.Verify(php); !apperr.HasKind(err, apperr.KindUnauthenticated) {
		t.Errorf("wrong audience: error = %v, want unauthenticated", err)
	}
}

func TestParseKeys(t *testing.T) {
	t.Parallel()

	keys, err := jwt.ParseKeys("", "", "")
	if keys != nil || err != nil {
		t.Errorf("no keys = %v, %v; want nil, nil", keys, err)
	}

	other, _, _ := jwt.GenerateKeys()

	for _, tt := range []struct{ private, public string }{
		{"not base64!", testPublicKey},
		{testPrivateKey, base64.StdEncoding.EncodeToString([]byte("short"))},
		{other, testPublicKey},
	} {
		if _, err := jwt.ParseKeys(tt.private, tt.public, ""); err == nil {
			t.Errorf("ParseKeys(%q, %q) error = nil", tt.private, tt.public)
		}
	}

	keys, err = jwt.ParseKeys(testPrivateKey, testPublicKey, "custom")
	if err != nil {
		t.Fatal(err)
	}

	jwks, _ := jwt.NewSigner(jwt.Config{Keys: keys}).JWKS()
	if jwks[0]["kid"] != "custom" {
		t.Errorf("kid = %v, want the JWT_KEY_ID override", jwks[0]["kid"])
	}
}

func TestGeneratedKeysParse(t *testing.T) {
	t.Parallel()

	private, public, err := jwt.GenerateKeys()
	if err != nil {
		t.Fatal(err)
	}

	if _, err := jwt.ParseKeys(private, public, ""); err != nil {
		t.Errorf("ParseKeys(generated) error = %v", err)
	}
}

func TestWithoutKeysEveryOperationFails(t *testing.T) {
	t.Parallel()

	signer := jwt.NewSigner(jwt.Config{Clock: clock.System{}})

	if err := signer.Ready(); err == nil {
		t.Error("Ready() = nil without keys")
	}

	if _, err := signer.Sign(domain.AccessClaims{}); err == nil {
		t.Error("Sign() succeeded without keys")
	}

	if _, err := signer.Verify("x"); err == nil {
		t.Error("Verify() succeeded without keys")
	}

	if _, err := signer.JWKS(); err == nil {
		t.Error("JWKS() succeeded without keys")
	}

	if err := newSigner(t, clock.System{}).Ready(); err != nil {
		t.Errorf("Ready() with keys = %v", err)
	}
}

func decodePart(t *testing.T, token string, index int) map[string]any {
	t.Helper()

	data, err := base64.RawURLEncoding.DecodeString(strings.Split(token, ".")[index])
	if err != nil {
		t.Fatal(err)
	}

	var part map[string]any
	if err := json.Unmarshal(data, &part); err != nil {
		t.Fatal(err)
	}

	return part
}

// shape maps each name in a token part to its JSON type.
func shape(t *testing.T, token string, index int) map[string]string {
	t.Helper()

	out := map[string]string{}
	for name, value := range decodePart(t, token, index) {
		out[name] = reflect.TypeOf(value).String()
	}

	return out
}
