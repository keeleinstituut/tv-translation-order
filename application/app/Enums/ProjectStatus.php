<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case New = 'NEW';
    case Registered = 'REGISTERED';
    case Cancelled = 'CANCELLED';
    case SubmittedToClient = 'SUBMITTED_TO_CLIENT';
    case Rejected = 'REJECTED';
    case Corrected = 'CORRECTED';
    case Accepted = 'ACCEPTED';
}
