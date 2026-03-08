<?php

namespace App\Http\Controllers;

use App\Enums\WorkoutStatus;
use App\Http\Resources\WorkoutResource;
use App\Models\Program;
use App\Models\Workout;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $enrolledPrograms = $user->programs()
            ->with(['workoutTemplates'])
            ->get();

        $upcomingSchedule = $this->buildUpcomingSchedule($enrolledPrograms);

        $inProgressWorkout = Workout::query()
            ->ownedBy($user)
            ->withTemplate()
            ->withActivitiesCount()
            ->where('status', WorkoutStatus::InProgress)
            ->latestUpdated()
            ->first();

        $recentWorkouts = Workout::query()
            ->ownedBy($user)
            ->withTemplate()
            ->withActivitiesCount()
            ->latestUpdated()
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'upcomingSchedule' => $upcomingSchedule,
            'inProgressWorkout' => $inProgressWorkout ? (new WorkoutResource($inProgressWorkout))->resolve() : null,
            'recentWorkouts' => WorkoutResource::collection($recentWorkouts)->resolve(),
            'summary' => [
                'enrolled_programs_count' => $enrolledPrograms->count(),
                'completed_workouts_count' => Workout::query()
                    ->ownedBy($user)
                    ->where('status', WorkoutStatus::Completed)
                    ->count(),
                'completed_last_7_days_count' => Workout::query()
                    ->ownedBy($user)
                    ->where('status', WorkoutStatus::Completed)
                    ->where('updated_at', '>=', now()->subDays(7))
                    ->count(),
                'upcoming_workouts_count' => count($upcomingSchedule),
            ],
        ]);
    }

    /**
     * @param  Collection<int, Program>  $programs
     * @return array<int, array{id: string, program_id: int, program_name: ?string, workout_template_id: int, workout_name: ?string, weekday: string, weekday_label: string, scheduled_at: string, scheduled_for: string}>
     */
    protected function buildUpcomingSchedule(Collection $programs): array
    {
        $today = CarbonImmutable::today();
        $windowEnd = $today->addDays(6);

        return $programs
            ->flatMap(function (Program $program) use ($today, $windowEnd) {
                return $program->workoutTemplates->map(function ($workoutTemplate) use ($program, $today, $windowEnd) {
                    $scheduledDate = $this->nextScheduledDate($workoutTemplate->pivot->weekday, $today);

                    if ($scheduledDate->greaterThan($windowEnd)) {
                        return null;
                    }

                    return [
                        'id' => sprintf('%d-%d-%s', $program->id, $workoutTemplate->id, $scheduledDate->toDateString()),
                        'program_id' => $program->id,
                        'program_name' => $program->name,
                        'workout_template_id' => $workoutTemplate->id,
                        'workout_name' => $workoutTemplate->name,
                        'weekday' => $workoutTemplate->pivot->weekday,
                        'weekday_label' => __($workoutTemplate->pivot->weekday),
                        'scheduled_at' => $scheduledDate->startOfDay()->toIso8601String(),
                        'scheduled_for' => $scheduledDate->toDateString(),
                    ];
                })->filter();
            })
            ->sortBy('scheduled_at')
            ->values()
            ->all();
    }

    protected function nextScheduledDate(string $weekday, CarbonImmutable $today): CarbonImmutable
    {
        $targetDay = match ($weekday) {
            'Sunday' => 0,
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            default => throw new \InvalidArgumentException("Unsupported weekday [{$weekday}]."),
        };

        $daysUntil = ($targetDay - $today->dayOfWeek + 7) % 7;

        return $today->addDays($daysUntil);
    }
}
