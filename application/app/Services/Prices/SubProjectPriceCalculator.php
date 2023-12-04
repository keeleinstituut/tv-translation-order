<?php

namespace App\Services\Prices;

use App\Models\Assignment;
use App\Models\SubProject;

readonly class SubProjectPriceCalculator implements PriceCalculator
{
    public function __construct(private SubProject $subProject)
    {
    }

    public function getPrice(): ?float
    {
        $prices = $this->subProject->assignments()->pluck('price');

        if ($prices->isEmpty()) {
            return null;
        }

        if ($prices->search(null, true) === false) {
            return $prices->sum();
        }

        return null;
    }
}
