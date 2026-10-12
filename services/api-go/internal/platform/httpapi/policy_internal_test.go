package httpapi

import (
	"slices"
	"testing"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// The policy read from the contract matches the PHP route groups: public
// routes outside `auth:jwt`, the account routes under `auth:jwt` alone, and
// everything else under `auth:jwt` + `verified`, admin under `admin` too.
func TestPolicyMatchesThePHPRouteGroups(t *testing.T) {
	t.Parallel()

	spec, err := gen.GetSpec()
	if err != nil {
		t.Fatal(err)
	}

	var public, signedIn, verified, admin []string

	for _, op := range operationsOf(spec) {
		switch {
		case op.public:
			public = append(public, op.id)
		case op.admin:
			admin = append(admin, op.id)
		case op.verified:
			verified = append(verified, op.id)
		default:
			signedIn = append(signedIn, op.id)
		}

		if op.admin && !op.verified {
			t.Errorf("%s is admin-only but not verified-only", op.id)
		}
	}

	for _, ids := range [][]string{public, signedIn, verified} {
		slices.Sort(ids)
	}

	wantPublic := []string{"forgotPassword", "getHealth", "getJwks", "getMeta", "getReadiness", "login", "refresh", "register", "resetPassword", "socialLogin"}
	wantSignedIn := []string{"deleteMe", "getMe", "logout", "reauth", "resendVerification", "updateMe", "updatePassword", "verifyEmail"}
	wantVerified := []string{
		"completeWorkout", "createWorkout", "deleteWorkout", "enrollProgram", "getDashboard", "getProgram", "getWorkout",
		"getWorkoutTemplate", "listExercises", "listPrograms", "listWorkouts", "repeatWorkout", "replaceWorkoutActivities", "unenrollProgram",
	}

	for name, pair := range map[string][2][]string{"public": {wantPublic, public}, "signed in": {wantSignedIn, signedIn}, "verified": {wantVerified, verified}} {
		if diff := cmp.Diff(pair[0], pair[1]); diff != "" {
			t.Errorf("%s operations (-want +got):\n%s", name, diff)
		}
	}

	if len(admin) != 25 {
		t.Errorf("admin operations = %d, want the 25 admin CRUD operations", len(admin))
	}
}
