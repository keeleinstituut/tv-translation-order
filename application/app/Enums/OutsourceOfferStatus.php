<?php

namespace App\Enums;

enum OutsourceOfferStatus: string
{
    case Pending = 'PENDING';
    case Notified = 'NOTIFIED';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Selected = 'SELECTED';
    case Rejected = 'REJECTED';
}
