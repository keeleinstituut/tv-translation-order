<?php

namespace App\Enums;

enum TaskType: string
{
    case Default = 'DEFAULT';
    case Review = 'REVIEW';
    case ClientReview = 'CLIENT_REVIEW';
    case Correcting = 'CORRECTING';
}
