package http_test

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"net/http"
	"strings"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/JwksTest.php.

type jwks struct {
	Keys []map[string]string `json:"keys"`
}

func (f *fixture) jwks(t *testing.T) jwks {
	t.Helper()

	res := f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/.well-known/jwks.json"}).
		AssertStatus(http.StatusOK).
		AssertHeader("Cache-Control", "public, max-age=3600")

	var set jwks
	if err := json.Unmarshal(res.Body.Bytes(), &set); err != nil {
		t.Fatal(err)
	}

	return set
}

func TestPublishesTheEd25519PublicKey(t *testing.T) {
	t.Parallel()

	set := newFixture(t).jwks(t)
	if len(set.Keys) != 1 {
		t.Fatalf("keys = %v, want one", set.Keys)
	}

	key := set.Keys[0]
	if key["kty"] != "OKP" || key["crv"] != "Ed25519" || key["alg"] != "EdDSA" || key["use"] != "sig" || key["kid"] != "6ea67caa64eb2e85" {
		t.Errorf("key = %v", key)
	}

	x, err := base64.RawURLEncoding.DecodeString(key["x"])
	if err != nil || base64.StdEncoding.EncodeToString(x) != testJWT.PublicKey {
		t.Errorf("x = %q, want JWT_PUBLIC_KEY", key["x"])
	}
}

func TestAccessTokenVerifiesAgainstThePublishedKey(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken
	key := f.jwks(t).Keys[0]

	parts := strings.Split(token, ".")
	headerJSON, _ := base64.RawURLEncoding.DecodeString(parts[0])

	var header map[string]string
	if err := json.Unmarshal(headerJSON, &header); err != nil {
		t.Fatal(err)
	}

	if header["alg"] != "EdDSA" || header["kid"] != key["kid"] {
		t.Errorf("header = %v, want EdDSA and the published kid", header)
	}

	public, _ := base64.RawURLEncoding.DecodeString(key["x"])
	signature, _ := base64.RawURLEncoding.DecodeString(parts[2])

	if !ed25519.Verify(public, []byte(parts[0]+"."+parts[1]), signature) {
		t.Error("the signature does not verify against the published key")
	}
}
