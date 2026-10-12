package http_test

import (
	"net/http"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// The policy the contract declares: Admin-tagged operations need an admin.
func TestAdminOperationsNeedAnAdmin(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	user := f.verified(t, "ada@example.com").AccessToken

	res := f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/admin/equipment", Token: user}).
		AssertStatus(http.StatusForbidden)

	if p := res.Problem(); p.Code != "forbidden" || p.Detail != "This action is unauthorized." {
		t.Errorf("problem = %+v", p)
	}

	if _, err := f.app.Identity.SeedDemoAdmin(t.Context()); err != nil {
		t.Fatal(err)
	}

	admin := decodeAuth(t, f.login("test@example.com", "password", "native", nil).AssertStatus(http.StatusOK))
	if !admin.User.IsAdmin {
		t.Error("the demo admin is not an admin")
	}

	f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/admin/equipment", Token: admin.AccessToken, SkipContract: true}).
		AssertStatus(http.StatusNotImplemented)
}
