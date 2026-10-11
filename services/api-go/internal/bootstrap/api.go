package bootstrap

import (
	identityhttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/http"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/health"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/meta"
)

// api is the generated strict server, assembled by embedding: each module
// contributes the operations it implements, and unimplemented answers the
// rest. A module handler is added here with its first operation.
type api struct {
	health.Liveness
	*health.Readiness
	meta.Handler
	identityhttp.Handlers
	unimplemented
}

var _ gen.StrictServerInterface = api{}
