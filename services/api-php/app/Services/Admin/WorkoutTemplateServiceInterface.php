<?php

namespace App\Services\Admin;

use App\Models\WorkoutTemplate;

interface WorkoutTemplateServiceInterface
{
    /**
     * @param  array{translations: array<string, array<string, ?string>>, activities: array<int, array<string, mixed>>}  $data
     */
    public function create(array $data): WorkoutTemplate;

    /**
     * @param  array{translations: array<string, array<string, ?string>>, activities: array<int, array<string, mixed>>}  $data
     */
    public function update(WorkoutTemplate $template, array $data): WorkoutTemplate;

    public function delete(WorkoutTemplate $template): void;
}
