package jobs

import (
	"bytes"
	"log/slog"
	"strings"
	"testing"
)

func TestRiverLogsFromInfoUp(t *testing.T) {
	t.Parallel()

	var buf bytes.Buffer
	logger := slog.New(atLeast{
		Handler: slog.NewJSONHandler(&buf, &slog.HandlerOptions{Level: slog.LevelDebug}),
		level:   riverLogLevel,
	}).With("service", "worker-go").WithGroup("river")

	logger.Debug("producer status")
	logger.Info("client started", "queue", "default")

	if got := buf.String(); strings.Contains(got, "producer status") ||
		!strings.Contains(got, `"service":"worker-go","river":{"queue":"default"}`) {
		t.Errorf("log = %s, want only the info line with its attributes and group", got)
	}
}
