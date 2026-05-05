<?php

namespace App\Enums;

enum OutsourceRequestStatus: string
{
    case Active = 'ACTIVE';
    case Fulfilled = 'FULFILLED';
    case Cancelled = 'CANCELLED';
}
