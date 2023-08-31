<?php

namespace App\Enums;

enum InstitutionUserStatus: string
{
    case Created = 'CREATED';
    case Activated = 'ACTIVATED';
    case Deactivated = 'DEACTIVATED';
}
