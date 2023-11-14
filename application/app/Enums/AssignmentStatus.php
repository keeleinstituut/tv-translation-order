<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case New = 'NEW';
    case InProgress = 'IN_PROGRESS';
    case Done = 'DONE';
}
