package bootstrap

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// unimplemented answers every contract operation no story has implemented
// yet with 501 (httpapi.ErrNotImplemented). It sits in api next to the
// module handlers at the same depth, so a story that implements an
// operation must delete its stub here: two methods of one name would make
// the call ambiguous and api would stop compiling.
type unimplemented struct{}

func (unimplemented) SocialLogin(context.Context, gen.SocialLoginRequestObject) (gen.SocialLoginResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}
