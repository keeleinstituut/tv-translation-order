<?php

namespace App\Enums;

enum ExternalRequestMode: string
{
    case Cascade = 'CASCADE';
    case Parallel = 'PARALLEL';
}
