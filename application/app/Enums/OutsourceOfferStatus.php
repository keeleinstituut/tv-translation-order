<?php

namespace App\Enums;

enum OutsourceOfferStatus: string
{
    case RequestPending = 'REQUEST_PENDING';
    case RequestSent = 'REQUEST_SENT';
    case RequestAccepted = 'REQUEST_ACCEPTED';
    case RequestDeclined = 'REQUEST_DECLINED';
    case RequestExpired = 'REQUEST_EXPIRED';
    case OfferAccepted = 'OFFER_ACCEPTED';
    case OfferDeclined = 'OFFER_DECLINED';
}
