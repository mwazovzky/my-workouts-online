<?php

namespace App\Enums;

enum EffortType: string
{
    case Repetitions = 'repetitions';
    case Duration = 'duration';

    /**
     * Short display label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Repetitions => __('reps'),
            self::Duration => __('sec'),
        };
    }

    /**
     * Column header label for the activity grid.
     */
    public function columnLabel(): string
    {
        return match ($this) {
            self::Repetitions => __('Reps'),
            self::Duration => __('Duration'),
        };
    }
}
