# Quality gate and tasks for services/api-go. CI runs exactly `make check`.
# Without Go on the host: docker compose exec -T api-go make check

GOLANGCI_LINT_VERSION := v2.14.0
GOLANGCI_LINT := bin/golangci-lint-$(GOLANGCI_LINT_VERSION)
SQLC_VERSION := 1.31.1
SQLC := bin/sqlc-v$(SQLC_VERSION)
# Minimum statement coverage, generated code excluded (scripts/coverage-check.sh).
COVERAGE_MIN := 80
# Directories holding committed generated code: oapi-codegen's HTTP layer and
# one sqlc package per module (add a module's sqlcgen with its first query).
GENERATED := internal/platform/httpapi/gen internal/identity/adapters/postgres/sqlcgen
# The same, as a coverage-profile pattern.
GENERATED_PATTERN := /(httpapi/gen|sqlcgen)/

.PHONY: check tidy-check fmt fmt-check vet lint generate generate-check test vuln run

## check: everything CI runs; must pass before every commit
check: tidy-check fmt-check vet lint generate-check test vuln

## tidy-check: go.mod and go.sum are tidy
tidy-check:
	go mod tidy -diff

## fmt: format the code (gofumpt + goimports)
fmt: $(GOLANGCI_LINT)
	$(GOLANGCI_LINT) fmt

fmt-check: $(GOLANGCI_LINT)
	$(GOLANGCI_LINT) fmt --diff

vet:
	go vet ./...

lint: $(GOLANGCI_LINT)
	$(GOLANGCI_LINT) run

## generate: regenerate the HTTP layer from contracts/openapi.yaml and the
## query packages from sql/ and migrations/
generate: $(SQLC)
	go generate ./...
	$(SQLC) generate

## generate-check: the committed generated code matches its sources
generate-check: generate
	git diff --exit-code -- $(GENERATED)
	@untracked="$$(git ls-files --others --exclude-standard -- $(GENERATED))"; \
	if [ -n "$$untracked" ]; then echo "untracked generated files:"; echo "$$untracked"; exit 1; fi

## test: race detector, shuffled order, coverage threshold. Database tests use
## TEST_DATABASE_URL when set (the api-go container sets it to the compose
## postgres), otherwise a testcontainers Postgres.
test:
	go test -race -shuffle=on -coverprofile=coverage.out ./...
	scripts/coverage-check.sh coverage.out $(COVERAGE_MIN) '$(GENERATED_PATTERN)'

vuln:
	go tool govulncheck ./...

## run: start the API on HTTP_ADDR (default :8081)
run:
	go run ./cmd/workouts serve

# The official install script, pinned to the same tag as the binary. The
# version is in the file name, so bumping GOLANGCI_LINT_VERSION reinstalls.
$(GOLANGCI_LINT):
	@mkdir -p bin
	curl -sSfL https://raw.githubusercontent.com/golangci/golangci-lint/$(GOLANGCI_LINT_VERSION)/install.sh | sh -s -- -b bin/tmp $(GOLANGCI_LINT_VERSION)
	mv bin/tmp/golangci-lint $@
	rm -rf bin/tmp

# sqlc's release binary for this machine. sqlc is not a `tool` directive: it
# needs cgo and a large dependency tree that go.mod would then carry.
$(SQLC):
	@mkdir -p bin
	curl -sSfL https://github.com/sqlc-dev/sqlc/releases/download/v$(SQLC_VERSION)/sqlc_$(SQLC_VERSION)_$$(go env GOOS)_$$(go env GOARCH).tar.gz | tar -xzf - -C bin sqlc
	mv bin/sqlc $@
