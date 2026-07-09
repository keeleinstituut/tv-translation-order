<?php

namespace App\Enums;

enum OutsourceRequestType: string
{
    case Incoming = 'INCOMING';
    case Outgoing = 'OUTGOING';
}
