# syntax=docker/dockerfile:1.7
# Go API image: the `workouts` binary built from services/api-go. The dev stage
# is the toolchain with the repository bind-mounted (docker-compose.yml); the
# final stage is distroless, non-root and has no shell.
ARG GO_VERSION=1.27.3

# --- Stage 1: toolchain (dev container and build base) ---
FROM golang:${GO_VERSION} AS dev
# The bind-mounted repository is owned by the host user; `make check` runs git.
RUN git config --system --add safe.directory '*'
WORKDIR /src/services/api-go
# Build, migrate, then exec the binary so it is PID 1 and receives SIGTERM
# itself (`go run` would be killed without forwarding it, skipping the drain).
CMD ["sh", "-c", "go build -o /tmp/workouts ./cmd/workouts && /tmp/workouts migrate up && exec /tmp/workouts serve"]

# --- Stage 2: build ---
FROM dev AS build
ARG VERSION=dev
COPY services/api-go/go.mod services/api-go/go.sum ./
RUN --mount=type=cache,target=/go/pkg/mod go mod download
COPY services/api-go/ ./
RUN --mount=type=cache,target=/go/pkg/mod --mount=type=cache,target=/root/.cache/go-build \
    CGO_ENABLED=0 go build -trimpath -ldflags "-s -w -X main.version=${VERSION}" -o /out/workouts ./cmd/workouts

# --- Stage 3: production API image ---
FROM gcr.io/distroless/static-debian13:nonroot AS api
COPY --from=build /out/workouts /workouts
# The shared catalog `workouts catalog sync` applies on deploy (ADR 0009).
COPY catalog/ /catalog/
ENV CATALOG_DIR=/catalog
USER nonroot:nonroot
EXPOSE 8081
# No shell or curl in distroless: the binary probes its own liveness endpoint.
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 CMD ["/workouts", "healthcheck"]
ENTRYPOINT ["/workouts"]
CMD ["serve"]
