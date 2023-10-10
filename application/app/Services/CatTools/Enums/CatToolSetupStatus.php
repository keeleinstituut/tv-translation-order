<?php

namespace App\Services\CatTools\Enums;

enum CatToolSetupStatus: string
{
    case Created = 'CREATED';
    case Failed = 'FAILED';
    case NotStarted = 'NOT_STARTED';
    case InProgress = 'IN_PROGRESS';
}
