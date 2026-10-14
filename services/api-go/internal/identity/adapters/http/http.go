// Package http implements identity's contract operations on the generated
// strict server: getJwks, register, login, refresh, logout, reauth,
// verifyEmail, resendVerification, forgotPassword, resetPassword, getMe,
// updateMe, updatePassword and deleteMe.
// Web clients get the refresh token as an httpOnly cookie scoped to the
// refresh endpoint; native clients get it in the body.
package http

import (
	"context"
	"net/http"
	"time"

	openapi_types "github.com/oapi-codegen/runtime/types"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// The refresh cookie, as the PHP API sets it.
const (
	cookieName = "refresh_token"
	cookiePath = "/api/v1/auth/refresh"
)

// jwksCacheControl lets verifiers keep the key set for an hour.
const jwksCacheControl = "public, max-age=3600"

// Throttles are identity's per-operation limits, as in routes/api.php.
func Throttles() map[string]httpapi.Throttle {
	return map[string]httpapi.Throttle{
		"register":           {Limit: 10, Window: time.Minute},
		"reauth":             {Limit: 6, Window: time.Minute},
		"verifyEmail":        {Limit: 6, Window: time.Minute},
		"resendVerification": {Limit: 6, Window: time.Minute},
		"forgotPassword":     {Limit: 6, Window: time.Minute},
		"resetPassword":      {Limit: 6, Window: time.Minute},
	}
}

// KeySet publishes the token verification keys.
type KeySet interface {
	JWKS() ([]map[string]any, error)
}

// Config configures the handlers.
type Config struct {
	// CookieSecure sets Secure on the refresh cookie (JWT_COOKIE_SECURE).
	CookieSecure bool
	Clock        clock.Clock
}

// Identity implements identity's operations.
type Identity struct {
	service *app.Service
	keys    KeySet
	cfg     Config
}

// New returns identity's handlers.
func New(service *app.Service, keys KeySet, cfg Config) Identity {
	return Identity{service: service, keys: keys, cfg: cfg}
}

// GetJwks implements getJwks.
func (h Identity) GetJwks(context.Context, gen.GetJwksRequestObject) (gen.GetJwksResponseObject, error) {
	keys, err := h.keys.JWKS()
	if err != nil {
		return nil, err
	}

	return cachedJWKS{gen.GetJwks200JSONResponse{Keys: keys}}, nil
}

// Register implements register.
func (h Identity) Register(ctx context.Context, req gen.RegisterRequestObject) (gen.RegisterResponseObject, error) {
	body := req.Body

	session, err := h.service.Register(ctx, app.RegisterInput{
		Name: body.Name, Email: string(body.Email), Password: body.Password, DeviceName: body.DeviceName,
		Locale: string(i18n.FromContext(ctx)), Client: string(body.Client),
	})
	if err != nil {
		return nil, err
	}

	payload, cookie := h.tokenResponse(session, body.Client)

	return gen.Register201JSONResponse{Body: payload, Headers: gen.Register201ResponseHeaders{SetCookie: cookie}}, nil
}

// Login implements login.
func (h Identity) Login(ctx context.Context, req gen.LoginRequestObject) (gen.LoginResponseObject, error) {
	body := req.Body

	session, err := h.service.Login(ctx, app.LoginInput{
		Email: string(body.Email), Password: body.Password, DeviceName: body.DeviceName,
		ClientIP: httpapi.ClientIP(httpapi.Request(ctx)), Client: string(body.Client),
	})
	if err != nil {
		return nil, err
	}

	payload, cookie := h.tokenResponse(session, body.Client)

	return gen.Login200JSONResponse{Body: payload, Headers: gen.Login200ResponseHeaders{SetCookie: cookie}}, nil
}

// Refresh implements refresh. A token in the body is the native flow; without
// one the token comes from the cookie (the web flow), which the contract
// guards with the required X-Requested-With header.
func (h Identity) Refresh(ctx context.Context, req gen.RefreshRequestObject) (gen.RefreshResponseObject, error) {
	client, plain := gen.Native, ""

	if req.Body != nil && req.Body.RefreshToken != nil && *req.Body.RefreshToken != "" {
		plain = *req.Body.RefreshToken
	} else {
		client = gen.Web

		if c, err := httpapi.Request(ctx).Cookie(cookieName); err == nil {
			plain = c.Value
		}
	}

	session, err := h.service.Refresh(ctx, plain)
	if err != nil {
		return nil, err
	}

	payload, cookie := h.tokenResponse(session, client)

	return gen.Refresh200JSONResponse{Body: payload, Headers: gen.Refresh200ResponseHeaders{SetCookie: cookie}}, nil
}

// Logout implements logout: the caller's family is revoked and the cookie
// cleared.
func (h Identity) Logout(ctx context.Context, _ gen.LogoutRequestObject) (gen.LogoutResponseObject, error) {
	principal, ok := auth.FromContext(ctx)
	if !ok {
		return nil, domain.ErrUnauthenticated()
	}

	if err := h.service.Logout(ctx, principal); err != nil {
		return nil, err
	}

	return clearedCookie{cookie: h.cookie("", -1)}, nil
}

// Reauth implements reauth.
func (h Identity) Reauth(ctx context.Context, req gen.ReauthRequestObject) (gen.ReauthResponseObject, error) {
	principal, ok := auth.FromContext(ctx)
	if !ok {
		return nil, apperr.Forbidden(apperr.CodeForbidden, "This action is unauthorized.")
	}

	step, err := h.service.Reauth(ctx, principal, req.Body.Password)
	if err != nil {
		return nil, err
	}

	return gen.Reauth200JSONResponse{AccessToken: step.AccessToken, ExpiresIn: step.ExpiresIn}, nil
}

// tokenResponse is the AuthResponse body and, for web clients, the refresh
// cookie; native clients get the refresh token in the body instead.
func (h Identity) tokenResponse(session app.Session, client gen.ClientKind) (gen.AuthResponse, *string) {
	body := gen.AuthResponse{
		AccessToken: session.Pair.AccessToken,
		TokenType:   gen.Bearer,
		ExpiresIn:   session.Pair.ExpiresIn,
		User:        toUser(session.User),
	}

	if client == gen.Native {
		body.RefreshToken = &session.Pair.RefreshToken

		return body, nil
	}

	maxAge := int(session.Pair.RefreshExpiresAt.Sub(h.cfg.Clock.Now()).Round(time.Second).Seconds())
	cookie := h.cookie(session.Pair.RefreshToken, maxAge)

	return body, &cookie
}

// cookie is the Set-Cookie value for the refresh token; maxAge -1 clears it.
func (h Identity) cookie(value string, maxAge int) string {
	c := &http.Cookie{ //nolint:gosec // G124: Secure follows JWT_COOKIE_SECURE, off only for plain-HTTP dev and CI
		Name:     cookieName,
		Value:    value,
		Path:     cookiePath,
		MaxAge:   maxAge,
		HttpOnly: true,
		Secure:   h.cfg.CookieSecure,
		SameSite: http.SameSiteStrictMode,
	}

	if maxAge > 0 {
		c.Expires = h.cfg.Clock.Now().Add(time.Duration(maxAge) * time.Second)
	}

	return c.String()
}

func toUser(u domain.User) gen.User {
	name := u.Name

	return gen.User{
		Id:              int(u.ID),
		Name:            &name,
		Email:           openapi_types.Email(u.Email),
		Locale:          gen.Locale(u.Locale),
		ThemePreference: gen.ThemePreference(u.ThemePreference),
		IsAdmin:         u.IsAdmin,
		EmailVerifiedAt: u.EmailVerifiedAt,
	}
}

// cachedJWKS adds Cache-Control to the generated response.
type cachedJWKS struct {
	gen.GetJwks200JSONResponse
}

func (c cachedJWKS) VisitGetJwksResponse(w http.ResponseWriter) error {
	w.Header().Set("Cache-Control", jwksCacheControl)

	return c.GetJwks200JSONResponse.VisitGetJwksResponse(w)
}

// clearedCookie is logout's 204 with the refresh cookie cleared; the
// contract's 204 declares no headers.
type clearedCookie struct {
	cookie string
}

func (c clearedCookie) VisitLogoutResponse(w http.ResponseWriter) error {
	w.Header().Set("Set-Cookie", c.cookie)

	return gen.Logout204Response{}.VisitLogoutResponse(w)
}
