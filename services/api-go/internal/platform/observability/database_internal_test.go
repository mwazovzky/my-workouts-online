package observability

import (
	"bytes"
	"log/slog"
	"strings"
	"testing"

	"github.com/jackc/pgx/v5/tracelog"
)

func TestQueryLogLeavesOutTheArguments(t *testing.T) {
	t.Parallel()

	var buf bytes.Buffer
	q := queryLogger{logger: slog.New(slog.NewJSONHandler(&buf, &slog.HandlerOptions{Level: slog.LevelDebug}))}

	q.Log(t.Context(), tracelog.LogLevelInfo, "Query", map[string]any{
		"sql": "SELECT * FROM users WHERE email = $1", "args": []any{"ada@example.com"},
	})

	got := buf.String()
	if !strings.Contains(got, `"level":"DEBUG","msg":"pgx: Query"`) || !strings.Contains(got, "SELECT * FROM users") ||
		strings.Contains(got, "ada@example.com") {
		t.Errorf("log = %s", got)
	}
}
