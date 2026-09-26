<?php

namespace App\Services\Admin;

use App\Models\Program;
use Illuminate\Support\Facades\DB;

class ProgramService implements ProgramServiceInterface
{
    public function create(array $data): Program
    {
        return DB::transaction(function () use ($data) {
            $program = Program::createWithTranslations($data['translations']);
            $this->syncAssignments($program, $data['assignments']);

            return $this->loadForResource($program);
        });
    }

    public function update(Program $program, array $data): Program
    {
        return DB::transaction(function () use ($program, $data) {
            $program->updateTranslations($data['translations']);
            $program->workoutTemplates()->detach();
            $this->syncAssignments($program, $data['assignments']);

            return $this->loadForResource($program);
        });
    }

    public function delete(Program $program): void
    {
        DB::transaction(function () use ($program) {
            $program->workoutTemplates()->detach();
            $program->delete();
        });
    }

    /**
     * Attach one pivot row per assignment. attach() (rather than sync()) is used
     * so the same template may appear on more than one weekday.
     *
     * @param  array<int, array{workout_template_id: int, weekday: string}>  $assignments
     */
    private function syncAssignments(Program $program, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $program->workoutTemplates()->attach(
                $assignment['workout_template_id'],
                ['weekday' => $assignment['weekday']],
            );
        }
    }

    private function loadForResource(Program $program): Program
    {
        return $program->load([
            'workoutTemplates' => fn ($query) => $query->orderBy('program_workout_template.id'),
        ]);
    }
}
