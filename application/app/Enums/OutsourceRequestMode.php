<?php

namespace App\Enums;

enum OutsourceRequestMode: string
{
    case Cascade = 'CASCADE';
    case Parallel = 'PARALLEL';
}
