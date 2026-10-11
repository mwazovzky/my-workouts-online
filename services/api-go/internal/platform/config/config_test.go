package config_test

import (
	"log/slog"
	"maps"
	"strings"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
)

func TestLoadAppliesDefaults(t *testing.T) {
	t.Parallel()

	cfg, err := config.Load(map[string]string{"DATABASE_URL": "postgres://localhost/workouts"})
	if err != nil {
		t.Fatalf("Load() error = %v", err)
	}

	want := config.Config{
		HTTPAddr:           ":8081",
		DatabaseURL:        "postgres://localhost/workouts",
		AppURL:             "http://localhost:8080",
		RateLimitPerMinute: 60,
		LogLevel:           config.LogLevel(slog.LevelInfo),
		ShutdownDrainDelay: 2 * time.Second,
		ShutdownTimeout:    5 * time.Second,
	}
	if diff := cmp.Diff(want, cfg); diff != "" {
		t.Errorf("Load() mismatch (-want +got):\n%s", diff)
	}
}

func TestLoadReadsEnvironment(t *testing.T) {
	t.Parallel()

	cfg, err := config.Load(map[string]string{
		"DATABASE_URL":              "postgres://db/workouts",
		"HTTP_ADDR":                 "127.0.0.1:9000",
		"APP_URL":                   "https://my-workouts.online",
		"API_RATE_LIMIT_PER_MINUTE": "100000",
		"LOG_LEVEL":                 "debug",
		"SHUTDOWN_DRAIN_DELAY":      "0s",
		"SHUTDOWN_TIMEOUT":          "30s",
	})
	if err != nil {
		t.Fatalf("Load() error = %v", err)
	}

	want := config.Config{
		HTTPAddr:           "127.0.0.1:9000",
		DatabaseURL:        "postgres://db/workouts",
		AppURL:             "https://my-workouts.online",
		RateLimitPerMinute: 100000,
		LogLevel:           config.LogLevel(slog.LevelDebug),
		ShutdownDrainDelay: 0,
		ShutdownTimeout:    30 * time.Second,
	}
	if diff := cmp.Diff(want, cfg); diff != "" {
		t.Errorf("Load() mismatch (-want +got):\n%s", diff)
	}
}

func TestLoadRejectsInvalidValues(t *testing.T) {
	t.Parallel()

	valid := map[string]string{"DATABASE_URL": "postgres://localhost/workouts"}

	tests := []struct {
		name    string
		environ map[string]string
		wantErr string
	}{
		{"missing database url", map[string]string{}, "DATABASE_URL"},
		{"empty database url", map[string]string{"DATABASE_URL": ""}, "DATABASE_URL"},
		{"address without port", with(valid, "HTTP_ADDR", "localhost"), "HTTP_ADDR"},
		{"unknown log level", with(valid, "LOG_LEVEL", "verbose"), "verbose"},
		{"unparsable duration", with(valid, "SHUTDOWN_TIMEOUT", "soon"), `invalid duration "soon"`},
		{"negative drain delay", with(valid, "SHUTDOWN_DRAIN_DELAY", "-1s"), "SHUTDOWN_DRAIN_DELAY"},
		{"zero shutdown timeout", with(valid, "SHUTDOWN_TIMEOUT", "0s"), "SHUTDOWN_TIMEOUT"},
		{"relative app url", with(valid, "APP_URL", "/api"), "APP_URL"},
		{"zero rate limit", with(valid, "API_RATE_LIMIT_PER_MINUTE", "0"), "API_RATE_LIMIT_PER_MINUTE"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			_, err := config.Load(tt.environ)
			if err == nil {
				t.Fatal("Load() error = nil, want an error")
			}

			if !strings.Contains(err.Error(), tt.wantErr) {
				t.Errorf("Load() error = %q, want it to mention %q", err, tt.wantErr)
			}
		})
	}
}

func TestLogLevelAcceptsLaravelNames(t *testing.T) {
	t.Parallel()

	tests := map[string]slog.Level{
		"debug":     slog.LevelDebug,
		"INFO":      slog.LevelInfo,
		"notice":    slog.LevelInfo,
		"warn":      slog.LevelWarn,
		"warning":   slog.LevelWarn,
		"error":     slog.LevelError,
		"critical":  slog.LevelError,
		"alert":     slog.LevelError,
		"emergency": slog.LevelError,
	}

	for name, want := range tests {
		var level config.LogLevel
		if err := level.UnmarshalText([]byte(name)); err != nil {
			t.Errorf("UnmarshalText(%q) error = %v", name, err)

			continue
		}

		if level.Level() != want {
			t.Errorf("UnmarshalText(%q) = %v, want %v", name, level.Level(), want)
		}
	}
}

func with(base map[string]string, key, value string) map[string]string {
	environ := maps.Clone(base)
	environ[key] = value

	return environ
}
