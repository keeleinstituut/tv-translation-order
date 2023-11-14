<?php

namespace App\Enums;

enum SubProjectStatus: string
{
    case New = 'NEW';
    case Registered = 'REGISTERED';
    case Cancelled = 'CANCELLED';
    case TasksSubmittedToVendors = 'TASKS_SUBMITTED_TO_VENDORS';
    case TasksInProgress = 'TASKS_IN_PROGRESS';
    case TasksCompleted = 'TASKS_COMPLETED';
    case Completed = 'COMPLETED';
}
