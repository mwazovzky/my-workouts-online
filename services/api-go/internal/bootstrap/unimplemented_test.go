package bootstrap

import (
	"context"
	"errors"
	"reflect"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
)

// Every stub answers ErrNotImplemented, which the responder turns into 501.
func TestUnimplementedStubsReturnErrNotImplemented(t *testing.T) {
	t.Parallel()

	stubs := reflect.ValueOf(unimplemented{})
	ctx := reflect.ValueOf(context.Background())

	for i := range stubs.NumMethod() {
		method := stubs.Method(i)
		name := stubs.Type().Method(i).Name

		out := method.Call([]reflect.Value{ctx, reflect.New(method.Type().In(1)).Elem()})

		err, _ := out[1].Interface().(error)
		if !errors.Is(err, httpapi.ErrNotImplemented) || !out[0].IsNil() {
			t.Errorf("%s() = %v, %v; want nil, ErrNotImplemented", name, out[0], err)
		}
	}
}
