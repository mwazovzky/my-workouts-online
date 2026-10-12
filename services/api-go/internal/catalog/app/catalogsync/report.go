package catalogsync

import (
	"strings"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
)

// Outcome is what the sync did to one record.
type Outcome string

// The outcomes, in report order.
const (
	Created   Outcome = "created"
	Adopted   Outcome = "adopted"
	Updated   Outcome = "updated"
	Unchanged Outcome = "unchanged"
	Retired   Outcome = "retired"
)

// Outcomes lists the outcomes in report order.
var Outcomes = []Outcome{Created, Adopted, Updated, Unchanged, Retired}

// Report is what a sync did or, on a dry run, would do: PHP's
// CatalogSyncReport.
type Report struct {
	DryRun bool
	// Committed reports whether the changes were written.
	Committed bool
	// Changes lists every record that is not unchanged, as
	// "exercises [squat]: updated, changed rest_time_seconds".
	Changes  []string
	Warnings []string

	counts map[ports.Kind]map[Outcome]int
}

func newReport(dryRun bool) *Report {
	r := &Report{DryRun: dryRun, counts: map[ports.Kind]map[Outcome]int{}}
	for _, kind := range ports.Kinds {
		r.counts[kind] = map[Outcome]int{}
	}

	return r
}

func (r *Report) record(kind ports.Kind, outcome Outcome, slug string, changed []string) {
	r.counts[kind][outcome]++

	if outcome == Unchanged {
		return
	}

	change := string(kind) + " [" + slug + "]: " + string(outcome)
	if len(changed) > 0 {
		change += ", changed " + strings.Join(changed, ", ")
	}

	r.Changes = append(r.Changes, change)
}

func (r *Report) warn(message string) {
	r.Warnings = append(r.Warnings, message)
}

// Count returns how many records of kind had outcome.
func (r *Report) Count(kind ports.Kind, outcome Outcome) int {
	return r.counts[kind][outcome]
}

// Total returns how many records of every kind had outcome.
func (r *Report) Total(outcome Outcome) int {
	total := 0
	for _, counts := range r.counts {
		total += counts[outcome]
	}

	return total
}
