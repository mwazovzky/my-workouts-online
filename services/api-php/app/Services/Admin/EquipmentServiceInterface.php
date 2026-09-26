<?php

namespace App\Services\Admin;

use App\Models\Equipment;

interface EquipmentServiceInterface
{
    /**
     * @param  array{difficulty_unit: string, translations: array<string, array<string, ?string>>}  $data
     */
    public function create(array $data): Equipment;

    /**
     * @param  array{difficulty_unit: string, translations: array<string, array<string, ?string>>}  $data
     */
    public function update(Equipment $equipment, array $data): Equipment;

    public function delete(Equipment $equipment): void;
}
