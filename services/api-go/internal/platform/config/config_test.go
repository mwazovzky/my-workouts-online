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

	cfg, err := config.Load(map[string]string{"DATABASE_URL": "postgres://localhost/workouts", "LINK_SIGNING_KEY": strings.Repeat("k", 32)})
	if err != nil {
		t.Fatalf("Load() error = %v", err)
	}

	want := config.Config{
		HTTPAddr:           ":8081",
		DatabaseURL:        "postgres://localhost/workouts",
		AppURL:             "http://localhost:8080",
		RateLimitPerMinute: 60,
		LogLevel:           config.LogLevel(slog.LevelInfo),
		LinkSigningKey:     strings.Repeat("k", 32),
		CatalogDir:         "catalog",
		ShutdownDrainDelay: 2 * time.Second,
		ShutdownTimeout:    5 * time.Second,
		AppName:            "My Workouts",
		FrontendURL:        "http://localhost:8080",
		AppEnv:             "production",
		BcryptRounds:       12,
		JWT: config.JWT{
			Issuer:            "http://localhost:8080",
			Audience:          "my-workouts",
			AccessTTLSeconds:  900,
			RefreshTTLSeconds: 2592000,
			LeewaySeconds:     30,
			CookieSecure:      true,
		},
		Mail:   config.Mail{Mailer: "log", FromAddress: "hello@example.com", FromName: "My Workouts"},
		Resend: config.Resend{BaseURL: "https://api.resend.com/"},
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
		"APP_ENV":                   "local",
		"CATALOG_DIR":               "/src/catalog",
		"APP_NAME":                  "Workouts",
		"FRONTEND_URL":              "https://app.my-workouts.online",
		"BCRYPT_ROUNDS":             "4",
		"JWT_ISSUER":                "https://issuer.test",
		"JWT_AUDIENCE":              "my-workouts-test",
		"JWT_PRIVATE_KEY":           "private",
		"JWT_PUBLIC_KEY":            "public",
		"JWT_KEY_ID":                "kid",
		"JWT_ACCESS_TTL":            "60",
		"JWT_REFRESH_TTL":           "120",
		"JWT_LEEWAY":                "0",
		"JWT_COOKIE_SECURE":         "false",
		"MAIL_MAILER":               "resend",
		"MAIL_FROM_ADDRESS":         "noreply@my-workouts.online",
		"MAIL_FROM_NAME":            "My Workouts Team",
		"MAIL_INBOUND_FORWARD_TO":   "owner@example.com",
		"RESEND_KEY":                "re_test",
		"RESEND_WEBHOOK_SECRET":     "whsec_dGVzdA==",
		"RESEND_BASE_URL":           "http://resend.test/",
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
		AppName:            "Workouts",
		FrontendURL:        "https://app.my-workouts.online",
		LinkSigningKey:     config.DevLinkSigningKey,
		CatalogDir:         "/src/catalog",
		AppEnv:             "local",
		BcryptRounds:       4,
		JWT: config.JWT{
			Issuer:            "https://issuer.test",
			Audience:          "my-workouts-test",
			PrivateKey:        "private",
			PublicKey:         "public",
			KeyID:             "kid",
			AccessTTLSeconds:  60,
			RefreshTTLSeconds: 120,
			CookieSecure:      false,
		},
		Mail: config.Mail{
			Mailer: "resend", FromAddress: "noreply@my-workouts.online", FromName: "My Workouts Team",
			InboundForwardTo: "owner@example.com",
		},
		Resend: config.Resend{Key: "re_test", WebhookSecret: "whsec_dGVzdA==", BaseURL: "http://resend.test/"},
	}
	if diff := cmp.Diff(want, cfg); diff != "" {
		t.Errorf("Load() mismatch (-want +got):\n%s", diff)
	}
}

func TestLoadRejectsInvalidValues(t *testing.T) {
	t.Parallel()

	valid := map[string]string{"DATABASE_URL": "postgres://localhost/workouts", "LINK_SIGNING_KEY": strings.Repeat("k", 32)}

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
		{"bcrypt rounds out of range", with(valid, "BCRYPT_ROUNDS", "3"), "BCRYPT_ROUNDS"},
		{"one jwt key without the other", with(valid, "JWT_PRIVATE_KEY", "x"), "JWT_PUBLIC_KEY"},
		{"zero access ttl", with(valid, "JWT_ACCESS_TTL", "0"), "JWT_ACCESS_TTL"},
		{"negative leeway", with(valid, "JWT_LEEWAY", "-1"), "JWT_LEEWAY"},
		{"relative frontend url", with(valid, "FRONTEND_URL", "/app"), "FRONTEND_URL"},
		{"short link key in production", with(valid, "LINK_SIGNING_KEY", "short"), "LINK_SIGNING_KEY"},
		{"unknown mailer", with(valid, "MAIL_MAILER", "smtp"), "MAIL_MAILER"},
		{"resend mailer without a key", with(valid, "MAIL_MAILER", "resend"), "RESEND_KEY"},
		{"webhook secret not base64", with(valid, "RESEND_WEBHOOK_SECRET", "whsec_not base64!"), "RESEND_WEBHOOK_SECRET"},
		{"relative resend url", with(valid, "RESEND_BASE_URL", "/resend"), "RESEND_BASE_URL"},
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

func TestDerivedValues(t *testing.T) {
	t.Parallel()

	cfg, err := config.Load(map[string]string{"DATABASE_URL": "postgres://localhost/workouts", "APP_URL": "https://my-workouts.online", "APP_ENV": "local"})
	if err != nil {
		t.Fatal(err)
	}

	if cfg.JWT.Issuer != "https://my-workouts.online" {
		t.Errorf("JWT issuer = %q, want APP_URL", cfg.JWT.Issuer)
	}

	if cfg.FrontendURL != "https://my-workouts.online" || cfg.LinkSigningKey != config.DevLinkSigningKey {
		t.Errorf("FrontendURL, LinkSigningKey = %q, %q; want APP_URL and the development key", cfg.FrontendURL, cfg.LinkSigningKey)
	}

	prod, err := config.Load(map[string]string{"DATABASE_URL": "postgres://localhost/workouts", "LINK_SIGNING_KEY": strings.Repeat("k", 32)})
	if err != nil || !prod.IsProduction() {
		t.Errorf("IsProduction() without APP_ENV = %v (%v), want true as in Laravel", prod.IsProduction(), err)
	}

	if cfg.JWT.AccessTTL() != 15*time.Minute || cfg.JWT.RefreshTTL() != 30*24*time.Hour || cfg.JWT.Leeway() != 30*time.Second {
		t.Errorf("durations = %v, %v, %v", cfg.JWT.AccessTTL(), cfg.JWT.RefreshTTL(), cfg.JWT.Leeway())
	}
}
