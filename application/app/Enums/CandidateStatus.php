<?php

namespace App\Enums;

enum CandidateStatus: string
{
    case New = 'NEW';
    case SubmittedToVendor = 'SUBMITTED_TO_VENDOR';
    case Accepted = 'ACCEPTED';

    case Done = 'DONE';
}
