<?php

namespace App\Enums;

enum WorkoutLogStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
