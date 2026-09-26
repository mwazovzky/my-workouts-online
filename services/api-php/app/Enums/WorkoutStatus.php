<?php

namespace App\Enums;

enum WorkoutStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
