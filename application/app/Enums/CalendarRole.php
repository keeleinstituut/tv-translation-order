<?php

namespace App\Enums;

enum CalendarRole: string
{
    case ProjectManager = 'tpm';
    case Vendor = 'vendor';
    case Client = 'client';
}
