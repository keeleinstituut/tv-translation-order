<?php

namespace App\Enums;

enum OutsourceRequestPriceMode: string
{
    case PriceListBased = 'PRICELIST_BASED';
    case FixedPrice = 'FIXED_PRICE';
    case AskForPrice = 'ASK_FOR_PRICE';
}
