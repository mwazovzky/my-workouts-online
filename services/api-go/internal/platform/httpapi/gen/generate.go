// Package gen is the HTTP layer generated from contracts/openapi.yaml by
// oapi-codegen: models, the stdlib net/http router, the strict server
// interface and the embedded spec. Never edit gen.go; run `make generate`.
package gen

//go:generate go tool oapi-codegen -config oapi-codegen.yaml ../../../../../../contracts/openapi.yaml
