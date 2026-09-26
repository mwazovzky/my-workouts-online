<?php

namespace App\Services\Admin;

use App\Models\Program;

interface ProgramServiceInterface
{
    /**
     * @param  array{translations: array<string, array<string, ?string>>, assignments: array<int, array{workout_template_id: int, weekday: string}>}  $data
     */
    public function create(array $data): Program;

    /**
     * @param  array{translations: array<string, array<string, ?string>>, assignments: array<int, array{workout_template_id: int, weekday: string}>}  $data
     */
    public function update(Program $program, array $data): Program;

    public function delete(Program $program): void;
}
