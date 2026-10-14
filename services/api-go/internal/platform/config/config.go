// Package config loads the service configuration from environment variables.
// Names shared with the PHP API keep their PHP spelling (APP_URL, LOG_LEVEL,
// API_RATE_LIMIT_PER_MINUTE, FRONTEND_URL, JWT_*, MAIL_*, RESEND_*), so one
// .env file serves both backends.
package config

import (
	"encoding/base64"
	"errors"
	"fmt"
	"log/slog"
	"net"
	"net/url"
	"strings"
	"time"

	"github.com/caarlos0/env/v11"
)

// Config is everything the service reads from the environment.
type Config struct {
	// HTTPAddr is the listen address of the public HTTP server.
	HTTPAddr string `env:"HTTP_ADDR" envDefault:":8081"`
	// AdminAddr is the admin listener's address: /metrics and /health/ready
	// for scrapers and probes, never proxied to the internet.
	AdminAddr string `env:"ADMIN_ADDR" envDefault:":9090"`
	// OTLPEndpoint is the OpenTelemetry collector's base URL; traces are
	// exported to it when set, and stay in the process otherwise.
	OTLPEndpoint string `env:"OTEL_EXPORTER_OTLP_ENDPOINT"`
	// DatabaseURL is the Postgres connection string.
	DatabaseURL string `env:"DATABASE_URL,required,notEmpty"`
	// AppURL is the API's public base URL. Problem types are
	// {AppURL}/problems/{code}, as in the PHP API.
	AppURL string `env:"APP_URL" envDefault:"http://localhost:8080"`
	// RateLimitPerMinute is the per-client request limit under /api/. CI sets
	// it high so contract fuzzing is never throttled.
	RateLimitPerMinute int `env:"API_RATE_LIMIT_PER_MINUTE" envDefault:"60"`
	// LogLevel accepts the slog names and the PSR-3 names Laravel uses.
	LogLevel LogLevel `env:"LOG_LEVEL" envDefault:"info"`
	// ShutdownDrainDelay is how long /health/ready answers 503 before the
	// server stops accepting connections, so a proxy can stop routing to it.
	ShutdownDrainDelay time.Duration `env:"SHUTDOWN_DRAIN_DELAY" envDefault:"2s"`
	// ShutdownTimeout bounds how long in-flight requests may take to finish.
	ShutdownTimeout time.Duration `env:"SHUTDOWN_TIMEOUT" envDefault:"5s"`
	// AppName signs off the emails (Laravel's APP_NAME).
	AppName string `env:"APP_NAME" envDefault:"My Workouts"`
	// FrontendURL is the SPA's base URL; verification and reset links in
	// emails point at it. Defaults to APP_URL, as in config/app.php.
	FrontendURL string `env:"FRONTEND_URL"`
	// LinkSigningKey signs email verification links (ADR 0008). Required in
	// production; elsewhere a fixed development key is used when it is unset.
	LinkSigningKey string `env:"LINK_SIGNING_KEY"`
	// CatalogDir is the shared YAML catalog (the repository's catalog/,
	// ADR 0009) that `catalog sync` and `seed` apply.
	CatalogDir string `env:"CATALOG_DIR" envDefault:"catalog"`
	// AppEnv is Laravel's APP_ENV; unset means production, as in Laravel, so
	// demo seeding is refused unless an environment says otherwise.
	AppEnv string `env:"APP_ENV" envDefault:"production"`
	// BcryptRounds is the password hashing cost (Laravel's BCRYPT_ROUNDS).
	BcryptRounds int `env:"BCRYPT_ROUNDS" envDefault:"12"`
	// JWT configures access and refresh tokens, with the PHP API's names.
	JWT JWT `envPrefix:"JWT_"`
	// Mail configures outgoing mail and the support inbox (MAIL_*).
	Mail Mail `envPrefix:"MAIL_"`
	// Resend is the Resend account (RESEND_*).
	Resend Resend `envPrefix:"RESEND_"`
}

// Mail configures outgoing mail, with Laravel's names.
type Mail struct {
	// Mailer is log (messages go to the log: the dev stack and CI) or
	// resend.
	Mailer string `env:"MAILER" envDefault:"log"`
	// FromAddress and FromName are the sender of every email. FromName
	// defaults to APP_NAME, as in .env.example.
	FromAddress string `env:"FROM_ADDRESS" envDefault:"hello@example.com"`
	FromName    string `env:"FROM_NAME"`
	// InboundForwardTo receives the email sent to the production domain;
	// blank forwards nothing (the worker logs a warning instead).
	InboundForwardTo string `env:"INBOUND_FORWARD_TO"`
}

// Mailers MAIL_MAILER accepts.
const (
	MailerLog    = "log"
	MailerResend = "resend"
)

// Resend is the Resend account.
type Resend struct {
	// Key is the API key; MAIL_MAILER=resend requires it, and the inbox
	// worker reads received email with it.
	Key string `env:"KEY"`
	// WebhookSecret signs the webhooks Resend posts ("whsec_" plus base64);
	// blank rejects every webhook.
	WebhookSecret string `env:"WEBHOOK_SECRET"`
	// BaseURL is the API root; tests point it at a fake server.
	BaseURL string `env:"BASE_URL" envDefault:"https://api.resend.com/"`
}

// JWT is the token configuration. Durations are whole seconds, as the PHP
// API reads them.
type JWT struct {
	// Issuer defaults to APP_URL (see Load), as in config/jwt.php.
	Issuer   string `env:"ISSUER"`
	Audience string `env:"AUDIENCE" envDefault:"my-workouts"`
	// PrivateKey and PublicKey are base64 raw Ed25519 keys as
	// `php artisan jwt:keys` and `workouts keys generate` print them. Without
	// them the API still serves public routes and /health/ready reports jwt
	// down.
	PrivateKey string `env:"PRIVATE_KEY"`
	PublicKey  string `env:"PUBLIC_KEY"`
	// KeyID overrides the derived kid (the first 16 hex chars of the
	// public key's SHA-256).
	KeyID             string `env:"KEY_ID"`
	AccessTTLSeconds  int    `env:"ACCESS_TTL" envDefault:"900"`
	RefreshTTLSeconds int    `env:"REFRESH_TTL" envDefault:"2592000"`
	LeewaySeconds     int    `env:"LEEWAY" envDefault:"30"`
	// CookieSecure sets Secure on the refresh cookie; off only for plain-HTTP
	// local and CI runs.
	CookieSecure bool `env:"COOKIE_SECURE" envDefault:"true"`
}

// DevLinkSigningKey signs verification links outside production when
// LINK_SIGNING_KEY is unset, so the dev stack and tests need no secret.
const DevLinkSigningKey = "development-only-link-signing-key"

// AccessTTL is the access-token lifetime.
func (j JWT) AccessTTL() time.Duration { return time.Duration(j.AccessTTLSeconds) * time.Second }

// RefreshTTL is the refresh-token lifetime.
func (j JWT) RefreshTTL() time.Duration { return time.Duration(j.RefreshTTLSeconds) * time.Second }

// Leeway is the clock skew tolerated when checking expiry.
func (j JWT) Leeway() time.Duration { return time.Duration(j.LeewaySeconds) * time.Second }

// IsProduction reports whether APP_ENV is production.
func (c Config) IsProduction() bool { return c.AppEnv == "production" }

// Load parses and validates the configuration from environ, a map of
// environment variables (env.ToMap(os.Environ()) in production).
func Load(environ map[string]string) (Config, error) {
	cfg, err := env.ParseAsWithOptions[Config](env.Options{Environment: environ})
	if err != nil {
		return Config{}, fmt.Errorf("config: %w", err)
	}

	if cfg.JWT.Issuer == "" {
		cfg.JWT.Issuer = cfg.AppURL
	}

	if cfg.FrontendURL == "" {
		cfg.FrontendURL = cfg.AppURL
	}

	if cfg.Mail.FromName == "" {
		cfg.Mail.FromName = cfg.AppName
	}

	if cfg.LinkSigningKey == "" && !cfg.IsProduction() {
		cfg.LinkSigningKey = DevLinkSigningKey
	}

	if err := cfg.validate(); err != nil {
		return Config{}, fmt.Errorf("config: %w", err)
	}

	return cfg, nil
}

func (c Config) validate() error {
	var errs []error

	if _, _, err := net.SplitHostPort(c.HTTPAddr); err != nil {
		errs = append(errs, fmt.Errorf("HTTP_ADDR %q: %w", c.HTTPAddr, err))
	}

	if _, _, err := net.SplitHostPort(c.AdminAddr); err != nil {
		errs = append(errs, fmt.Errorf("ADMIN_ADDR %q: %w", c.AdminAddr, err))
	}

	if c.OTLPEndpoint != "" {
		if u, err := url.Parse(c.OTLPEndpoint); err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
			errs = append(errs, fmt.Errorf("OTEL_EXPORTER_OTLP_ENDPOINT must be an absolute http(s) URL, got %q", c.OTLPEndpoint))
		}
	}

	if u, err := url.Parse(c.AppURL); err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
		errs = append(errs, fmt.Errorf("APP_URL must be an absolute http(s) URL, got %q", c.AppURL))
	}

	if c.RateLimitPerMinute <= 0 {
		errs = append(errs, fmt.Errorf("API_RATE_LIMIT_PER_MINUTE must be positive, got %d", c.RateLimitPerMinute))
	}

	if c.ShutdownDrainDelay < 0 {
		errs = append(errs, fmt.Errorf("SHUTDOWN_DRAIN_DELAY must not be negative, got %s", c.ShutdownDrainDelay))
	}

	if c.ShutdownTimeout <= 0 {
		errs = append(errs, fmt.Errorf("SHUTDOWN_TIMEOUT must be positive, got %s", c.ShutdownTimeout))
	}

	if u, err := url.Parse(c.FrontendURL); err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
		errs = append(errs, fmt.Errorf("FRONTEND_URL must be an absolute http(s) URL, got %q", c.FrontendURL))
	}

	if c.IsProduction() && len(c.LinkSigningKey) < 32 {
		errs = append(errs, errors.New("LINK_SIGNING_KEY must be set to at least 32 characters in production"))
	}

	if c.BcryptRounds < 4 || c.BcryptRounds > 31 {
		errs = append(errs, fmt.Errorf("BCRYPT_ROUNDS must be between 4 and 31, got %d", c.BcryptRounds))
	}

	if (c.JWT.PrivateKey == "") != (c.JWT.PublicKey == "") {
		errs = append(errs, errors.New("JWT_PRIVATE_KEY and JWT_PUBLIC_KEY must be set together"))
	}

	for name, seconds := range map[string]int{"JWT_ACCESS_TTL": c.JWT.AccessTTLSeconds, "JWT_REFRESH_TTL": c.JWT.RefreshTTLSeconds} {
		if seconds <= 0 {
			errs = append(errs, fmt.Errorf("%s must be positive, got %d", name, seconds))
		}
	}

	if c.JWT.LeewaySeconds < 0 {
		errs = append(errs, fmt.Errorf("JWT_LEEWAY must not be negative, got %d", c.JWT.LeewaySeconds))
	}

	errs = append(errs, c.validateMail()...)

	return errors.Join(errs...)
}

func (c Config) validateMail() []error {
	var errs []error

	switch c.Mail.Mailer {
	case MailerLog:
	case MailerResend:
		if c.Resend.Key == "" {
			errs = append(errs, errors.New("RESEND_KEY must be set when MAIL_MAILER=resend"))
		}
	default:
		errs = append(errs, fmt.Errorf("MAIL_MAILER must be log or resend, got %q", c.Mail.Mailer))
	}

	if secret := c.Resend.WebhookSecret; secret != "" {
		if key, err := base64.StdEncoding.DecodeString(strings.TrimPrefix(secret, "whsec_")); err != nil || len(key) == 0 {
			errs = append(errs, errors.New("RESEND_WEBHOOK_SECRET must be whsec_ followed by base64"))
		}
	}

	if u, err := url.Parse(c.Resend.BaseURL); err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
		errs = append(errs, fmt.Errorf("RESEND_BASE_URL must be an absolute http(s) URL, got %q", c.Resend.BaseURL))
	}

	return errs
}

// LogLevel is a slog level that also accepts Laravel's PSR-3 level names, so
// LOG_LEVEL means the same thing to both backends.
type LogLevel slog.Level

// Level implements slog.Leveler.
func (l LogLevel) Level() slog.Level {
	return slog.Level(l)
}

// UnmarshalText parses debug, info, notice, warn, warning, error, critical,
// alert and emergency, case-insensitively.
func (l *LogLevel) UnmarshalText(text []byte) error {
	switch strings.ToLower(string(text)) {
	case "debug":
		*l = LogLevel(slog.LevelDebug)
	case "info", "notice":
		*l = LogLevel(slog.LevelInfo)
	case "warn", "warning":
		*l = LogLevel(slog.LevelWarn)
	case "error", "critical", "alert", "emergency":
		*l = LogLevel(slog.LevelError)
	default:
		return fmt.Errorf("unknown log level %q", text)
	}

	return nil
}
