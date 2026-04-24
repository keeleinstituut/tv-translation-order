<?php

namespace App\Enums;

enum ExternalRequestStatus: string
{
    case Active = 'ACTIVE';
    case Fulfilled = 'FULFILLED';
    case Cancelled = 'CANCELLED';
}
