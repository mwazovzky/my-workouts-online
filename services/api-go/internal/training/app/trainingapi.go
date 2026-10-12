package app

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/pagination"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/trainingapi"
)

var _ trainingapi.Training = (*Service)(nil)

// RecentWorkouts implements trainingapi.Training.
func (s *Service) RecentWorkouts(ctx context.Context, userID int64, n int) ([]trainingapi.WorkoutSummary, error) {
	result, err := s.Workouts.List(ctx, userID, pagination.Page{Number: 1, Size: n})
	if err != nil {
		return nil, err
	}

	summaries := make([]trainingapi.WorkoutSummary, len(result.Items))
	for i, w := range result.Items {
		summaries[i] = summary(w)
	}

	return summaries, nil
}

// InProgress implements trainingapi.Training.
func (s *Service) InProgress(ctx context.Context, userID int64) (trainingapi.WorkoutSummary, bool, error) {
	w, ok, err := s.Workouts.InProgress(ctx, userID)
	if err != nil || !ok {
		return trainingapi.WorkoutSummary{}, false, err
	}

	return summary(w), true, nil
}

// Counts implements trainingapi.Training.
func (s *Service) Counts(ctx context.Context, userID int64, since time.Time) (trainingapi.Counts, error) {
	all, recent, err := s.Workouts.CountCompleted(ctx, userID, since)

	return trainingapi.Counts{Completed: all, CompletedSince: recent}, err
}

func summary(w domain.Workout) trainingapi.WorkoutSummary {
	count := 0
	if w.ActivitiesCount != nil {
		count = *w.ActivitiesCount
	}

	return trainingapi.WorkoutSummary{
		ID: w.ID, UserID: w.UserID, TemplateID: w.TemplateID, Name: w.Name, Status: string(w.Status),
		ActivitiesCount: count, CreatedAt: w.CreatedAt, UpdatedAt: w.UpdatedAt,
	}
}
