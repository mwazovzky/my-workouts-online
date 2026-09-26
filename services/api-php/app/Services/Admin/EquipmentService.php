<?php

namespace App\Services\Admin;

use App\Models\Equipment;
use Illuminate\Support\Facades\DB;

class EquipmentService implements EquipmentServiceInterface
{
    public function create(array $data): Equipment
    {
        return DB::transaction(function () use ($data) {
            return Equipment::createWithTranslations(
                $data['translations'],
                ['difficulty_unit' => $data['difficulty_unit']],
            );
        });
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        return DB::transaction(function () use ($equipment, $data) {
            $equipment->update(['difficulty_unit' => $data['difficulty_unit']]);
            $equipment->updateTranslations($data['translations']);

            return $equipment;
        });
    }

    public function delete(Equipment $equipment): void
    {
        $equipment->delete();
    }
}
