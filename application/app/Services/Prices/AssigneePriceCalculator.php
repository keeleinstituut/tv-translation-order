<?php

namespace App\Services\Prices;

use App\Models\Assignment;
use App\Models\Volume;

readonly class AssigneePriceCalculator implements PriceCalculator
{
    public function __construct(private Assignment $assignment)
    {
    }

    public function getPrice(): ?float
    {
        if (empty($this->assignment->volumes)) {
            return null;
        }

        $prices = $this->assignment->volumes->map(function (Volume $volume) {
            return (new VolumePriceCalculator($volume))->getPrice();
        });

        if ($prices->search(null) === false) {
            return $prices->sum();
        }

        return null;
    }
}
