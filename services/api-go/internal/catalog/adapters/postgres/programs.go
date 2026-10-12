package postgres

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// Programs implements ports.Programs.
type Programs struct {
	tx *postgres.Transactor
}

var _ ports.Programs = (*Programs)(nil)

// NewPrograms returns the Programs repository.
func NewPrograms(tx *postgres.Transactor) *Programs {
	return &Programs{tx: tx}
}

func (r *Programs) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// List implements ports.Programs.
func (r *Programs) List(ctx context.Context, userID int64) ([]ports.ProgramEntry, error) {
	rows, err := r.q(ctx).ListPrograms(ctx, userID)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	entries := make([]ports.ProgramEntry, 0, len(rows))
	for _, row := range rows {
		program, err := toProgram(row.ID, row.Name, row.Description, row.Translations)
		if err != nil {
			return nil, err
		}

		entries = append(entries, ports.ProgramEntry{Program: program, Enrolled: row.Enrolled})
	}

	return entries, nil
}

// ByID implements ports.Programs.
func (r *Programs) ByID(ctx context.Context, id, userID int64) (ports.ProgramEntry, error) {
	q := r.q(ctx)

	row, err := q.GetProgram(ctx, userID, id)
	if err != nil {
		return ports.ProgramEntry{}, postgres.MapError(err)
	}

	program, err := toProgram(row.ID, row.Name, row.Description, row.Translations)
	if err != nil {
		return ports.ProgramEntry{}, err
	}

	assignments, err := q.ListProgramAssignments(ctx, id)
	if err != nil {
		return ports.ProgramEntry{}, postgres.MapError(err)
	}

	program.Assignments = make([]domain.Assignment, 0, len(assignments))
	for _, a := range assignments {
		templateName, description, err := named(a.Name, a.Description, a.Translations)
		if err != nil {
			return ports.ProgramEntry{}, err
		}

		program.Assignments = append(program.Assignments, domain.Assignment{
			Template: domain.WorkoutTemplate{ID: a.ID, Name: templateName, Description: description},
			Weekday:  domain.Weekday(a.Weekday),
		})
	}

	return ports.ProgramEntry{Program: program, Enrolled: row.Enrolled}, nil
}

// Exists implements ports.Programs.
func (r *Programs) Exists(ctx context.Context, id int64) (bool, error) {
	exists, err := r.q(ctx).ProgramExists(ctx, id)

	return exists, postgres.MapError(err)
}

// Enroll implements ports.Programs.
func (r *Programs) Enroll(ctx context.Context, programID, userID int64, now time.Time) error {
	return postgres.MapError(r.q(ctx).Enroll(ctx, sqlcgen.EnrollParams{ProgramID: programID, UserID: userID, EnrolledAt: now}))
}

// Unenroll implements ports.Programs.
func (r *Programs) Unenroll(ctx context.Context, programID, userID int64) error {
	return postgres.MapError(r.q(ctx).Unenroll(ctx, programID, userID))
}

// EnrolledSchedule implements ports.Programs.
func (r *Programs) EnrolledSchedule(ctx context.Context, userID int64) ([]ports.ScheduledTemplate, error) {
	rows, err := r.q(ctx).ListEnrolledSchedule(ctx, userID)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	schedule := make([]ports.ScheduledTemplate, 0, len(rows))
	for _, row := range rows {
		programName, err := name(row.ProgramName, row.ProgramTranslations)
		if err != nil {
			return nil, err
		}

		templateName, err := name(row.TemplateName, row.TemplateTranslations)
		if err != nil {
			return nil, err
		}

		schedule = append(schedule, ports.ScheduledTemplate{
			ProgramID: row.ProgramID, ProgramName: programName,
			TemplateID: row.TemplateID, TemplateName: templateName,
			Weekday: domain.Weekday(row.Weekday),
		})
	}

	return schedule, nil
}

func toProgram(id int64, source string, description *string, raw []byte) (domain.Program, error) {
	programName, programDescription, err := named(source, description, raw)
	if err != nil {
		return domain.Program{}, err
	}

	return domain.Program{ID: id, Name: programName, Description: programDescription}, nil
}
