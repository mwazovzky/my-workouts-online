package http

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// caller is the authenticated principal; the authorize middleware lets no
// request reach these operations without one.
func caller(ctx context.Context) (auth.Principal, error) {
	p, ok := auth.FromContext(ctx)
	if !ok {
		return auth.Principal{}, domain.ErrUnauthenticated()
	}

	return p, nil
}

func locale(ctx context.Context) string {
	return string(i18n.FromContext(ctx))
}

// VerifyEmail implements verifyEmail.
func (h Identity) VerifyEmail(ctx context.Context, req gen.VerifyEmailRequestObject) (gen.VerifyEmailResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	b := req.Body
	if err := h.service.VerifyEmail(ctx, p, app.VerifyInput{ID: int64(b.Id), Hash: b.Hash, Expires: int64(b.Expires), Signature: b.Signature}); err != nil {
		return nil, err
	}

	return gen.VerifyEmail204Response{}, nil
}

// ResendVerification implements resendVerification.
func (h Identity) ResendVerification(ctx context.Context, _ gen.ResendVerificationRequestObject) (gen.ResendVerificationResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	if err := h.service.ResendVerification(ctx, p, locale(ctx)); err != nil {
		return nil, err
	}

	return gen.ResendVerification202Response{}, nil
}

// ForgotPassword implements forgotPassword.
func (h Identity) ForgotPassword(ctx context.Context, req gen.ForgotPasswordRequestObject) (gen.ForgotPasswordResponseObject, error) {
	if err := h.service.ForgotPassword(ctx, string(req.Body.Email), locale(ctx)); err != nil {
		return nil, err
	}

	return gen.ForgotPassword202Response{}, nil
}

// ResetPassword implements resetPassword.
func (h Identity) ResetPassword(ctx context.Context, req gen.ResetPasswordRequestObject) (gen.ResetPasswordResponseObject, error) {
	b := req.Body
	if err := h.service.ResetPassword(ctx, app.ResetInput{Token: b.Token, Email: string(b.Email), Password: b.Password}); err != nil {
		return nil, err
	}

	return gen.ResetPassword204Response{}, nil
}

// GetMe implements getMe.
func (h Identity) GetMe(ctx context.Context, _ gen.GetMeRequestObject) (gen.GetMeResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	user, err := h.service.Me(ctx, p)
	if err != nil {
		return nil, err
	}

	return gen.GetMe200JSONResponse{UserResponseJSONResponse: gen.UserResponseJSONResponse{Data: toUser(user)}}, nil
}

// UpdateMe implements updateMe.
func (h Identity) UpdateMe(ctx context.Context, req gen.UpdateMeRequestObject) (gen.UpdateMeResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	b := req.Body
	patch := app.MePatch{Name: b.Name}

	if b.Email != nil {
		email := string(*b.Email)
		patch.Email = &email
	}

	if b.Locale != nil {
		l := string(*b.Locale)
		patch.Locale = &l
	}

	if b.ThemePreference != nil {
		theme := string(*b.ThemePreference)
		patch.ThemePreference = &theme
	}

	user, err := h.service.UpdateMe(ctx, p, patch, locale(ctx))
	if err != nil {
		return nil, err
	}

	return gen.UpdateMe200JSONResponse{UserResponseJSONResponse: gen.UserResponseJSONResponse{Data: toUser(user)}}, nil
}

// UpdatePassword implements updatePassword.
func (h Identity) UpdatePassword(ctx context.Context, req gen.UpdatePasswordRequestObject) (gen.UpdatePasswordResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	if err := h.service.UpdatePassword(ctx, p, req.Body.CurrentPassword, req.Body.Password); err != nil {
		return nil, err
	}

	return gen.UpdatePassword204Response{}, nil
}

// DeleteMe implements deleteMe.
func (h Identity) DeleteMe(ctx context.Context, req gen.DeleteMeRequestObject) (gen.DeleteMeResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	if err := h.service.DeleteMe(ctx, p, req.Body.Password); err != nil {
		return nil, err
	}

	return gen.DeleteMe204Response{}, nil
}
