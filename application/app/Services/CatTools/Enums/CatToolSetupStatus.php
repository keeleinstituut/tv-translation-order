<?php

namespace App\Services\CatTools\Enums;

enum CatToolSetupStatus
{
    case Done;
    case Failed;
    case NotStarted;
    case InProgress;
}
