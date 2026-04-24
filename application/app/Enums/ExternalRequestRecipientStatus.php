<?php

namespace App\Enums;

enum ExternalRequestRecipientStatus: string
{
    case Pending = 'PENDING';
    case Notified = 'NOTIFIED';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Selected = 'SELECTED';
}
