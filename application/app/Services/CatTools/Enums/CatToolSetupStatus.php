<?php

namespace App\Services\CatTools\Enums;

enum CatToolSetupStatus: string
{
    case Done = 'DONE';
    case Failed = 'FAILED';
    case NotStarted = 'NOT_STARTED';
    case InProgress = 'IN_PROGRESS';
}
