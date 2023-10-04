<?php

namespace App\Services\Prices;

interface PriceCalculator
{
    public function getPrice(): ?float;
}
