// Package config loads the service configuration from environment variables.
// Names shared with the PHP API keep their PHP spelling (LOG_LEVEL, and later
// APP_ENV, FRONTEND_URL, JWT_*), so one .env file serves both backends.
package config

import (
	"errors"
	"fmt"
	"log/slog"
	"net"
	"strings"
	"time"

	"github.com/caarlos0/env/v11"
)

// Config is everything the service reads from the environment.
type Config struct {
	// HTTPAddr is the listen address of the public HTTP server.
	HTTPAddr string `env:"HTTP_ADDR" envDefault:":8081"`
	// DatabaseURL is the Postgres connection string. Required now so a missing
	// value fails at startup; the connection pool arrives with the schema.
	DatabaseURL string `env:"DATABASE_URL,required,notEmpty"`
	// LogLevel accepts the slog names and the PSR-3 names Laravel uses.
	LogLevel LogLevel `env:"LOG_LEVEL" envDefault:"info"`
	// ShutdownDrainDelay is how long /health/ready answers 503 before the
	// server stops accepting connections, so a proxy can stop routing to it.
	ShutdownDrainDelay time.Duration `env:"SHUTDOWN_DRAIN_DELAY" envDefault:"2s"`
	// ShutdownTimeout bounds how long in-flight requests may take to finish.
	ShutdownTimeout time.Duration `env:"SHUTDOWN_TIMEOUT" envDefault:"5s"`
}

// Load parses and validates the configuration from environ, a map of
// environment variables (env.ToMap(os.Environ()) in production).
func Load(environ map[string]string) (Config, error) {
	cfg, err := env.ParseAsWithOptions[Config](env.Options{Environment: environ})
	if err != nil {
		return Config{}, fmt.Errorf("config: %w", err)
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

	if c.ShutdownDrainDelay < 0 {
		errs = append(errs, fmt.Errorf("SHUTDOWN_DRAIN_DELAY must not be negative, got %s", c.ShutdownDrainDelay))
	}

	if c.ShutdownTimeout <= 0 {
		errs = append(errs, fmt.Errorf("SHUTDOWN_TIMEOUT must be positive, got %s", c.ShutdownTimeout))
	}

	return errors.Join(errs...)
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
