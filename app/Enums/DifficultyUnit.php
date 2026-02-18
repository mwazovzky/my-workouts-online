<?php

namespace App\Enums;

enum DifficultyUnit: string
{
    case Kilograms = 'kilograms';
    case Pounds = 'pounds';
    case Plates = 'plates';
    case None = 'none';

    /**
     * Short display label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Kilograms => __('kg'),
            self::Pounds => __('lbs'),
            self::Plates => __('Plates'),
            self::None => '',
        };
    }

    /**
     * Column header label for the activity grid.
     */
    public function columnLabel(): string
    {
        return match ($this) {
            self::Kilograms => __('Weight').' ('.__('kg').')',
            self::Pounds => __('Weight').' ('.__('lbs').')',
            self::Plates => __('Plates'),
            self::None => '',
        };
    }
}
