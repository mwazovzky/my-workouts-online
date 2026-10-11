// Package meta implements getMeta: the public options every client needs
// before sign-in, mirroring the PHP MetaController.
package meta

import (
	"context"
	"net/http"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// cacheControl lets clients and proxies keep the options for an hour.
const cacheControl = "public, max-age=3600"

// Handler serves getMeta.
type Handler struct {
	bundle *i18n.Bundle
}

// NewHandler returns the getMeta handler for the locales in bundle.
func NewHandler(bundle *i18n.Bundle) Handler {
	return Handler{bundle: bundle}
}

// GetMeta implements the getMeta operation.
func (h Handler) GetMeta(context.Context, gen.GetMetaRequestObject) (gen.GetMetaResponseObject, error) {
	var body gen.GetMeta200JSONResponse

	body.Data.AvailableLocales = map[string]string{}
	for locale, name := range h.bundle.Names() {
		body.Data.AvailableLocales[string(locale)] = name
	}

	body.Data.ThemePreferences = []gen.ThemePreference{gen.Light, gen.Dark, gen.System}

	return cached{body}, nil
}

// cached adds Cache-Control to the generated response, which only writes
// the headers the contract declares.
type cached struct {
	gen.GetMeta200JSONResponse
}

func (c cached) VisitGetMetaResponse(w http.ResponseWriter) error {
	w.Header().Set("Cache-Control", cacheControl)

	return c.GetMeta200JSONResponse.VisitGetMetaResponse(w)
}
