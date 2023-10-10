<?php

namespace App\Enums;

enum SubProjectStatus: string
{
    case New = 'NEW';
    case Registered = 'REGISTERED';
    case Cancelled = 'CANCELLED';
    case TasksSubmittedToRework = 'TASKS_SUBMITTED_TO_REWORK';
    case TasksInProgress = 'TASKS_IN_PROGRESS';
    case TasksCompleted = 'TASKS_COMPLETED';
    case Completed = 'COMPLETED';
}
